<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\PhoneNumberService;

// NEW 29 Jul 2026 — EspoCRM integration (task #252): every customer this
// service creates or updates also gets synced to a matching EspoCRM
// Contact. Injected via the constructor since this class is only ever
// resolved through Laravel's container (EmailIngestionController), never
// manually `new`'d.

/**
 * NEW 17 Jul 2026 — foundational service for the future Document
 * Extraction Engine. Given customer details read off an uploaded
 * document (name, NRIC, address, phone, etc.), this resolves which
 * customer record they belong to:
 *
 *   - No existing customer with that NRIC -> create a new record.
 *   - Existing customer, details match      -> nothing to do.
 *   - Existing customer, details differ     -> update the changed
 *     fields automatically, but ALWAYS write an audit row to
 *     customer_change_logs first (old value, new value, source,
 *     timestamp) so nothing is ever silently overwritten.
 *
 * Deliberately usable on its own today (e.g. from the manual Sales
 * Transaction form) even though the PDF extraction engine that will
 * eventually be its main caller hasn't been built yet.
 */
class CustomerResolutionService
{
    /**
     * Fields safe to auto-patch when they differ. full_name is
     * intentionally NOT here — a full_name mismatch under the same
     * NRIC is a possible wrong-match/typo problem, not a "customer
     * moved house" problem, so it's surfaced via needsReview instead
     * of being silently rewritten.
     */
    private const UPDATABLE_FIELDS = ['email', 'phone', 'address', 'postcode', 'city', 'state'];

    public function __construct(private EspoCrmService $espoCrm)
    {
    }

    /**
     * @param array $extracted Expected keys: nric, full_name, email, phone,
     *   address, postcode, city, state. Missing keys are simply skipped.
     * @param string $ownerAgentId Agent this customer is attributed to —
     *   only used when a brand-new customer record is being created;
     *   ownership of an existing customer is immutable here.
     * @param string $source e.g. 'DOCUMENT_UPLOAD', 'MANUAL_ENTRY', 'EMAIL_INGESTION'.
     * @param string|null $sourceReference Traceability pointer — e.g. the
     *   document reference number or policy_id that triggered this.
     * @param string|null $changedByAgentId Who was logged in, if anyone —
     *   null is valid for a fully automated pipeline.
     * @param array $fieldConfidence Optional per-field confidence (0-1)
     *   from the extraction engine. Fields below $confidenceThreshold are
     *   NOT auto-updated, they're returned in needsReview instead.
     *
     * @return array{customer_id: string, created: bool, changes: array, needsReview: array}
     */
    public function resolveOrCreate(
        array $extracted,
        string $ownerAgentId,
        string $source = 'DOCUMENT_UPLOAD',
        ?string $sourceReference = null,
        ?string $changedByAgentId = null,
        array $fieldConfidence = [],
        float $confidenceThreshold = 0.75
    ): array {
        // UPDATED 29 Jul 2026 — app-wide phone standardization. This is a
        // best-effort pipeline (document extraction / email ingestion),
        // not a live form, so a bad OCR/parse of the phone number should
        // never block the whole customer record from being created —
        // it's just left blank instead, same as any other unrecognized
        // field, rather than throwing a hard validation error.
        if (!empty($extracted['phone'])) {
            $extracted['phone'] = PhoneNumberService::isValid($extracted['phone'])
                ? PhoneNumberService::normalize($extracted['phone'])
                : null;
        }

        $nricRaw = trim((string) ($extracted['nric'] ?? ''));
        if ($nricRaw === '') {
            throw new \InvalidArgumentException('Customer resolution requires an NRIC.');
        }
        $nricHash = hash('sha256', $nricRaw);

        $existing = DB::table('customers')
            ->where('nric_hash', $nricHash)
            ->where('is_deleted', false)
            ->first();

        // ---- No match: brand new customer ----
        if (!$existing) {
            $customerId = Str::uuid()->toString();
            DB::table('customers')->insert([
                'customer_id'       => $customerId,
                'nric_encrypted'    => encrypt($nricRaw),
                'nric_hash'         => $nricHash,
                'full_name'         => $extracted['full_name'] ?? null,
                'email'             => $extracted['email'] ?? null,
                'phone'             => $extracted['phone'] ?? null,
                'address'           => $extracted['address'] ?? null,
                'postcode'          => $extracted['postcode'] ?? null,
                'city'              => $extracted['city'] ?? null,
                'state'             => $extracted['state'] ?? null,
                'owned_by_agent_id' => $ownerAgentId,
                'created_by'        => $changedByAgentId,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // NEW 29 Jul 2026 — EspoCRM integration (task #252).
            $newCustomer = DB::table('customers')->where('customer_id', $customerId)->first();
            $contactId = $this->espoCrm->createContact($newCustomer);
            if ($contactId) {
                DB::table('customers')->where('customer_id', $customerId)->update(['espocrm_contact_id' => $contactId]);
            }

            return [
                'customer_id' => $customerId,
                'created'     => true,
                'changes'     => [],
                'needsReview' => [],
            ];
        }

        // ---- Match found: compare & selectively update ----
        $changes = [];
        $needsReview = [];
        $updates = [];

        // Name mismatch under an identical NRIC is a red flag worth a
        // human glance (typo, or the wrong person entirely) — never
        // auto-corrected here.
        if (!empty($extracted['full_name'])) {
            $normalizedNew = strtolower(trim($extracted['full_name']));
            $normalizedOld = strtolower(trim((string) $existing->full_name));
            if ($normalizedNew !== '' && $normalizedNew !== $normalizedOld) {
                $needsReview[] = [
                    'field'      => 'full_name',
                    'old_value'  => $existing->full_name,
                    'new_value'  => $extracted['full_name'],
                    'confidence' => $fieldConfidence['full_name'] ?? null,
                    'note'       => 'Name differs from records under the same NRIC — verify this is the same person before assuming a details change.',
                ];
            }
        }

        foreach (self::UPDATABLE_FIELDS as $field) {
            if (!array_key_exists($field, $extracted)) {
                continue;
            }
            $newValue = $extracted[$field] !== '' ? $extracted[$field] : null;
            $oldValue = $existing->$field;

            if ($newValue === $oldValue) {
                continue;
            }

            $confidence = $fieldConfidence[$field] ?? 1.0;
            if ($confidence < $confidenceThreshold) {
                $needsReview[] = [
                    'field'      => $field,
                    'old_value'  => $oldValue,
                    'new_value'  => $newValue,
                    'confidence' => $confidence,
                    'note'       => 'Low extraction confidence — not auto-applied.',
                ];
                continue;
            }

            $updates[$field] = $newValue;
            $changes[] = ['field' => $field, 'old_value' => $oldValue, 'new_value' => $newValue];
        }

        if (!empty($updates)) {
            $updates['updated_at'] = now();
            $updates['updated_by'] = $changedByAgentId;
            DB::table('customers')->where('customer_id', $existing->customer_id)->update($updates);

            foreach ($changes as $change) {
                DB::table('customer_change_logs')->insert([
                    'change_id'           => Str::uuid()->toString(),
                    'customer_id'         => $existing->customer_id,
                    'field_name'          => $change['field'],
                    'old_value'           => $change['old_value'],
                    'new_value'           => $change['new_value'],
                    'source'              => $source,
                    'source_reference'    => $sourceReference,
                    'changed_by_agent_id' => $changedByAgentId,
                    'created_at'          => now(),
                ]);
            }

            // NEW 29 Jul 2026 — EspoCRM integration (task #252).
            $refreshed = DB::table('customers')->where('customer_id', $existing->customer_id)->first();
            if (!empty($existing->espocrm_contact_id)) {
                $this->espoCrm->updateContact($existing->espocrm_contact_id, $refreshed);
            } else {
                $contactId = $this->espoCrm->createContact($refreshed);
                if ($contactId) {
                    DB::table('customers')->where('customer_id', $existing->customer_id)->update(['espocrm_contact_id' => $contactId]);
                }
            }
        }

        return [
            'customer_id' => $existing->customer_id,
            'created'     => false,
            'changes'     => $changes,
            'needsReview' => $needsReview,
        ];
    }
}
