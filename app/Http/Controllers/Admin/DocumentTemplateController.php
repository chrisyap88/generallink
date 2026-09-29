<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * REWRITTEN 18 Jul 2026 — Admin-only Document Template screens.
 *
 * Previously this drove a 3-screen calibration wizard (upload a sample
 * document, teach the engine an anchor label or a labeled-image bracket
 * position for every field, review a test extraction). That whole
 * approach is gone: the live Sales Transaction form now reads uploaded
 * documents directly through the Claude API
 * (see Shared\SalesTransactionController::extractDocument() and
 * App\Services\ClaudeDocumentExtractionService), which understands the
 * document the way a person would — no per-vendor calibration needed.
 *
 * What's left here is much smaller: for a given Vendor + Product +
 * Document Type, tell the system which fields are actually expected to
 * appear on that document (a checklist), so the extraction prompt can
 * be scoped tighter and the eventual Review screen only asks about
 * fields relevant to that vendor. No sample upload, no bracket
 * labeling, no calibration step, no local OCR involved anywhere.
 */
class DocumentTemplateController extends Controller
{
    /**
     * Generic field roles — same list works for any future vendor
     * industry.
     */
    public const FIELD_ROLES = [
        'DOCUMENT_REFERENCE_NUMBER' => 'Document Reference Number (policy/invoice/receipt no.)',
        'TRANSACTION_DATE'          => 'Transaction / Issue Date',
        'COVERAGE_START'            => 'Coverage Start Date (insurance only)',
        'COVERAGE_END'              => 'Coverage End Date (insurance only)',
        'COVERAGE_TYPE'             => 'Coverage Type (insurance only)',
        'ADD_ONS'                   => 'Add-ons / Extensions (insurance only)',
        'AMOUNT'                    => 'Sales Amount',
        'SUM_INSURED'               => 'Sum Insured (insurance only)',
        'VENDOR_NAME'               => "Vendor's Printed Name (resolves vendor_id)",
        'CUSTOMER_NAME'             => 'Customer Name',
        'CUSTOMER_NRIC'             => 'Customer NRIC',
        'CUSTOMER_PHONE'            => 'Customer Phone',
        'CUSTOMER_ADDRESS'          => 'Customer Address',
        'CUSTOMER_EMAIL'            => 'Customer Email',
        'VEHICLE_NUMBER'            => 'Vehicle Registration Number (motor only)',
        'VEHICLE_MAKE_MODEL'        => 'Make & Type of Body (motor only)',
        'CUBIC_CAPACITY'            => 'Cubic Capacity (motor only)',
        'YEAR_OF_MANUFACTURE'       => 'Year of Manufacture (motor only)',
        'SEATING_CAPACITY'          => 'Seating Capacity (motor only)',
        'ENGINE_NUMBER'             => 'Engine No. (motor only)',
        'CHASSIS_NUMBER'            => 'Chassis No. (motor only)',
        'TRAILER_CHASSIS_NUMBER'    => 'Trailer Chassis No. (motor only)',
        'NAMED_DRIVERS'             => 'Named Driver(s) (motor only)',
    ];

    /**
     * Groups FIELD_ROLES by which real screen/table each one actually
     * lands on, so the checklist below is presented one section at a
     * time (Customer, Vehicle & Renewal, etc.) instead of one long
     * flat list.
     */
    public const FIELD_SECTIONS = [
        'Document / Vendor' => ['DOCUMENT_REFERENCE_NUMBER', 'TRANSACTION_DATE', 'VENDOR_NAME'],
        'Customer'          => ['CUSTOMER_NAME', 'CUSTOMER_NRIC', 'CUSTOMER_PHONE', 'CUSTOMER_ADDRESS', 'CUSTOMER_EMAIL'],
        'Sales Transaction' => ['AMOUNT', 'SUM_INSURED'],
        'Coverage & Add-ons'=> ['COVERAGE_START', 'COVERAGE_END', 'COVERAGE_TYPE', 'ADD_ONS'],
        'Vehicle & Renewal' => ['VEHICLE_NUMBER', 'VEHICLE_MAKE_MODEL', 'CUBIC_CAPACITY', 'YEAR_OF_MANUFACTURE', 'SEATING_CAPACITY', 'ENGINE_NUMBER', 'CHASSIS_NUMBER', 'TRAILER_CHASSIS_NUMBER', 'NAMED_DRIVERS'],
    ];

    /**
     * Matches the document_type enum already used on the live Sales
     * Transaction upload form — a template applies to one of these,
     * since a Policy Schedule and an Invoice from the same vendor
     * almost never carry the same fields.
     */
    public const DOCUMENT_TYPES = [
        'POLICY_DOCUMENT' => 'Policy document / cover note',
        'SALES_INVOICE'   => 'Invoice',
        'RECEIPT'         => 'Receipt',
        'OTHER'           => 'Other',
    ];

    public function index(Request $request)
    {
        $filterProductId = $request->get('product_id');
        $filterProductName = $filterProductId
            ? DB::table('products')->where('product_id', $filterProductId)->value('product_name')
            : null;

        $templates = DB::table('document_templates as dt')
            ->join('vendors as v', 'dt.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('products as p', 'dt.product_id', '=', 'p.product_id')
            ->leftJoin('agents as a', 'dt.created_by', '=', 'a.agent_id')
            ->select(
                'dt.template_id', 'dt.template_group_id', 'dt.template_name', 'dt.document_type',
                'dt.is_active', 'dt.version_number', 'dt.created_at',
                'v.vendor_name', 'p.product_name', 'a.full_name as created_by_name'
            )
            ->whereRaw('dt.version_number = (select max(inner_dt.version_number) from document_templates as inner_dt where inner_dt.template_group_id = dt.template_group_id)')
            ->when($filterProductId, fn ($q) => $q->where('dt.product_id', $filterProductId))
            ->orderBy('v.vendor_name')
            ->orderBy('dt.template_name')
            ->get();

        $versionCounts = DB::table('document_templates')
            ->select('template_group_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('template_group_id')
            ->pluck('cnt', 'template_group_id');

        return view('admin.document-templates.index', compact('templates', 'versionCounts', 'filterProductId', 'filterProductName'));
    }

    public function create()
    {
        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get();
        $fieldRoles = self::FIELD_ROLES;
        $fieldSections = self::FIELD_SECTIONS;
        $documentTypes = self::DOCUMENT_TYPES;
        $template = null;
        $selectedRoles = [];
        $isNewVersion = false;

        return view('admin.document-templates.form', compact('vendors', 'fieldRoles', 'fieldSections', 'documentTypes', 'template', 'selectedRoles', 'isNewVersion'));
    }

    private function validateTemplateRequest(Request $request): array
    {
        return $request->validate([
            'vendor_id'      => ['required', 'exists:vendors,vendor_id'],
            'product_id'     => ['nullable', 'exists:products,product_id'],
            'document_type'  => ['required', 'in:' . implode(',', array_keys(self::DOCUMENT_TYPES))],
            'template_name'  => ['required', 'string', 'max:150'],
            'field_roles'    => ['required', 'array', 'min:1'],
            'field_roles.*'  => ['required', 'string', 'in:' . implode(',', array_keys(self::FIELD_ROLES))],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateTemplateRequest($request);

        // Only one active template per vendor+product+document type —
        // if one already exists, this isn't a brand-new template, it's
        // a format change. Point Admin to "New Version" instead of
        // silently creating a second competing template.
        $existingActive = DB::table('document_templates')
            ->where('vendor_id', $validated['vendor_id'])
            ->where('document_type', $validated['document_type'])
            ->where('is_active', true)
            ->where(function ($q) use ($validated) {
                if ($validated['product_id'] ?? null) {
                    $q->where('product_id', $validated['product_id']);
                } else {
                    $q->whereNull('product_id');
                }
            })
            ->first();

        if ($existingActive) {
            return back()->withInput()->withErrors([
                'vendor_id' => "An active template already exists for this vendor, product, and document type (\"{$existingActive->template_name}\", version {$existingActive->version_number}). If the vendor changed their document layout, open that template and use \"New Version\" instead of creating a separate one.",
            ]);
        }

        $agent = auth('agent')->user();
        $templateId = Str::uuid()->toString();

        DB::table('document_templates')->insert([
            'template_id'       => $templateId,
            'template_group_id' => $templateId,
            'vendor_id'         => $validated['vendor_id'],
            'product_id'        => $validated['product_id'] ?: null,
            'document_type'     => $validated['document_type'],
            'template_name'     => $validated['template_name'],
            'version_number'    => 1,
            'is_active'         => true,
            'created_by'        => $agent->agent_id,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->saveFields($templateId, $validated['field_roles']);

        return redirect()->route('admin.document-templates.create')->with('success', 'Document template saved.');
    }

    public function edit(string $id)
    {
        $template = DB::table('document_templates')->where('template_id', $id)->firstOrFail();
        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get();
        $selectedRoles = DB::table('document_template_fields')->where('template_id', $id)->orderBy('sort_order')->pluck('field_role')->all();
        $fieldRoles = self::FIELD_ROLES;
        $fieldSections = self::FIELD_SECTIONS;
        $documentTypes = self::DOCUMENT_TYPES;
        $isNewVersion = false;
        $createdBy = DB::table('agents')->where('agent_id', $template->created_by)->value('full_name');

        return view('admin.document-templates.form', compact('template', 'vendors', 'fieldRoles', 'fieldSections', 'documentTypes', 'selectedRoles', 'isNewVersion', 'createdBy'));
    }

    /**
     * Correcting a mistake on the CURRENT version — does not bump the
     * version or touch history. For an actual format change from the
     * vendor, use newVersion() instead.
     */
    public function update(Request $request, string $id)
    {
        $validated = $this->validateTemplateRequest($request);

        DB::table('document_templates')->where('template_id', $id)->update([
            'vendor_id'     => $validated['vendor_id'],
            'product_id'    => $validated['product_id'] ?: null,
            'document_type' => $validated['document_type'],
            'template_name' => $validated['template_name'],
            'updated_at'    => now(),
        ]);

        DB::table('document_template_fields')->where('template_id', $id)->delete();
        $this->saveFields($id, $validated['field_roles']);

        $backUrl = $request->input('back') ? urldecode($request->input('back')) : route('admin.document-templates.index');

        return redirect($backUrl)->with('success', 'Document template updated.');
    }

    /**
     * Start a new version from an existing template — e.g. the vendor
     * redesigned their document. Pre-fills the checklist from the
     * current version so Admin only edits what actually changed.
     */
    public function newVersionForm(string $id)
    {
        $template = DB::table('document_templates')->where('template_id', $id)->firstOrFail();
        $vendors = DB::table('vendors')->where('is_active', true)->orderBy('vendor_name')->get();
        $selectedRoles = DB::table('document_template_fields')->where('template_id', $id)->orderBy('sort_order')->pluck('field_role')->all();
        $fieldRoles = self::FIELD_ROLES;
        $fieldSections = self::FIELD_SECTIONS;
        $documentTypes = self::DOCUMENT_TYPES;
        $isNewVersion = true;
        $createdBy = DB::table('agents')->where('agent_id', $template->created_by)->value('full_name');

        return view('admin.document-templates.form', compact('template', 'vendors', 'fieldRoles', 'fieldSections', 'documentTypes', 'selectedRoles', 'isNewVersion', 'createdBy'));
    }

    public function storeNewVersion(Request $request, string $id)
    {
        $previous = DB::table('document_templates')->where('template_id', $id)->firstOrFail();
        $validated = $this->validateTemplateRequest($request);

        $agent = auth('agent')->user();
        $newId = Str::uuid()->toString();

        DB::table('document_templates')->insert([
            'template_id'       => $newId,
            'template_group_id' => $previous->template_group_id,
            'vendor_id'         => $validated['vendor_id'],
            'product_id'        => $validated['product_id'] ?: null,
            'document_type'     => $validated['document_type'],
            'template_name'     => $validated['template_name'],
            'version_number'    => $previous->version_number + 1,
            'is_active'         => true,
            'created_by'        => $agent->agent_id,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->saveFields($newId, $validated['field_roles']);

        DB::table('document_templates')->where('template_id', $id)->update([
            'is_active'     => false,
            'superseded_at' => now(),
            'updated_at'    => now(),
        ]);

        $backUrl = $request->input('back') ? urldecode($request->input('back')) : route('admin.document-templates.index');

        return redirect($backUrl)->with('success', "New version {$validated['template_name']} (v{$previous->version_number}->" . ($previous->version_number + 1) . ") saved. Version {$previous->version_number} is kept in history.");
    }

    /**
     * Read-only list of every version ever set up for this template
     * group — so if a vendor's format changes back, the old field list
     * is still there to see or reactivate.
     */
    public function history(string $groupId)
    {
        $versions = DB::table('document_templates as dt')
            ->join('vendors as v', 'dt.vendor_id', '=', 'v.vendor_id')
            ->leftJoin('products as p', 'dt.product_id', '=', 'p.product_id')
            ->where('dt.template_group_id', $groupId)
            ->select('dt.*', 'v.vendor_name', 'p.product_name')
            ->orderByDesc('dt.version_number')
            ->get();

        abort_if($versions->isEmpty(), 404);

        return view('admin.document-templates.history', compact('versions'));
    }

    public function reactivate(string $id)
    {
        $template = DB::table('document_templates')->where('template_id', $id)->firstOrFail();

        DB::table('document_templates')
            ->where('template_group_id', $template->template_group_id)
            ->where('template_id', '!=', $id)
            ->update(['is_active' => false, 'superseded_at' => now(), 'updated_at' => now()]);

        DB::table('document_templates')->where('template_id', $id)->update([
            'is_active'     => true,
            'superseded_at' => null,
            'updated_at'    => now(),
        ]);

        return back()->with('success', "Version {$template->version_number} reactivated — it's now the one used for future uploads.");
    }

    public function toggle(string $id)
    {
        $template = DB::table('document_templates')->where('template_id', $id)->firstOrFail();
        DB::table('document_templates')->where('template_id', $id)->update([
            'is_active'     => !$template->is_active,
            'superseded_at' => !$template->is_active ? null : now(),
            'updated_at'    => now(),
        ]);

        return back()->with('success', 'Template status updated.');
    }

    private function saveFields(string $templateId, array $fieldRoles): void
    {
        $rows = [];
        foreach (array_values($fieldRoles) as $i => $role) {
            $rows[] = [
                'field_id'    => Str::uuid()->toString(),
                'template_id' => $templateId,
                'field_role'  => $role,
                'sort_order'  => $i,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }
        DB::table('document_template_fields')->insert($rows);
    }
}
