<?php

namespace App\Services;

use App\Models\Agent;
use App\Services\HierarchyService;
use App\Services\PhoneNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class BatchRegistrationService
{
    // -------------------------------------------------------
    // Parses the uploaded CSV/XLSX (person data ONLY — no role/sponsor
    // columns, per the final design) and stages every row. batch_type
    // and owner_agent_id come from what Admin selected on the upload
    // screen itself, not from the file.
    // -------------------------------------------------------
    public function parseAndStage(string $fullPath, string $uploadedBy, string $batchType, ?string $ownerAgentId): string
    {
        $rows = $this->readFile($fullPath);
        if (empty($rows)) {
            throw new \Exception('File is empty or could not be read.');
        }

        $batchId = (string) Str::uuid();
        $filename = basename($fullPath);

        DB::table('batch_registration_staging')->insert([
            'batch_id'       => $batchId,
            'uploaded_by'    => $uploadedBy,
            'filename'       => $filename,
            'status'         => 'PENDING_VALIDATION',
            'batch_type'     => $batchType,
            'owner_agent_id' => $ownerAgentId,
            'total_rows'     => count($rows),
            'valid_rows'     => 0,
            'invalid_rows'   => 0,
            'committed_rows' => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $rowNumber = 1;
        foreach ($rows as $row) {
            DB::table('batch_registration_records')->insert([
                'record_id'    => (string) Str::uuid(),
                'batch_id'     => $batchId,
                'row_number'   => $rowNumber,
                'full_name'    => trim($row['full_name'] ?? ''),
                'second_name'  => trim($row['second_name'] ?? '') ?: null,
                'email'        => trim(strtolower($row['email'] ?? '')),
                'phone'        => trim($row['phone'] ?? ''),
                'address'      => trim($row['address'] ?? ''),
                'postcode'     => trim($row['postcode'] ?? ''),
                'city'         => trim($row['city'] ?? ''),
                'state'        => trim($row['state'] ?? ''),
                // NRIC and bank details are NEVER collected — removed
                // per confirmed legal/privacy requirement (06 Jul 2026).
                'sponsor_code' => null, // batch-level ownership now, not per-row
                'role'         => null, // batch-level, not per-row
                'status'       => 'PENDING',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $rowNumber++;
        }

        return $batchId;
    }

    private function readFile(string $fullPath): array
    {
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $rows = [];

        if ($ext === 'csv') {
            $handle = fopen($fullPath, 'r');
            $header = fgetcsv($handle);
            $header = array_map(fn ($h) => strtolower(trim($h)), $header);
            while (($line = fgetcsv($handle)) !== false) {
                $rows[] = array_combine($header, $line);
            }
            fclose($handle);
        } else {
            // XLSX/XLS via PhpSpreadsheet (already a Laravel/Composer
            // dependency in most setups via maatwebsite/excel or similar;
            // falls back to a clear error if not installed).
            if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                throw new \Exception('XLSX support requires phpoffice/phpspreadsheet — please upload a CSV instead, or ask to have this installed.');
            }
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $header = array_map(fn ($h) => strtolower(trim($h ?? '')), $sheet[0]);
            for ($i = 1; $i < count($sheet); $i++) {
                $rows[] = array_combine($header, $sheet[$i]);
            }
        }

        return $rows;
    }

    // -------------------------------------------------------
    // Validates every row. Batch-level ownership is checked ONCE here
    // too (not per-row) — the selected GL/TL must genuinely hold that
    // role. All-or-nothing: batch status only becomes VALIDATED if
    // every single row passes.
    // -------------------------------------------------------
    public function validateBatch(string $batchId): array
    {
        $batch = DB::table('batch_registration_staging')->where('batch_id', $batchId)->first();

        // Batch-level ownership check first.
        if ($batch->batch_type === 'TL_BATCH' || $batch->batch_type === 'INTRODUCER_BATCH') {
            $requiredRole = $batch->batch_type === 'TL_BATCH' ? 'GROUP_LEADER' : 'TEAM_LEADER';
            $owner = $batch->owner_agent_id ? Agent::where('agent_id', $batch->owner_agent_id)->where('is_deleted', false)->first() : null;
            if (!$owner || $owner->role !== $requiredRole || $owner->status !== 'ACTIVE') {
                DB::table('batch_registration_staging')->where('batch_id', $batchId)->update([
                    'status' => 'OWNER_INVALID',
                    'updated_at' => now(),
                ]);
                return ['pass' => 0, 'fail' => $batch->total_rows, 'error' => "Selected owner is not a valid active {$requiredRole}."];
            }
        }

        $records = DB::table('batch_registration_records')->where('batch_id', $batchId)->get();
        $passCount = 0;
        $failCount = 0;

        // Track email within THIS batch too, so duplicates inside the
        // same file are caught, not just against existing agents.
        $seenEmail = [];

        foreach ($records as $record) {
            $errors = [];

            if (empty($record->full_name) || !preg_match('/^[a-zA-Z\s\'\-\.]+$/', $record->full_name)) {
                $errors[] = 'Full Name must be letters and spaces only.';
            }
            // NRIC and bank details are NEVER collected — removed per
            // confirmed legal/privacy requirement (06 Jul 2026). Email
            // uniqueness (below) is the duplicate-detection mechanism now.
            // UPDATED 29 Jul 2026 — was mobile-only (01X-XXXXXXX); now uses
            // the same app-wide PhoneNumberService check as every other
            // form, so a landline office number also passes here.
            if (!PhoneNumberService::isValid($record->phone)) {
                $errors[] = 'Phone must be a valid Malaysian mobile or landline number (e.g. 012-3456789 or 03-22723932, with or without the +60 country code).';
            }
            if (!filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email is not a valid email address.';
            } elseif (isset($seenEmail[$record->email])) {
                $errors[] = 'Duplicate email within this same file (row ' . $seenEmail[$record->email] . ').';
            } else {
                $seenEmail[$record->email] = $record->row_number;
                if (Agent::where('email', $record->email)->where('is_deleted', false)->exists()) {
                    $errors[] = 'Email already exists in the system.';
                }
            }
            if (empty($record->address)) $errors[] = 'Address is required.';
            if (!preg_match('/^\d{5}$/', $record->postcode)) $errors[] = 'Postcode must be exactly 5 digits.';
            if (empty($record->city)) $errors[] = 'City is required.';
            if (empty($record->state)) $errors[] = 'State is required.';

            $status = empty($errors) ? 'VALID' : 'INVALID';
            if ($status === 'VALID') $passCount++; else $failCount++;

            DB::table('batch_registration_records')->where('record_id', $record->record_id)->update([
                'status'            => $status,
                'validation_errors' => empty($errors) ? null : json_encode($errors),
                'updated_at'        => now(),
            ]);
        }

        // All-or-nothing: only VALIDATED (ready to commit) if zero
        // failures remain.
        DB::table('batch_registration_staging')->where('batch_id', $batchId)->update([
            'valid_rows'   => $passCount,
            'invalid_rows' => $failCount,
            'status'       => $failCount === 0 ? 'VALIDATED' : 'VALIDATION_FAILED',
            'updated_at'   => now(),
        ]);

        return ['pass' => $passCount, 'fail' => $failCount];
    }

    // -------------------------------------------------------
    // Re-validates a single row after Admin fixes it (the "Fix Row"
    // flow) — same field checks as validateBatch(), just for one row,
    // then re-checks whether the WHOLE batch can now be VALIDATED.
    // -------------------------------------------------------
    public function updateRecord(string $recordId, array $data): array
    {
        DB::table('batch_registration_records')->where('record_id', $recordId)->update(array_merge($data, ['updated_at' => now()]));

        $record = DB::table('batch_registration_records')->where('record_id', $recordId)->first();
        $this->validateBatch($record->batch_id); // re-run full batch validation to keep counts accurate

        $updated = DB::table('batch_registration_records')->where('record_id', $recordId)->first();
        return $updated->status === 'INVALID' ? json_decode($updated->validation_errors, true) : [];
    }

    // -------------------------------------------------------
    // COMMIT — only ever called once status === VALIDATED (enforced by
    // the controller). Creates real agent accounts for every row,
    // exactly matching normal one-by-one registration: INACTIVE status,
    // real agent_code via HierarchyService, QR token, email
    // verification sent — nothing skipped or bypassed for batch imports.
    // -------------------------------------------------------
    public function commitBatch(string $batchId, string $performedBy, ?string $groupLabelId = null): array
    {
        $batch = DB::table('batch_registration_staging')->where('batch_id', $batchId)->first();
        $records = DB::table('batch_registration_records')->where('batch_id', $batchId)->where('status', 'VALID')->get();

        $owner = $batch->owner_agent_id ? Agent::find($batch->owner_agent_id) : null;
        $committed = 0;
        $report = [];

        foreach ($records as $record) {
            $agentId = (string) Str::uuid();
            $qrToken = Str::random(10);
            $verifyToken = Str::random(64);

            if ($batch->batch_type === 'GL_BATCH') {
                // Independent new GL — own new group, no parent. No
                // established root-level method exists in the real
                // HierarchyService for this case, so this simple
                // "highest existing root code + 1" approach is used.
                $groupId = (string) Str::uuid();
                DB::table('groups')->insert([
                    'group_id'           => $groupId,
                    'group_name'         => $record->full_name . "'s Group",
                    'group_code'         => 'G-' . strtoupper(Str::random(6)),
                    'root_member_suffix' => '0',
                    'is_active'          => true,
                    'created_by'         => $performedBy,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
                $agentCode = $this->nextRootAgentCode();
                $parentId = null;
            } else {
                // TL or Introducer under a real, already-verified owner
                // — uses the REAL HierarchyService code generator, with
                // its locking + retry logic, exactly like normal
                // one-by-one registration.
                $groupId = $owner->group_id;
                $agentCode = app(HierarchyService::class)->generateAgentCode($owner);
                $parentId = $owner->agent_id;
            }

            $agentRole = $batch->batch_type === 'GL_BATCH' ? 'GROUP_LEADER' : ($batch->batch_type === 'TL_BATCH' ? 'TEAM_LEADER' : 'INTRODUCER');

            // FIXED 23 Jul 2026 — this insert never set hierarchy_path,
            // leaving every batch-registered TL/Introducer stuck on the
            // schema default '/' even though parent_id was correct.
            // Same root cause and same fix as SpecialGroupController's
            // createAgent(): compute it here instead of leaving it unset.
            $hierarchyPath = $parentId
                ? (Agent::find($parentId)->hierarchy_path ?? '/') . $agentId . '/'
                : '/' . $agentId . '/';

            DB::table('agents')->insert([
                'agent_id'                  => $agentId,
                'full_name'                 => $record->full_name,
                'second_name'               => $record->second_name ?? null,
                'email'                     => $record->email,
                'phone'                     => PhoneNumberService::normalize($record->phone),
                // NRIC and bank details are NEVER collected — removed
                // per confirmed legal/privacy requirement (06 Jul 2026).
                'role'                      => $agentRole,
                'agent_code'                => $agentCode,
                'parent_id'                 => $parentId,
                'group_id'                  => $groupId,
                'group_label_id'            => $batch->batch_type === 'GL_BATCH' ? $groupLabelId : ($owner->group_label_id ?? null),
                'hierarchy_path'            => $hierarchyPath,
                'status'                    => 'INACTIVE', // becomes ACTIVE only after their own verification + password
                'qr_code_token'             => $qrToken,
                'email_verification_token'  => $verifyToken,
                'created_by'                => $performedBy,
                'created_at'                => now(),
                'updated_at'                => now(),
            ]);

            DB::table('agent_profiles')->insert([
                'profile_id' => (string) Str::uuid(),
                'agent_id'   => $agentId,
                'address'    => $record->address,
                'postcode'   => $record->postcode,
                'city'       => $record->city,
                'state'      => $record->state,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Same styled verification email used everywhere else in
            // the system, per confirmed decision — consistent branding
            // and Registered By/Date-Time on every registration path.
            $verifyLink = url('/verify-email/' . $verifyToken);
            $roleLabel = ucwords(strtolower(str_replace('_', ' ', $agentRole)));
            $logoUrl = url('/images/generallink-logo.jpeg');
            $createdByAgent = \App\Models\Agent::find($performedBy);
            $createdByName = $createdByAgent->full_name ?? 'GeneralLink Admin';
            $createdAt = now()->format('d M Y, h:i A');

            $html = <<<HTML
<div style="font-family:'Segoe UI',Arial,sans-serif; max-width:520px; margin:0 auto; background:#f4f9fb;">
    <div style="background:linear-gradient(135deg,#1565C0,#1B9AE4); padding:24px; text-align:center; border-radius:10px 10px 0 0;">
        <img src="{$logoUrl}" alt="GeneralLink" style="max-height:56px; border-radius:8px; margin-bottom:8px;">
        <div style="color:#fff; font-size:18px; font-weight:700;">Welcome to GeneralLink!</div>
    </div>
    <div style="background:#fff; padding:28px 24px; border-radius:0 0 10px 10px;">
        <p style="font-size:14px; color:#1a1a1a; margin:0 0 12px;">Dear {$record->full_name},</p>
        <p style="font-size:13px; color:#374151; line-height:1.6; margin:0 0 14px;">
            Thank you for joining GeneralLink Digital Affiliate Ecosystem as a <strong>{$roleLabel}</strong>.
            We're delighted to have you on board. Please verify your email address below to activate your account.
        </p>
        <div style="background:#f0f9ff; border-radius:6px; padding:10px 14px; font-size:11.5px; color:#374151; margin-bottom:18px;">
            <strong>Registered By:</strong> {$createdByName}<br>
            <strong>Date/Time:</strong> {$createdAt}
        </div>
        <div style="text-align:center; margin:24px 0;">
            <a href="{$verifyLink}" style="background:#1565C0; color:#fff; text-decoration:none; padding:12px 32px; border-radius:8px; font-size:14px; font-weight:600; display:inline-block;">Verify My Email Address</a>
        </div>
        <div style="background:#f0f9ff; border-radius:6px; padding:10px 14px; font-size:11.5px; color:#6b7280; margin-top:20px;">
            If the button doesn't work, copy and paste this link into your browser:<br>
            <span style="color:#1565C0; word-break:break-all;">{$verifyLink}</span>
        </div>
        <p style="font-size:11px; color:#92400e; background:#fff8e1; border-radius:6px; padding:8px 12px; margin-top:14px;">
            🔒 Security Notice: this link expires in 24 hours. If you did not expect this email, please disregard it.
        </p>
        <p style="font-size:13px; color:#374151; margin-top:24px;">
            Kind Regards,<br>
            <strong>GeneralLink Admin Director</strong>
        </p>
    </div>
    <div style="text-align:center; padding:14px; font-size:10px; color:#9ca3af; letter-spacing:.05em;">
        AI-POWERED &middot; MALAYSIA &middot; SOUTHEAST ASIA
    </div>
</div>
HTML;

            Mail::html($html, function ($mail) use ($record) {
                $mail->to($record->email)->subject('Welcome to GeneralLink! Please Verify Your Email');
            });

            DB::table('batch_registration_records')->where('record_id', $record->record_id)->update([
                'committed_agent_id' => $agentId,
                'status'             => 'COMMITTED',
                'updated_at'         => now(),
            ]);

            $report[] = ['full_name' => $record->full_name, 'agent_code' => $agentCode];
            $committed++;
        }

        DB::table('batch_registration_staging')->where('batch_id', $batchId)->update([
            'status'         => 'COMMITTED',
            'committed_rows' => $committed,
            'updated_at'     => now(),
        ]);

        return ['committed' => $committed, 'report' => $report];
    }

    private function nextRootAgentCode(): string
    {
        $max = Agent::whereNull('parent_id')->where('is_deleted', false)
            ->get()->map(fn ($a) => (int) $a->agent_code)->filter()->max();
        return (string) (($max ?? 0) + 1);
    }

    public function purgeBatch(string $batchId): void
    {
        DB::table('batch_registration_records')->where('batch_id', $batchId)->delete();
        DB::table('batch_registration_staging')->where('batch_id', $batchId)->delete();
    }
}
