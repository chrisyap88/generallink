<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BatchRegistrationService;
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
            'batch_file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        $file     = $request->file('batch_file');
        $path     = $file->store('batch_uploads', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $batchId = $this->service->parseAndStage($fullPath, Auth::guard('agent')->id());
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
    public function commit(string $batchId)
    {
        $batch = DB::table('batch_registration_staging')->where('batch_id', $batchId)->first();

        if ($batch->status !== 'VALIDATED') {
            return back()->with('error', 'Please validate the batch before committing.');
        }

        $result = $this->service->commitBatch($batchId, Auth::guard('agent')->id());

        return redirect()->route('admin.batch.show', $batchId)
                         ->with('success', "{$result['committed']} agents registered successfully.");
    }

    // -------------------------------------------------------
    // Edit a single staging record then re-validate it
    // -------------------------------------------------------
    public function updateRecord(Request $request, string $recordId)
    {
        $request->validate([
            'full_name'    => ['required', 'string', 'max:200'],
            'email'        => ['required', 'email', 'max:200'],
            'nric'         => ['required', 'string'],
            'phone'        => ['required', 'string', 'max:20'],
            'sponsor_code' => ['required', 'string'],
            'bank_name'    => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:30'],
        ]);

        $errors = $this->service->updateRecord($recordId, $request->only([
            'full_name','email','nric','phone','sponsor_code','bank_name','bank_account'
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
        $headers = ['full_name','email','nric','phone','bank_name','bank_account','sponsor_code','role'];
        $sample  = [
            ['Ahmad Bin Ali','ahmad@email.com','900101015678','+60121234567','Maybank','1234567890','C0001-0','INTRODUCER'],
            ['Siti Binti Omar','siti@email.com','910202026543','+60197654321','CIMB Bank','9876543210','C0001-0-1','INTRODUCER'],
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
