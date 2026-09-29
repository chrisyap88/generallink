<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
// Phase 2: CSV/Excel import for Bank Transaction Entry (spec section
// 2.2). Mirrors the parse -> stage -> preview -> commit pattern already
// used by BatchRegistrationService (Admin batch agent registration),
// including reusing the same PhpSpreadsheet-based readFile() approach
// for XLSX/XLS, so beginners see the same upload/preview screen shape
// they already have elsewhere in the app.
//
// Required columns (case-insensitive header row): date, description,
// debit OR credit (one populated, not both), reference (optional),
// cheque_no (optional), bank_reference (optional — used for duplicate
// detection against transactions already saved for this bank account).
class BankTransactionImportService
{
    public function parseAndStage(string $fullPath, string $filename, string $cbeNodeId, string $bankAccountId, string $uploadedBy): string
    {
        $rows = $this->readFile($fullPath);
        if (empty($rows)) {
            throw new \Exception('File is empty or could not be read.');
        }

        $batchId = (string) Str::uuid();
        DB::table('cbe_bank_transaction_import_batches')->insert([
            'batch_id'        => $batchId,
            'cbe_node_id'     => $cbeNodeId,
            'bank_account_id' => $bankAccountId,
            'filename'        => $filename,
            'status'          => 'PREVIEW',
            'total_rows'      => count($rows),
            'valid_rows'      => 0,
            'invalid_rows'    => 0,
            'duplicate_rows'  => 0,
            'committed_rows'  => 0,
            'uploaded_by'     => $uploadedBy,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $validCount = 0;
        $invalidCount = 0;
        $duplicateCount = 0;
        $rowNumber = 1;

        foreach ($rows as $row) {
            $dateRaw = trim((string) ($row['date'] ?? $row['transaction_date'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $referenceNo = trim((string) ($row['reference'] ?? $row['reference_no'] ?? '')) ?: null;
            $chequeNo = trim((string) ($row['cheque_no'] ?? $row['cheque'] ?? '')) ?: null;
            $bankReference = trim((string) ($row['bank_reference'] ?? '')) ?: null;
            $debitRaw = trim((string) ($row['debit'] ?? ''));
            $creditRaw = trim((string) ($row['credit'] ?? ''));

            $parsedDate = $this->parseDate($dateRaw);
            $debit = $this->parseAmount($debitRaw);
            $credit = $this->parseAmount($creditRaw);

            $status = 'VALID';
            $error = null;

            if (! $parsedDate) {
                $status = 'INVALID';
                $error = "Could not read the date \"{$dateRaw}\" — use DD/MM/YYYY or YYYY-MM-DD.";
            } elseif ($description === '') {
                $status = 'INVALID';
                $error = 'Description is required.';
            } elseif (($debit === null && $credit === null) || ($debit !== null && $credit !== null && $debit > 0 && $credit > 0)) {
                $status = 'INVALID';
                $error = 'Fill in either Debit or Credit for this row, not both and not neither.';
            } elseif (($debit ?? 0) < 0 || ($credit ?? 0) < 0) {
                $status = 'INVALID';
                $error = 'Amounts cannot be negative — use the Debit or Credit column instead.';
            }

            if ($status === 'VALID' && $parsedDate) {
                $amount = (float) ($credit ?? 0) - (float) ($debit ?? 0);
                $duplicate = DB::table('cbe_bank_transactions')
                    ->where('bank_account_id', $bankAccountId)
                    ->where('transaction_date', $parsedDate)
                    ->where('amount', $amount)
                    ->when($bankReference, fn ($q) => $q->where('bank_reference', $bankReference))
                    ->exists();
                if ($duplicate) {
                    $status = 'DUPLICATE';
                    $error = 'A bank transaction with the same date, amount'.($bankReference ? ' and bank reference' : '').' already exists.';
                }
            }

            DB::table('cbe_bank_transaction_import_rows')->insert([
                'row_id'               => (string) Str::uuid(),
                'batch_id'             => $batchId,
                'row_number'           => $rowNumber,
                'transaction_date_raw' => $dateRaw ?: null,
                'transaction_date'     => $parsedDate,
                'description'          => $description ?: null,
                'reference_no'         => $referenceNo,
                'cheque_no'            => $chequeNo,
                'debit'                => $debit,
                'credit'               => $credit,
                'bank_reference'       => $bankReference,
                'status'               => $status,
                'error_message'        => $error,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            if ($status === 'VALID') { $validCount++; }
            elseif ($status === 'DUPLICATE') { $duplicateCount++; }
            else { $invalidCount++; }
            $rowNumber++;
        }

        DB::table('cbe_bank_transaction_import_batches')->where('batch_id', $batchId)->update([
            'valid_rows'     => $validCount,
            'invalid_rows'   => $invalidCount,
            'duplicate_rows' => $duplicateCount,
            'updated_at'     => now(),
        ]);

        return $batchId;
    }

    // Commits every VALID row into cbe_bank_transactions. DUPLICATE and
    // INVALID rows are left in the batch (visible on the preview screen)
    // but never committed — the officer can re-upload a corrected file
    // for those instead of the whole batch.
    public function commit(string $batchId, string $bankAccountId, string $cbeNodeId, ?string $transactionTypeId, string $committedBy): int
    {
        $rows = DB::table('cbe_bank_transaction_import_rows')
            ->where('batch_id', $batchId)->where('status', 'VALID')
            ->whereNull('committed_transaction_id')
            ->get();

        $committed = 0;
        foreach ($rows as $row) {
            $transactionId = (string) Str::uuid();
            $amount = (float) ($row->credit ?? 0) - (float) ($row->debit ?? 0);

            DB::table('cbe_bank_transactions')->insert([
                'transaction_id'      => $transactionId,
                'cbe_node_id'         => $cbeNodeId,
                'bank_account_id'     => $bankAccountId,
                'transaction_date'    => $row->transaction_date,
                'value_date'          => null,
                'transaction_type_id' => $transactionTypeId,
                'description'         => $row->description,
                'reference_no'        => $row->reference_no,
                'cheque_no'           => $row->cheque_no,
                'amount'              => $amount,
                'bank_reference'      => $row->bank_reference,
                'source'              => 'IMPORT',
                'import_batch_id'     => $batchId,
                'status'              => 'UNRECONCILED',
                'created_by'          => $committedBy,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            DB::table('cbe_bank_transaction_import_rows')->where('row_id', $row->row_id)
                ->update(['committed_transaction_id' => $transactionId, 'updated_at' => now()]);

            $committed++;
        }

        DB::table('cbe_bank_transaction_import_batches')->where('batch_id', $batchId)->update([
            'status'         => 'COMMITTED',
            'committed_rows' => $committed,
            'updated_at'     => now(),
        ]);

        return $committed;
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') { return null; }

        // Excel serial date (numeric cell read as a number, e.g. 45678).
        if (is_numeric($raw)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y', 'm/d/Y'] as $format) {
            $d = \DateTime::createFromFormat($format, $raw);
            if ($d && $d->format($format) === $raw) {
                return $d->format('Y-m-d');
            }
        }
        // Last resort — let PHP guess (catches ISO datetimes, etc.).
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function parseAmount(string $raw): ?float
    {
        $raw = trim(str_replace([',', 'RM', 'rm'], '', $raw));
        if ($raw === '') { return null; }
        return is_numeric($raw) ? round((float) $raw, 2) : null;
    }

    private function readFile(string $fullPath): array
    {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $rows = [];

        if ($ext === 'csv') {
            $handle = fopen($fullPath, 'r');
            $header = fgetcsv($handle);
            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
            while (($line = fgetcsv($handle)) !== false) {
                $rows[] = array_combine($header, array_pad($line, count($header), null));
            }
            fclose($handle);
        } else {
            if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                throw new \Exception('XLSX support requires phpoffice/phpspreadsheet — please upload a CSV instead.');
            }
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $header = array_map(fn ($h) => strtolower(trim((string) ($h ?? ''))), $sheet[0]);
            for ($i = 1; $i < count($sheet); $i++) {
                $rows[] = array_combine($header, array_pad($sheet[$i], count($header), null));
            }
        }

        return $rows;
    }
}
