<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 3: Draft Supplier/Customer/Donor Master
// Creation.
//
// No live web lookups (confirmed with Chris before building): a name is
// either genuinely present in the bank line's own description text, or
// it isn't — this service never searches the internet or invents
// company details. Two jobs, kept deliberately separate in time:
//   1. computeSuggestedNames() runs once per document right after
//      classification (Phase 2), extracting a best-effort candidate
//      name from each line's raw description using only text already
//      on the statement. For a great many real Malaysian bank
//      statements this returns null for most lines — "Payment via
//      Cheque (INW AMB CHQ)" genuinely does not name a payee — and that
//      is the honest, correct answer, not a bug: Chris fills the name
//      in himself the first time, which is exactly what Phase 2's
//      learned-rule pattern is built to remember for next time.
//   2. resolveOrCreateDraft() runs only at commit time, using whatever
//      name is showing on the review screen (the suggestion, or Chris's
//      own typed correction) — never silently, always after Chris has
//      seen and can edit the name. An exact case-insensitive match
//      against an existing master links to it directly; anything else
//      creates a DRAFT record (is_draft = true) with just the name and
//      nothing fabricated, for Chris to complete later from the normal
//      Supplier/Customer/Donor master screens.
class PartyMatchingService
{
    private const CATEGORY_TABLES = [
        'SUPPLIER' => ['table' => 'cbe_suppliers', 'pk' => 'supplier_id', 'name_col' => 'supplier_name', 'match_field' => 'matched_supplier_id'],
        'CUSTOMER' => ['table' => 'cbe_customers', 'pk' => 'customer_id', 'name_col' => 'customer_name', 'match_field' => 'matched_customer_id'],
        'DONATION' => ['table' => 'cbe_donors', 'pk' => 'donor_id', 'name_col' => 'donor_name', 'match_field' => 'matched_donor_id'],
        // NEW 8 Sep 2026 (Task #397, Phase 6) — a fixed asset's vendor is,
        // in this app's data model, just another cbe_suppliers record
        // (cbe_fixed_assets.supplier_id is an FK to that same table) —
        // reuses the identical draft-or-link mechanism, no new table.
        'ASSET' => ['table' => 'cbe_suppliers', 'pk' => 'supplier_id', 'name_col' => 'supplier_name', 'match_field' => 'matched_supplier_id'],
    ];

    // Generic bank-statement boilerplate stripped before whatever text
    // remains is judged as a possible name. Deliberately conservative —
    // under-suggesting (leaving it blank for Chris to type) is safer
    // than over-suggesting a wrong or nonsensical "name".
    private const GENERIC_DESC_PHRASES = [
        'payment via cheque', 'inw amb chq', 'ctl outward clearing', 'local cheque deposit',
        'bank cheque processing fee', 'cheque processing fee', 'bank charge', 'service charge',
        'online transfer', 'instant transfer', 'fund transfer', 'interbank giro', 'duitnow',
        'transfer to', 'transfer from', 'payment to', 'payment from', 'received from', 'paid to',
        'salary payment', 'donation received', 'membership fee received', 'cheque deposit',
    ];

    /**
     * Fills in suggested_party_name for every not-yet-suggested line in
     * a document. Runs once, right after classification, independent of
     * whichever category ends up confirmed later — the name suggestion
     * itself doesn't depend on category, only the master-table lookup
     * at commit time does.
     */
    public function computeSuggestedNames(string $documentId): void
    {
        $lines = DB::table('cbe_ai_extracted_transactions')
            ->where('document_id', $documentId)
            ->whereNull('suggested_party_name')
            ->get();

        foreach ($lines as $line) {
            $candidate = $this->extractCandidateName($line->description);
            if ($candidate) {
                DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $line->extraction_id)
                    ->update(['suggested_party_name' => $candidate, 'updated_at' => now()]);
            }
        }
    }

    /**
     * Called at commit time for a line whose confirmed category is
     * SUPPLIER/CUSTOMER/DONATION and a party name is available (from
     * the suggestion or Chris's own edit). Returns the resolved
     * supplier/customer/donor id (existing match or newly created
     * draft), and records it on the extraction row. Returns null and
     * does nothing if the category doesn't need a party master or no
     * name is available — a bank transaction can still commit without
     * one; it just won't be linked to a master yet.
     */
    public function resolveOrCreateDraft(string $extractionId, ?string $category, ?string $partyName, string $nodeId, string $agentId): ?string
    {
        $partyName = $partyName ? trim($partyName) : null;
        if (! $category || ! $partyName || ! isset(self::CATEGORY_TABLES[$category])) {
            return null;
        }

        $meta = self::CATEGORY_TABLES[$category];

        $existingId = DB::table($meta['table'])
            ->where('cbe_node_id', $nodeId)
            ->whereRaw('LOWER('.$meta['name_col'].') = ?', [mb_strtolower($partyName)])
            ->value($meta['pk']);

        $resolvedId = $existingId ?: $this->createDraft($meta, $partyName, $nodeId, $agentId, $extractionId);

        DB::table('cbe_ai_extracted_transactions')->where('extraction_id', $extractionId)->update([
            $meta['match_field'] => $resolvedId, 'updated_at' => now(),
        ]);

        return $resolvedId;
    }

    // NEW 9 Sep 2026 (Task #397 follow-up, Phase 13) — per Chris: a bank
    // statement line with no identifiable payee/payer name must still
    // become a real Bill/Invoice ("sometime may not have debtor but you
    // can create an invoice as cash sales"). Rather than leaving the
    // party blank (which cbe_invoices.customer_id/cbe_purchase_bills.
    // supplier_id don't allow — both are NOT NULL), this gets or creates
    // ONE reusable draft placeholder party per node per category, shared
    // across every AI-committed line that genuinely has no name to go
    // on — never a real name, never fabricated, just an honest "this
    // was cash, no name given" marker Chris can review and merge later.
    //
    // EXTENDED 9 Sep 2026 (Phase 14): also covers DONATION — a real,
    // ordinary NGO/temple practice of issuing a receipt to "Anonymous
    // Donor" when a bank credit is clearly a donation but the statement
    // gives no name, rather than skipping the (mandatory) receipt.
    public function resolveOrCreateGenericParty(string $category, string $nodeId, string $agentId): ?string
    {
        if (! in_array($category, ['SUPPLIER', 'CUSTOMER', 'DONATION'], true)) {
            return null;
        }

        $meta = self::CATEGORY_TABLES[$category];
        $genericName = __('cbe_ai.generic_party_'.strtolower($category));

        $existingId = DB::table($meta['table'])
            ->where('cbe_node_id', $nodeId)
            ->whereRaw('LOWER('.$meta['name_col'].') = ?', [mb_strtolower($genericName)])
            ->value($meta['pk']);

        if ($existingId) {
            return $existingId;
        }

        $id = (string) Str::uuid();
        $row = [
            $meta['pk'] => $id,
            'cbe_node_id' => $nodeId,
            $meta['name_col'] => $genericName,
            'is_draft' => true,
            'created_by' => $agentId,
            'created_at' => now(), 'updated_at' => now(),
        ];

        if ($meta['table'] === 'cbe_suppliers') {
            $count = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->count();
            $row['supplier_code'] = sprintf('SUP-%04d', $count + 1);
        }

        DB::table($meta['table'])->insert($row);

        return $id;
    }

    private function createDraft(array $meta, string $partyName, string $nodeId, string $agentId, string $extractionId): string
    {
        $id = (string) Str::uuid();
        $row = [
            $meta['pk'] => $id,
            'cbe_node_id' => $nodeId,
            $meta['name_col'] => $partyName,
            'is_draft' => true,
            'created_from_extraction_id' => $extractionId,
            'created_by' => $agentId,
            'created_at' => now(), 'updated_at' => now(),
        ];

        if ($meta['table'] === 'cbe_suppliers') {
            $count = DB::table('cbe_suppliers')->where('cbe_node_id', $nodeId)->count();
            $row['supplier_code'] = sprintf('SUP-%04d', $count + 1);
        }

        DB::table($meta['table'])->insert($row);

        return $id;
    }

    private function extractCandidateName(?string $description): ?string
    {
        if (! $description) {
            return null;
        }

        $text = mb_strtolower($description);
        foreach (self::GENERIC_DESC_PHRASES as $phrase) {
            $text = str_replace($phrase, ' ', $text);
        }
        $text = preg_replace('/\([^)]*\)/', ' ', $text); // drop bracketed bank codes like (INW AMB CHQ)
        $text = preg_replace('/[^a-z\s]/', ' ', $text);  // drop digits/punctuation — never guess a name from a cheque number
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (mb_strlen($text) < 4) {
            return null; // nothing meaningful left — honestly blank, not a fabricated name
        }

        return mb_substr(ucwords($text), 0, 150);
    }
}
