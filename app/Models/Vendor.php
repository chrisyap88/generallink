<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

// NEW 8 Aug 2026 (Task #92) — mirrors App\Models\Agent's auth shape as
// closely as possible (same getAuthPassword() pattern, same UUID
// auto-generation on create) so the 'vendor' auth guard behaves exactly
// like the existing 'agent' guard, just on a much smaller surface
// (vendors have no hierarchy, no roles, no commission — just login +
// submit/track their own offer requests).
class Vendor extends Authenticatable
{
    protected $table      = 'vendors';
    protected $primaryKey = 'vendor_id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'vendor_id', 'vendor_name', 'second_name', 'vendor_code', 'vendor_email', 'vendor_phone',
        'vendor_office_phone', 'vendor_website', 'vendor_address', 'vendor_postcode',
        'vendor_city', 'vendor_state', 'pic_name', 'pic_phone', 'pic_designation', 'pic_email', 'industry', 'vendor_type', 'entity_type',
        'contact2_name', 'contact2_designation', 'contact2_phone', 'contact2_email',
        'contact3_name', 'contact3_designation', 'contact3_phone', 'contact3_email',
        'authorized_signatory_name', 'authorized_signatory_designation', 'authorized_signatory_email', 'authorized_signatory_phone',
        'is_active', 'password_hash', 'login_status', 'rejection_reason',
        'ssm_document_path', 'ssm_document_name', 'company_profile_document_path',
        'company_profile_document_name', 'fb_page_url', 'email_verification_token', 'qa_access_token',
        'declaration_accepted_at', 'declaration_version', 'nature_of_business',
        'industry_other_text', 'nature_of_business_other_text',
        'approved_by_1', 'approved_at_1', 'approved_by_2', 'approved_at_2',
        'failed_login_attempts', 'locked_until', 'created_by', 'updated_by',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'is_active'               => 'boolean',
        'locked_until'            => 'datetime',
        'declaration_accepted_at' => 'datetime',
        'approved_at_1'           => 'datetime',
        'approved_at_2'           => 'datetime',
    ];

    // NEW 8 Aug 2026 — Vendor Management Phase 1 (spec Section 3). Kept as
    // a model constant, same pattern as VendorController::INDUSTRIES, so
    // every screen (self-registration, Admin create/edit, Phase 2
    // catalogue later) reads from one place.
    public const TYPES = [
        'PRODUCT'         => 'Product',
        'SERVICE'         => 'Service',
        'EVENT'           => 'Event',
        'PRODUCT_SERVICE' => 'Product + Service',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->vendor_id)) {
                $model->vendor_id = Str::uuid()->toString();
            }
        });
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * NEW 13 Aug 2026 — per Chris: "which vendor email address? contact
     * 1 or 2 or 3? you need to add another contact as authorized
     * Director." Single source of truth for "who is authorised to sign
     * on behalf of this vendor" — defaults to Contact 1 (already the
     * vendor's login identity), and only differs if the vendor
     * explicitly named a separate authorised signatory (e.g. the actual
     * company Director) during registration. Used by the Agreement /
     * Email OTP acceptance flow (Phase 3/4) so there is exactly one
     * place this decision is resolved, not one hardcoded assumption per
     * screen.
     */
    public function authorizedSignatory(): array
    {
        if ($this->authorized_signatory_email) {
            return [
                'name' => $this->authorized_signatory_name,
                'designation' => $this->authorized_signatory_designation,
                'email' => $this->authorized_signatory_email,
                'phone' => $this->authorized_signatory_phone,
                'is_contact1' => false,
            ];
        }

        return [
            'name' => $this->pic_name,
            'designation' => $this->pic_designation,
            'email' => $this->pic_email ?? $this->vendor_email,
            'phone' => $this->pic_phone ?? $this->vendor_phone,
            'is_contact1' => true,
        ];
    }
}
