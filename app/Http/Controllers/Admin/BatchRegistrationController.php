<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BatchRegistrationService;
use App\Services\PhoneNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BatchRegistrationController extends Controller
{
    public function __construct(private BatchRegistrationService $service) {}

    // -------------------------------------------------------
    // Main batch upload page — lists all batches
    // -------------------------------------------------------
    public function index()
    {
        $batches = DB::table('batch_registration_staging as b')
            ->join('agents as a', 'a.agent_id', '=', 'b.uploaded_by')
            ->select('b.*', 'a.full_name as uploader_name')
            ->orderByDesc('b.created_at')
            ->paginate(15);

        return view('batch.index', compact('batches'));
    }

    // -------------------------------------------------------
    // Upload new file and stage records
    // -------------------------------------------------------
    public function upload(Request $request)
    {
        $request->validate([
            'batch_file'     => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
            'batch_type'     => ['required', 'in:GL_BATCH,TL_BATCH,INTRODUCER_BATCH'],
            'owner_agent_id' => ['required_unless:batch_type,GL_BATCH', 'nullable', 'string'],
        ]);

        // Confirm the selected owner genuinely holds the required role
        // BEFORE even staging the file — fail fast with a clear reason.
        if ($request->batch_type !== 'GL_BATCH') {
            $requiredRole = $request->batch_type === 'TL_BATCH' ? 'GROUP_LEADER' : 'TEAM_LEADER';
            $owner = \App\Models\Agent::where('agent_id', $request->owner_agent_id)->where('is_deleted', false)->first();
            if (!$owner || $owner->role !== $requiredRole || $owner->status !== 'ACTIVE') {
                return back()->withErrors(['owner_agent_id' => "Selected owner is not a valid, active {$requiredRole}."]);
            }
        }

        $file     = $request->file('batch_file');
        $path     = $file->store('batch_uploads', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $batchId = $this->service->parseAndStage($fullPath, Auth::guard('agent')->id(), $request->batch_type, $request->owner_agent_id);
            return redirect()->route('admin.batch.show', $batchId)
                             ->with('success', 'File uploaded. Review records below before validating.');
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return back()->with('error', 'Failed to parse file: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // Show a specific batch — staging records + status
    // -------------------------------------------------------
    public function show(string $batchId)
    {
        $batch = DB::table('batch_registration_staging')->where('batch_id', $batchId)->firstOrFail();

        $records = DB::table('batch_registration_records')
                     ->where('batch_id', $batchId)
                     ->orderBy('row_number')
                     ->paginate(50);

        return view('batch.show', compact('batch', 'records'));
    }

    // -------------------------------------------------------
    // Validate all records in the batch
    // -------------------------------------------------------
    public function validate(string $batchId)
    {
        $result = $this->service->validateBatch($batchId);
        return redirect()->route('admin.batch.show', $batchId)
                         ->with('success', "Validation complete: {$result['pass']} valid, {$result['fail']} failed.");
    }

    // -------------------------------------------------------
    // Commit valid records — create agent accounts
    // -------------------------------------------------------
    public function commit(Request $request, string $batchId)
    {
        $batch = DB::table('batch_registration_staging')->where('batch_id', $batchId)->first();

        if ($batch->status !== 'VALIDATED') {
            return back()->with('error', 'Please validate the batch before committing.');
        }

        if ($batch->batch_type === 'GL_BATCH') {
            $request->validate(['group_label_id' => ['nullable', 'exists:group_labels,group_label_id']]);
        }

        $result = $this->service->commitBatch($batchId, Auth::guard('agent')->id(), $request->group_label_id ?? null);

        return redirect()->route('admin.batch.show', $batchId)
                         ->with('success', "{$result['committed']} agents registered successfully.");
    }

    // -------------------------------------------------------
    // Owner lookup for the upload screen — reuses the SAME live
    // wildcard search already used by registration, filtered to the
    // required role for the selected batch type.
    // -------------------------------------------------------
    public function ownerLookup(Request $request)
    {
        $q = trim($request->get('q', ''));
        $role = $request->get('role'); // GROUP_LEADER or TEAM_LEADER
        if (strlen($q) < 2 || !in_array($role, ['GROUP_LEADER', 'TEAM_LEADER'])) {
            return response()->json([]);
        }

        $results = \App\Models\Agent::where('role', $role)
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->where(function ($query) use ($q) {
                $query->where('full_name', 'like', '%' . $q . '%')
                      ->orWhere('agent_code', 'like', '%' . $q . '%');
            })
            ->orderBy('full_name')
            ->limit(15)
            ->get(['agent_id', 'full_name', 'agent_code']);

        return response()->json($results);
    }

    // -------------------------------------------------------
    // Edit a single staging record then re-validate it
    // -------------------------------------------------------
    public function updateRecord(Request $request, string $recordId)
    {
        $request->validate([
            'full_name'   => ['required', 'string', 'max:200'],
            'second_name' => ['nullable', 'string', 'max:200'],
            'email'       => ['required', 'email', 'max:200'],
            'phone'       => ['required', 'string', PhoneNumberService::rule()],
            'address'     => ['required', 'string'],
            'postcode'    => ['required', 'string', 'max:10'],
            'city'        => ['required', 'string', 'max:100'],
            'state'       => ['required', 'string', 'max:100'],
        ]);

        $request->merge(['phone' => PhoneNumberService::normalize($request->phone)]);
        $errors = $this->service->updateRecord($recordId, $request->only([
            'full_name','second_name','email','phone','address','postcode','city','state'
        ]));

        $record = DB::table('batch_registration_records')->where('record_id', $recordId)->first();

        if (empty($errors)) {
            return back()->with('success', "Row {$record->row_number} updated and validated successfully.");
        } else {
            return back()->with('error', "Row {$record->row_number} still has errors: " . implode(' | ', $errors));
        }
    }

    // -------------------------------------------------------
    // Download PDF rejection report
    // -------------------------------------------------------
    public function rejectionReport(string $batchId)
    {
        $batch   = DB::table('batch_registration_staging')->where('batch_id', $batchId)->first();
        $invalid = DB::table('batch_registration_records')
                     ->where('batch_id', $batchId)
                     ->where('status', 'INVALID')
                     ->get();

        // Generate simple PDF using DomPDF (Laravel's default PDF package)
        $html = view('batch.rejection-report-pdf', compact('batch', 'invalid'))->render();

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->download("rejection_report_{$batchId}.pdf");
        }

        // Fallback: return as printable HTML
        return response($html)->header('Content-Type', 'text/html');
    }

    // -------------------------------------------------------
    // Purge batch — delete all staging records
    // -------------------------------------------------------
    public function purge(string $batchId)
    {
        $batch = DB::table('batch_registration_staging')->where('batch_id', $batchId)->first();

        if ($batch->status === 'COMMITTED') {
            return back()->with('error', 'Cannot purge a committed batch. Agents already created.');
        }

        $this->service->purgeBatch($batchId);
        return redirect()->route('admin.batch.index')
                         ->with('success', 'Batch purged successfully.');
    }

    // -------------------------------------------------------
    // Download column mapping template (CSV)
    // -------------------------------------------------------
    public function downloadTemplate()
    {
        // 'second_name' — optional Chinese/Tamil/Hindi/other-script name column.
        // Leave the cell blank if not needed; it is never required.
        $headers = ['full_name','second_name','phone','email','address','postcode','city','state'];
        $sample  = [
            ['Ahmad Bin Ali','','+60121234567','ahmad@email.com','No. 12, Jalan Merdeka','50000','Kuala Lumpur','Kuala Lumpur'],
            ['Siti Binti Omar','','+60197654321','siti@email.com','No. 5, Jalan Damai','40000','Shah Alam','Selangor'],
        ];

        $csv  = implode(',', $headers) . "\n";
        foreach ($sample as $row) {
            $csv .= implode(',', $row) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="batch_registration_template.csv"',
        ]);
    }
}
