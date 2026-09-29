<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 22 Jul 2026 — per Chris: business-rule fraud/document-integrity
// checks (duplicate file hashes, duplicate reference numbers, math
// and date-range anomalies). Deliberately does NOT attempt AI-based
// tamper detection (font/logo/image manipulation) or merchant document
// fingerprinting — those were the parts of the original spec flagged
// as unreliable (no general-purpose forensic accuracy), expensive
// (a Claude API call per document, per check), and dependent on a
// merchant history dataset this system doesn't have. This service
// only ever flags for human review — it never declares a document
// genuine or fraudulent on its own, per Chris's own requirement.
class FraudDetectionService
{
    // Point weights per anomaly type — deliberately simple, additive
    // scoring rather than a black-box model, so an Admin looking at a
    // flagged item can see exactly why it scored what it did (each
    // anomaly's own weight is stored in the anomaly detail, not just
    // the final number).
    private const WEIGHTS = [
        'DUPLICATE_FILE_HASH'        => 40, // the exact same file was uploaded before, anywhere in the system
        'DUPLICATE_REFERENCE_NUMBER' => 35, // same receipt/invoice/policy number already on file
        'MATH_MISMATCH'              => 20, // subtotal + tax + service charge != total
        'DATE_OUT_OF_RANGE'          => 15, // transaction date is in the future, or older than the claim window
        'SOFT_DUPLICATE'             => 25, // same customer+product+amount submitted very recently (existing Sales Transaction check)
    ];

    private const DEFAULT_MEDIUM_THRESHOLD   = 20;
    private const DEFAULT_HIGH_THRESHOLD     = 45;
    private const DEFAULT_CRITICAL_THRESHOLD = 70;

    public function weight(string $code): int
    {
        return self::WEIGHTS[$code] ?? 10;
    }

    private function threshold(string $key, int $default): int
    {
        $value = DB::table('system_settings')->where('setting_key', $key)->value('setting_value');
        return $value !== null ? (int) $value : $default;
    }

    public function riskThresholds(): array
    {
        return [
            'medium'   => $this->threshold('fraud_risk_medium_threshold', self::DEFAULT_MEDIUM_THRESHOLD),
            'high'     => $this->threshold('fraud_risk_high_threshold', self::DEFAULT_HIGH_THRESHOLD),
            'critical' => $this->threshold('fraud_risk_critical_threshold', self::DEFAULT_CRITICAL_THRESHOLD),
        ];
    }

    public function scoreToLevel(int $score): string
    {
        $t = $this->riskThresholds();
        if ($score >= $t['critical']) return 'CRITICAL';
        if ($score >= $t['high']) return 'HIGH';
        if ($score >= $t['medium']) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Hash a file already on disk (Storage path resolved to absolute
     * by the caller) — sha256, same algorithm Sales Transaction
     * already uses for its own reused-photo check.
     */
    public function hashFile(string $absolutePath): ?string
    {
        if (!is_file($absolutePath)) return null;
        return hash_file('sha256', $absolutePath) ?: null;
    }

    /**
     * Has this exact file (by hash) already been submitted anywhere
     * in $table.$hashColumn? Optionally excluding the row currently
     * being checked (e.g. when re-checking after an edit).
     */
    public function isDuplicateHash(string $table, string $hashColumn, string $hash, ?string $excludeIdColumn = null, ?string $excludeId = null): bool
    {
        $query = DB::table($table)->where($hashColumn, $hash);
        if ($excludeIdColumn && $excludeId) {
            $query->where($excludeIdColumn, '!=', $excludeId);
        }
        return $query->exists();
    }

    /**
     * Has this reference/receipt/invoice/policy number already been
     * submitted? $extraWhere lets the caller scope it (e.g. only
     * within the same merchant, or excluding soft-deleted rows).
     */
    public function isDuplicateReferenceNumber(string $table, string $column, string $value, array $extraWhere = [], ?string $excludeIdColumn = null, ?string $excludeId = null): bool
    {
        $query = DB::table($table)->where($column, $value);
        foreach ($extraWhere as $col => $val) {
            $query->where($col, $val);
        }
        if ($excludeIdColumn && $excludeId) {
            $query->where($excludeIdColumn, '!=', $excludeId);
        }
        return $query->exists();
    }

    /**
     * Returns an anomaly array if the date is outside a sane window,
     * or null if it's fine. $maxDaysOld is the outer edge of a
     * reasonable claim period (configurable per document type by the
     * caller) — this is NOT the same as any specific claim-deadline
     * business rule elsewhere in the app, just a fraud-signal sanity
     * check (e.g. a receipt dated 3 years ago suddenly being claimed).
     */
    public function dateRangeAnomaly(\Carbon\Carbon $date, int $maxDaysOld = 180, bool $disallowFuture = true): ?array
    {
        if ($disallowFuture && $date->isFuture()) {
            return ['code' => 'DATE_OUT_OF_RANGE', 'label' => 'Future-dated document', 'detail' => 'Document date (' . $date->format('d M Y') . ') is in the future.'];
        }
        if ($date->diffInDays(now()) > $maxDaysOld) {
            return ['code' => 'DATE_OUT_OF_RANGE', 'label' => 'Document older than claim window', 'detail' => 'Document date (' . $date->format('d M Y') . ') is ' . (int) $date->diffInDays(now()) . ' days old, beyond the ' . $maxDaysOld . '-day window.'];
        }
        return null;
    }

    /**
     * Returns an anomaly array if subtotal + tax + service charge
     * don't add up to the stated total (within a small tolerance for
     * rounding), or null if the math checks out.
     */
    public function mathTotalAnomaly(float $subtotal, float $tax, float $serviceCharge, float $total, float $tolerance = 0.05): ?array
    {
        $expected = round($subtotal + $tax + $serviceCharge, 2);
        if (abs($expected - $total) > $tolerance) {
            return [
                'code'   => 'MATH_MISMATCH',
                'label'  => 'Total does not match line items',
                'detail' => "Subtotal ({$subtotal}) + tax ({$tax}) + service charge ({$serviceCharge}) = {$expected}, but stated total is {$total}.",
            ];
        }
        return null;
    }

    /**
     * Combines a list of anomaly arrays (each ['code','label','detail'])
     * into a total score + level, WITHOUT writing anything — lets a
     * caller preview the score before deciding to flag.
     */
    public function score(array $anomalies): array
    {
        $score = 0;
        foreach ($anomalies as $a) {
            $score += $this->weight($a['code']);
        }
        $score = min(100, $score);
        return ['score' => $score, 'level' => $this->scoreToLevel($score)];
    }

    /**
     * Writes a fraud_review_flags row if $anomalies is non-empty, and
     * notifies Admin immediately for HIGH/CRITICAL (MEDIUM/LOW just
     * sit in the queue for the regular reminder/escalation command to
     * surface — see CheckFraudReviewReminders). Returns the flag
     * summary, or null if there was nothing to flag.
     */
    public function flag(string $flaggableType, string $flaggableId, string $agentId, array $anomalies): ?array
    {
        if (empty($anomalies)) {
            return null;
        }

        $result = $this->score($anomalies);
        $flagId = (string) Str::uuid();

        DB::table('fraud_review_flags')->insert([
            'flag_id'        => $flagId,
            'flaggable_type' => $flaggableType,
            'flaggable_id'   => $flaggableId,
            'agent_id'       => $agentId,
            'risk_score'     => $result['score'],
            'risk_level'     => $result['level'],
            'anomalies'      => json_encode($anomalies),
            'status'         => 'OPEN',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        if (in_array($result['level'], ['HIGH', 'CRITICAL'], true)) {
            $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();
            $agent = DB::table('agents')->where('agent_id', $agentId)->first();
            app(NotificationService::class)->notify(
                $admins->all(),
                'FRAUD_REVIEW_' . $result['level'],
                ($result['level'] === 'CRITICAL' ? 'Critical' : 'High') . ' Risk Submission Flagged',
                ($agent->full_name ?? 'An agent') . "'s submission was flagged {$result['level']} risk (score {$result['score']}/100) and is on hold pending your review.",
                $agentId
            );
        }

        return ['flag_id' => $flagId, 'risk_score' => $result['score'], 'risk_level' => $result['level']];
    }

    /**
     * Is there an unresolved (OPEN or UNDER_REVIEW) flag against this
     * item? Callers (e.g. the Document Credit top-up approval screen,
     * the commission calculation step) should check this BEFORE
     * letting a flagged transaction proceed to payment/credit.
     */
    public function hasUnresolvedFlag(string $flaggableType, string $flaggableId): bool
    {
        return DB::table('fraud_review_flags')
            ->where('flaggable_type', $flaggableType)
            ->where('flaggable_id', $flaggableId)
            ->whereIn('status', ['OPEN', 'UNDER_REVIEW'])
            ->exists();
    }
}
