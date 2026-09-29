<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// NEW 9 Aug 2026 — SSM Document Registration & Verification Module,
// sections 8-10 of Chris's spec ("AI Document Verification" / "Missing
// Document Detection" / "AI Document Validation"). Runs a real,
// honestly-scoped automatic first-pass check on EVERY document a vendor
// uploads at registration: reads the document via Claude, extracts
// whatever company/business name is printed on it, and compares that to
// the vendor_name typed at registration.
//
// Scope, stated plainly (same honesty standard as VendorDueDiligenceService):
// this DOES catch a blank/corrupted/unreadable upload and a document that
// plainly belongs to a different company. It does NOT detect forgery or
// alteration, and does NOT classify a document against its claimed SSM
// form number (e.g. it can't confirm "this really is a Form 9 and not a
// Form 24"), and does NOT cross-check every document against every other
// document pairwise. Admin's own human verification_status remains the
// real approval gate (see VendorLoginApprovalController::verifyDocument);
// ai_check_status is only ever a first-pass signal shown alongside it.
class VendorDocumentVerificationService
{
    private const NAME_MATCH_THRESHOLD = 70;

    public function __construct(private ClaudeDocumentExtractionService $extractor)
    {
    }

    /** Runs the check on every document currently on file for this vendor. Never throws — callers should still wrap in try/catch for safety, matching the Due Diligence pattern. */
    public function checkAll(Vendor $vendor): void
    {
        $docs = DB::table('vendor_documents')->where('vendor_id', $vendor->vendor_id)->get();
        foreach ($docs as $doc) {
            try {
                $this->checkOne($vendor, $doc);
            } catch (\Throwable $e) {
                Log::warning('VendorDocumentVerificationService: check failed for document ' . $doc->vendor_document_id . ': ' . $e->getMessage());
                $this->update($doc->vendor_document_id, 'UNREADABLE', 'Automatic check could not run — Manual Review Required.');
            }
        }
    }

    private function checkOne(Vendor $vendor, object $doc): void
    {
        if (!Storage::disk('local')->exists($doc->file_path)) {
            $this->update($doc->vendor_document_id, 'UNREADABLE', 'File could not be found on disk — please ask the vendor to re-upload.');
            return;
        }

        $ext = strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => null,
        };
        if (!$mime) {
            $this->update($doc->vendor_document_id, 'UNREADABLE', 'File format not supported for automatic reading — Manual Review Required.');
            return;
        }

        $extraction = $this->extractor->extract(Storage::disk('local')->path($doc->file_path), $mime, [
            'entity_name' => 'The exact registered company or business name printed on this document',
        ]);

        if ($extraction['status'] !== 'OK') {
            $this->update($doc->vendor_document_id, 'UNREADABLE', 'Could not read this document automatically (' . ($extraction['message'] ?? 'unknown error') . ') — Manual Review Required.');
            return;
        }

        $docName = trim((string) ($extraction['values']['entity_name'] ?? ''));
        if ($docName === '' || stripos($docName, 'UNSURE:') === 0) {
            $this->update($doc->vendor_document_id, 'UNREADABLE', 'Could not clearly find a company/business name on this document — Manual Review Required.');
            return;
        }

        similar_text(strtoupper($vendor->vendor_name), strtoupper($docName), $percent);
        $score = (int) round($percent);

        if ($score >= self::NAME_MATCH_THRESHOLD) {
            $this->update($doc->vendor_document_id, 'VERIFIED', "Document reads \"{$docName}\" — {$score}% match to the registered vendor name.");
        } else {
            $this->update($doc->vendor_document_id, 'FAILED', "Document reads \"{$docName}\" — only {$score}% match to the registered vendor name \"{$vendor->vendor_name}\". Please check this document carefully.");
        }
    }

    private function update(string $vendorDocumentId, string $status, string $note): void
    {
        DB::table('vendor_documents')->where('vendor_document_id', $vendorDocumentId)->update([
            'ai_check_status' => $status,
            'ai_check_note'   => $note,
            'updated_at'      => now(),
        ]);
    }
}
