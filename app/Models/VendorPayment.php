<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VendorPayment extends Model
{
    protected $primaryKey = 'payment_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'payment_id',
        'payment_reference',
        'vendor_id',
        'payment_date',
        'bank_reference',
        'total_amount',
        'allocated_amount',
        'remittance_provided',
        'remittance_file_path',
        'matching_stage',
        'status',
        'flag_count',
        'is_deleted',
        'recorded_by',
        'updated_by',
    ];

    protected $casts = [
        'payment_date'        => 'date',
        'total_amount'        => 'decimal:4',
        'allocated_amount'    => 'decimal:4',
        'remittance_provided' => 'boolean',
        'is_deleted'          => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->payment_id)) {
                $model->payment_id = (string) Str::uuid();
            }
            if (empty($model->payment_reference)) {
                $model->payment_reference = 'VP-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            }
        });
    }

    // Relationships
    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'vendor_id');
    }

    public function allocations()
    {
        return $this->hasMany(VendorPaymentAllocation::class, 'payment_id', 'payment_id');
    }

    public function matchingLogs()
    {
        return $this->hasMany(ClaimMatchingLog::class, 'payment_id', 'payment_id');
    }

    public function recorder()
    {
        return $this->belongsTo(Agent::class, 'recorded_by', 'agent_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'IN_PROGRESS');
    }

    public function scopeDisputed($query)
    {
        return $query->where('status', 'DISPUTED');
    }

    // Helpers
    public function getUnallocatedAmountAttribute(): float
    {
        return $this->total_amount - $this->allocated_amount;
    }

    public function canAllocate(float $amount): bool
    {
        return ($this->allocated_amount + $amount) <= $this->total_amount;
    }

    public function isFullyAllocated(): bool
    {
        return $this->allocated_amount >= $this->total_amount;
    }

    public function flagVendor(): void
    {
        $this->increment('flag_count');
        if ($this->flag_count >= 3) {
            $this->update(['status' => 'DISPUTED']);
            // Update vendor performance
            VendorPerformance::updateCooperationScore($this->vendor_id);
        }
    }
}
