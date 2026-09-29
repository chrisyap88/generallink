<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Claim extends Model
{
    protected $primaryKey = 'claim_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'claim_id',
        'claim_reference',
        'policy_id',
        'customer_id',
        'agent_id',
        'vendor_id',
        'product_id',
        'claim_amount',
        'approved_amount',
        'sales_transaction_date',
        'claim_upload_date',
        'status',
        'commission_held',
        'commission_released_at',
        'rejection_reason',
        'notes',
        'is_deleted',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'claim_amount'           => 'decimal:4',
        'approved_amount'        => 'decimal:4',
        'commission_held'        => 'boolean',
        'sales_transaction_date' => 'date',
        'claim_upload_date'      => 'datetime',
        'commission_released_at' => 'datetime',
        'is_deleted'             => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->claim_id)) {
                $model->claim_id = (string) Str::uuid();
            }
            if (empty($model->claim_reference)) {
                $model->claim_reference = 'CLM-' . strtoupper(Str::random(8));
            }
            // Auto-hold commission on claim submission
            $model->commission_held = 1;
        });
    }

    // Relationships
    public function transaction()
    {
        return $this->belongsTo(SalesTransaction::class, 'policy_id', 'policy_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'vendor_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function documents()
    {
        return $this->hasMany(ClaimDocument::class, 'claim_id', 'claim_id');
    }

    public function allocations()
    {
        return $this->hasMany(VendorPaymentAllocation::class, 'claim_id', 'claim_id');
    }

    public function holdLog()
    {
        return $this->hasMany(CommissionHoldLog::class, 'claim_id', 'claim_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_deleted', 0);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeHeld($query)
    {
        return $query->where('commission_held', 1);
    }

    // Helpers
    public function isCommissionHeld(): bool
    {
        return $this->commission_held == 1;
    }

    public function releaseCommission(): void
    {
        $this->update([
            'commission_held'        => 0,
            'commission_released_at' => now(),
        ]);
    }
}
