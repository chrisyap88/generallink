<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BatchRegistrationService
{
    // -------------------------------------------------------
    // STEP 1: Parse uploaded Excel/CSV file into staging table
    // Returns batch_id
    // -------------------------------------------------------
    public function parseAndStage(string $filePath, string $uploadedBy): string
    {
        $batchId = Str::uuid()->toString();
        $rows    = $this->readFile($filePath);

        DB::table('batch_registration_staging')->insert([
            'batch_id'    => $batchId,
            'uploaded_by' => $uploadedBy,
            'filename'    => basename($filePath),
            'status'      => 'PENDING',
            'total_rows'  => count($rows),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        foreach ($rows as $idx => $row) {
            DB::table('batch_registration_records')->insert([
                'record_id'      => Str::uuid()->toString(),
                'batch_id'       => $batchId,
                'row_number'     => $idx + 2, // +2 because row 1 is header
                'full_name'      => trim($row['full_name']       ?? ''),
                'email'          => strtolower(trim($row['email'] ?? '')),
                'nric'           => preg_replace('/[^0-9]/', '', $row['nric'] ?? ''),
                'phone'          => trim($row['phone']           ?? ''),
                'bank_name'      => trim($row['bank_name']       ?? ''),
                'bank_account'   => trim($row['bank_account']    ?? ''),
                'sponsor_code'   => trim($row['sponsor_code']    ?? ''),
                'role'           => strtoupper(trim($row['role'] ?? 'INTRODUCER')),
                'status'         => 'PENDING',
                'validation_errors' => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        return $batchId;
    }

    // -------------------------------------------------------
    // STEP 2: Validate all records in the batch
    // -------------------------------------------------------
    public function validateBatch(string $batchId): array
    {
        $records = DB::table('batch_registration_records')
                     ->where('batch_id', $batchId)
                     ->get();

        $passCount = 0;
        $failCount = 0;

        foreach ($records as $record) {
            $errors = $this->validateRecord($record, $batchId);

            if (empty($errors)) {
                DB::table('batch_registration_records')
                  ->where('record_id', $record->record_id)
                  ->update(['status' => 'VALID', 'validation_errors' => null, 'updated_at' => now()]);
                $passCount++;
            } else {
                DB::table('batch_registration_records')
                  ->where('record_id', $record->record_id)
                  ->update([
                      'status'            => 'INVALID',
                      'validation_errors' => json_encode($errors),
                      'updated_at'        => now(),
                  ]);
                $failCount++;
            }
        }

        DB::table('batch_registration_staging')
          ->where('batch_id', $batchId)
          ->update([
              'status'         => 'VALIDATED',
              'valid_rows'     => $passCount,
              'invalid_rows'   => $failCount,
              'updated_at'     => now(),
          ]);

        return ['pass' => $passCount, 'fail' => $failCount];
    }

    private function validateRecord(object $record, string $batchId): array
    {
        $errors = [];

        // Full name
        if (empty($record->full_name)) $errors[] = 'Full name is required.';
        elseif (strlen($record->full_name) > 200) $errors[] = 'Full name too long (max 200 chars).';

        // Email
        if (empty($record->email)) {
            $errors[] = 'Email is required.';
        } elseif (! filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } elseif (Agent::where('email', $record->email)->exists()) {
            $errors[] = 'Email already registered in the system.';
        } else {
            // Check for duplicates within this batch
            $dupInBatch = DB::table('batch_registration_records')
                ->where('batch_id', $batchId)
                ->where('record_id', '!=', $record->record_id)
                ->where('email', $record->email)
                ->exists();
            if ($dupInBatch) $errors[] = 'Duplicate email found in this batch.';
        }

        // NRIC — Malaysia: 12 digits
        if (empty($record->nric)) {
            $errors[] = 'NRIC is required.';
        } elseif (! preg_match('/^\d{12}$/', $record->nric)) {
            $errors[] = 'NRIC must be exactly 12 digits (no dashes).';
        }

        // Phone — Malaysia format
        if (empty($record->phone)) {
            $errors[] = 'Phone number is required.';
        } elseif (! preg_match('/^(\+?6?01)[0-9]{8,9}$/', preg_replace('/[\s\-]/', '', $record->phone))) {
            $errors[] = 'Invalid Malaysian phone number format.';
        }

        // Sponsor code — must exist
        if (empty($record->sponsor_code)) {
            $errors[] = 'Sponsor code is required (member_code or agent_code of sponsor).';
        } else {
            $sponsor = Agent::where('member_code', $record->sponsor_code)
                            ->orWhere('agent_code', $record->sponsor_code)
                            ->where('is_deleted', false)
                            ->first();
            if (! $sponsor) {
                $errors[] = "Sponsor code '{$record->sponsor_code}' not found.";
            } elseif (! $sponsor->isActive()) {
                $errors[] = "Sponsor '{$record->sponsor_code}' is not active.";
            } else {
                // Check tier restriction
                $hierarchyService = app(\App\Services\HierarchyService::class);
                if (! $hierarchyService->canRecruit($sponsor)) {
                    $errors[] = "Sponsor '{$record->sponsor_code}' has reached the maximum recruitment tier.";
                }
            }
        }

        // Role
        if (! in_array($record->role, ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])) {
            $errors[] = "Invalid role '{$record->role}'. Must be INTRODUCER, TEAM_LEADER, or GROUP_LEADER.";
        }

        return $errors;
    }

    // -------------------------------------------------------
    // STEP 3: Commit valid records — create agent accounts
    // -------------------------------------------------------
    public function commitBatch(string $batchId, string $committedBy): array
    {
        $validRecords = DB::table('batch_registration_records')
                         ->where('batch_id', $batchId)
                         ->where('status', 'VALID')
                         ->get();

        $committed = 0;
        $hierarchyService = app(\App\Services\HierarchyService::class);

        foreach ($validRecords as $record) {
            try {
                DB::transaction(function () use ($record, $hierarchyService, $committedBy, &$committed) {

                    $sponsor = Agent::where('member_code', $record->sponsor_code)
                                    ->orWhere('agent_code', $record->sponsor_code)
                                    ->where('is_deleted', false)
                                    ->first();

                    $verificationToken = Str::random(64);

                    $agent = $hierarchyService->registerUnderSponsor($sponsor, [
                        'full_name'                => $record->full_name,
                        'email'                    => $record->email,
                        'password_hash'            => Hash::make(Str::random(16)), // temp — user sets via email
                        'nric_encrypted'           => encrypt($record->nric),
                        'phone'                    => $record->phone,
                        'bank_name'                => $record->bank_name ?: null,
                        'bank_account_encrypted'   => $record->bank_account ? encrypt($record->bank_account) : null,
                        'qr_code_token'            => Str::random(40),
                        'email_verification_token' => $verificationToken,
                        'status'                   => 'ACTIVE',
                        'security_phrase_set'      => false,
                    ], $committedBy);

                    DB::table('batch_registration_records')
                      ->where('record_id', $record->record_id)
                      ->update([
                          'status'          => 'COMMITTED',
                          'committed_agent_id' => $agent->agent_id,
                          'updated_at'      => now(),
                      ]);

                    $committed++;
                });
            } catch (\Exception $e) {
                DB::table('batch_registration_records')
                  ->where('record_id', $record->record_id)
                  ->update([
                      'status'            => 'INVALID',
                      'validation_errors' => json_encode(['Commit error: ' . $e->getMessage()]),
                      'updated_at'        => now(),
                  ]);
            }
        }

        DB::table('batch_registration_staging')
          ->where('batch_id', $batchId)
          ->update(['status' => 'COMMITTED', 'committed_rows' => $committed, 'updated_at' => now()]);

        return ['committed' => $committed];
    }

    // -------------------------------------------------------
    // STEP 4: Purge a batch (removes staging records only, not committed agents)
    // -------------------------------------------------------
    public function purgeBatch(string $batchId): void
    {
        DB::table('batch_registration_records')->where('batch_id', $batchId)->delete();
        DB::table('batch_registration_staging')->where('batch_id', $batchId)->delete();
    }

    // -------------------------------------------------------
    // Update a single record and re-validate it
    // -------------------------------------------------------
    public function updateRecord(string $recordId, array $data): array
    {
        DB::table('batch_registration_records')
          ->where('record_id', $recordId)
          ->update(array_merge($data, ['status' => 'PENDING', 'validation_errors' => null, 'updated_at' => now()]));

        $record = DB::table('batch_registration_records')->where('record_id', $recordId)->first();
        $errors = $this->validateRecord($record, $record->batch_id);

        DB::table('batch_registration_records')
          ->where('record_id', $recordId)
          ->update([
              'status'            => empty($errors) ? 'VALID' : 'INVALID',
              'validation_errors' => empty($errors) ? null : json_encode($errors),
              'updated_at'        => now(),
          ]);

        return $errors;
    }

    // -------------------------------------------------------
    // Read uploaded file — supports CSV and XLSX
    // -------------------------------------------------------
    private function readFile(string $path): array
    {
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rows = [];

        if ($ext === 'csv') {
            $handle = fopen($path, 'r');
            $header = fgetcsv($handle);
            $header = array_map('trim', $header);
            while (($line = fgetcsv($handle)) !== false) {
                if (count($line) === count($header)) {
                    $rows[] = array_combine($header, $line);
                }
            }
            fclose($handle);
        } elseif (in_array($ext, ['xlsx', 'xls'])) {
            // Use PhpSpreadsheet (already available via Laravel)
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $sheet       = $spreadsheet->getActiveSheet();
            $data        = $sheet->toArray(null, true, true, false);
            $header      = array_map('trim', $data[0]);
            for ($i = 1; $i < count($data); $i++) {
                if (array_filter($data[$i])) { // skip empty rows
                    $rows[] = array_combine($header, array_map('trim', $data[$i]));
                }
            }
        }

        return $rows;
    }
}
