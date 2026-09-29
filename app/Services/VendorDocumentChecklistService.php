<?php

namespace App\Services;

// NEW 9 Aug 2026 — per Chris's exact spec: "the system should
// automatically switch required documents based on [entity type]
// selection."
//
// REBUILT 9 Aug 2026 (SSM Document Registration & Verification Module)
// per Chris's full, detailed spec. Entity Type now asks the real
// question that determines which SSM paperwork applies — Old Companies
// Act 1965 registration vs New Companies Act 2016 registration vs Sole
// Proprietorship/Partnership vs Public Listed Company — instead of the
// earlier Sdn Bhd/Bhd/Partnership/Sole Prop split. Every document is its
// own checklist line (name + description + file), tiered:
//   MANDATORY   — blocks submission if missing.
//   CONDITIONAL — shown, never forced ("required only when applicable" —
//                 Chris: "the Vendor should NOT be forced to upload a
//                 conditional document when it does not apply").
//   OPTIONAL    — supporting evidence only.
// 'OTHER' is kept as a pragmatic 5th option for foreign-registered
// vendors, since SSM's Malaysia-specific forms don't apply to them —
// Chris's spec didn't ask for this, but removing it entirely would leave
// non-Malaysian vendors with no way to register at all.
class VendorDocumentChecklistService
{
    public const ENTITY_TYPES = [
        'OLD_COMPANY_1965' => 'Old Registration – Companies Act 1965',
        'NEW_COMPANY_2016' => 'New Registration – Companies Act 2016',
        'SOLE_PROP'         => 'Sole Proprietorship / Partnership',
        'PUBLIC_LISTED'     => 'Public Listed Company',
        'OTHER'             => 'Other / Foreign-Registered Entity',
    ];

    public const TIER_MANDATORY   = 'MANDATORY';
    public const TIER_CONDITIONAL = 'CONDITIONAL';
    public const TIER_OPTIONAL    = 'OPTIONAL';

    public const TIER_LABELS = [
        self::TIER_MANDATORY   => 'MANDATORY',
        self::TIER_CONDITIONAL => 'REQUIRED IF APPLICABLE',
        self::TIER_OPTIONAL    => 'OPTIONAL / SUPPORTING DOCUMENT',
    ];

    /**
     * @return array<int, array{key:string,label:string,description:string,tier:string}>
     */
    public static function checklist(string $entityType): array
    {
        return match ($entityType) {
            'OLD_COMPANY_1965' => [
                // CHANGED 9 Aug 2026 per Chris: "form ... 8,9,24,44,49 if
                // click old registration ... is compulsory/required field"
                // — Forms 8/9/24/44/49 are now all MANDATORY, not just Form 9.
                ['key' => 'form9', 'label' => 'Form 9 – Certificate of Incorporation of Private Company', 'description' => 'Certificate confirming incorporation of the company.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'form8', 'label' => 'Form 8 – Certificate of Incorporation of Public Company', 'description' => 'For companies incorporated as public companies under the Companies Act 1965.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'form24', 'label' => 'Form 24 – Return of Allotment of Shares', 'description' => 'Contains information relating to shares allotted by the company and shareholders.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'form44', 'label' => 'Form 44 – Notice of Situation of Registered Office and Office Hours', 'description' => "Contains information relating to the company's registered office.", 'tier' => self::TIER_MANDATORY],
                ['key' => 'form49', 'label' => 'Form 49 – Particulars of Directors, Managers and Secretaries', 'description' => "Contains information relating to the company's directors, managers and company secretary.", 'tier' => self::TIER_MANDATORY],
                ['key' => 'm_and_a', 'label' => 'Memorandum & Articles of Association (M&A)', 'description' => "Where applicable, upload the company's constitutional documents under the previous Companies Act.", 'tier' => self::TIER_OPTIONAL],
                ['key' => 'company_profile', 'label' => 'Latest SSM Company Profile', 'description' => 'Current company information including company number, incorporation date, company status, registered address, nature of business, directors/officers, shareholders and share capital.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'annual_return', 'label' => 'Latest Annual Return', 'description' => 'Latest available annual statutory return.', 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'financials', 'label' => 'Latest Audited Financial Statements', 'description' => 'Latest available audited financial statements, where applicable.', 'tier' => self::TIER_OPTIONAL],
            ],
            'NEW_COMPANY_2016' => [
                // CHANGED 9 Aug 2026 per Chris: "if click new registration
                // then section 14, 17, certificate of incorporation latest
                // company profile section 58,78 is compulsory/required
                // field" — Section 51 and Annual Return/Financials stay
                // conditional (not mentioned in that instruction).
                ['key' => 'section14', 'label' => 'Section 14 – Application for Registration of a Company', 'description' => 'Contains key incorporation information including company name, company type, business nature, registered address, directors and members/shareholders.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'section17', 'label' => 'Section 17 – Notice of Registration / Incorporation', 'description' => "Evidence of the company's incorporation under the Companies Act 2016.", 'tier' => self::TIER_MANDATORY],
                ['key' => 'coi', 'label' => 'Certificate of Incorporation / Registration of Company', 'description' => 'Where the company has requested or obtained the certificate from SSM.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'company_profile', 'label' => 'Latest SSM Company Profile', 'description' => 'Current company information including company number, company type, status, registered address, nature of business, directors/officers, shareholders, share capital and other relevant information.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'section32', 'label' => 'Section 32 – Constitution', 'description' => 'Upload the Constitution where applicable or adopted by the company.', 'tier' => self::TIER_OPTIONAL],
                ['key' => 'section51', 'label' => 'Section 51 – Notification of Change in Register of Members', 'description' => 'Where applicable, particularly where changes to members/shareholders are relevant.', 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'section58', 'label' => 'Section 58 – Notification of Change in Particulars of Directors and Secretaries', 'description' => 'Where applicable and where changes have occurred.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'section78', 'label' => 'Section 78 – Return for Allotment of Shares', 'description' => 'Where applicable, for companies that have made share allotments.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'annual_return', 'label' => 'Latest Annual Return', 'description' => 'Latest available Annual Return.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'financials', 'label' => 'Latest Financial Statements & Reports', 'description' => 'Latest available financial statements and reports, where applicable.', 'tier' => self::TIER_CONDITIONAL],
            ],
            'SOLE_PROP' => [
                // CHANGED 9 Aug 2026 per Chris: "form A, D, business profile
                // ... is compulsory/required field" — Form A joins Form D
                // and Business Profile as MANDATORY.
                ['key' => 'form_a', 'label' => 'Form A – Registration of Business', 'description' => 'Application for registration of the business.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'form_d', 'label' => 'Form D – Business Registration Certificate', 'description' => 'Official Certificate of Registration of the business.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'business_profile', 'label' => 'Business Profile – Latest SSM Business Profile', 'description' => 'Current business information including business name, registration number, business type, owner/partners, business address and registration information.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'form_a1', 'label' => 'Form A1 – Application for Renewal of Business Registration', 'description' => 'Required where relevant to demonstrate renewal of the business registration.', 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'form_b', 'label' => 'Form B – Registration of Changes in Business Particulars', 'description' => 'Required only where changes to the registered business particulars have been made.', 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'partnership_agreement', 'label' => 'Partnership Agreement', 'description' => 'For partnerships, where applicable.', 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'licence', 'label' => 'Relevant Business Licence / Permit / Supporting Letter', 'description' => 'Where the nature of business requires a licence, permit or approval from another authority.', 'tier' => self::TIER_OPTIONAL],
            ],
            'PUBLIC_LISTED' => [
                ['key' => 'section14', 'label' => 'Section 14 – Application for Registration of a Company', 'description' => "Where available and relevant to the company's incorporation records.", 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'notice_registration', 'label' => 'Notice of Registration / Incorporation', 'description' => '', 'tier' => self::TIER_MANDATORY],
                ['key' => 'coi', 'label' => 'Certificate of Incorporation / Registration', 'description' => '', 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'company_profile', 'label' => 'Latest SSM Company Profile', 'description' => '', 'tier' => self::TIER_MANDATORY],
                ['key' => 'annual_return', 'label' => 'Latest Annual Return', 'description' => '', 'tier' => self::TIER_MANDATORY],
                ['key' => 'section58', 'label' => 'Section 58 – Directors and Secretaries Information / Latest Relevant Filing', 'description' => '', 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'section51', 'label' => 'Section 51 – Register of Members Changes / Relevant Filing', 'description' => '', 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'section78', 'label' => 'Section 78 – Return for Allotment of Shares / Relevant Filing', 'description' => 'Where applicable.', 'tier' => self::TIER_CONDITIONAL],
                ['key' => 'financials', 'label' => 'Latest Audited Financial Statements', 'description' => '', 'tier' => self::TIER_MANDATORY],
                ['key' => 'annual_report', 'label' => 'Latest Annual Report', 'description' => '', 'tier' => self::TIER_OPTIONAL],
                ['key' => 'financial_report', 'label' => 'Latest Financial Report', 'description' => '', 'tier' => self::TIER_OPTIONAL],
                ['key' => 'governance_statement', 'label' => 'Latest Corporate Governance / Governance Statement', 'description' => 'Where applicable.', 'tier' => self::TIER_OPTIONAL],
                ['key' => 'bursa_disclosure', 'label' => 'Latest Bursa Malaysia Corporate Announcement / Disclosure', 'description' => 'Where applicable.', 'tier' => self::TIER_OPTIONAL],
                ['key' => 'shareholding_info', 'label' => 'Latest Shareholding Information', 'description' => '', 'tier' => self::TIER_OPTIONAL],
                ['key' => 'other_disclosure', 'label' => 'Other Regulatory Disclosure', 'description' => 'Where applicable.', 'tier' => self::TIER_OPTIONAL],
            ],
            default => [
                ['key' => 'business_reg', 'label' => 'Business Registration / Incorporation Document', 'description' => 'Proof of registration or incorporation in your home jurisdiction.', 'tier' => self::TIER_MANDATORY],
                ['key' => 'business_profile', 'label' => 'Company or Business Profile Document', 'description' => 'Current company/business information.', 'tier' => self::TIER_MANDATORY],
            ],
        };
    }

    /** All checklists keyed by entity type — handed to the view as one JSON blob for the client-side dynamic form. */
    public static function allChecklists(): array
    {
        $out = [];
        foreach (array_keys(self::ENTITY_TYPES) as $key) {
            $out[$key] = self::checklist($key);
        }
        return $out;
    }

    /** Keys of documents that are MANDATORY for a given entity type — used by server-side validation, never trust the client alone. */
    public static function requiredKeys(string $entityType): array
    {
        return array_column(array_filter(self::checklist($entityType), fn ($d) => $d['tier'] === self::TIER_MANDATORY), 'key');
    }

    /**
     * True if an actual vendor_documents.document_key satisfies a given
     * required key. Kept as a real (not merely cosmetic) equality check
     * — every checklist item is a single upload field again, but this
     * stays in place because Admin's Review Documents badge and the
     * approval gate both call it, and a plain '===' inline would be
     * fine too but this keeps one canonical definition of "satisfies."
     */
    public static function keySatisfiesGroup(string $documentKey, string $groupKey): bool
    {
        return $documentKey === $groupKey;
    }

    /**
     * NEW 13 Aug 2026 — per Chris's restricted-access design: a
     * self-registered vendor should be able to log in (restricted view
     * only) the moment every MANDATORY document has been individually
     * verified by Admin — not at raw registration (that would undo the
     * anti-fraud rule that no login exists until documents are checked),
     * and not only at final approval either. Extracted from what used
     * to be inline, duplicate logic inside
     * VendorLoginApprovalController::approve() so both the auto-unlock
     * trigger (VendorLoginApprovalController::verifyDocument()) and the
     * final-approval safety check use the exact same definition of
     * "fully verified" — never two copies that could drift apart.
     */
    public static function allMandatoryVerified(\App\Models\Vendor $vendor): bool
    {
        if (!$vendor->entity_type) {
            return false; // legacy vendors with no checklist never use this path
        }
        $requiredKeys = self::requiredKeys($vendor->entity_type);
        $verifiedKeys = \Illuminate\Support\Facades\DB::table('vendor_documents')
            ->where('vendor_id', $vendor->vendor_id)
            ->where('verification_status', 'VERIFIED')
            ->pluck('document_key')
            ->all();

        foreach ($requiredKeys as $groupKey) {
            $satisfied = false;
            foreach ($verifiedKeys as $vk) {
                if (self::keySatisfiesGroup($vk, $groupKey)) {
                    $satisfied = true;
                    break;
                }
            }
            if (!$satisfied) {
                return false;
            }
        }
        return true;
    }
}
