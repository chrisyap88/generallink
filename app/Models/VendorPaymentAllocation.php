<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VendorPaymentAllocation extends Model
{
    protected $primaryKey = 'allocation_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'allocation_id',
        'payment_id',
        'claim_id',
        'allocated_amount',
        'matching_stage',
        'match_method',
        'vendor_ref_provided',
        'verified_by',
        'verified_at',
        'agent_confirmed',
        'agent_confirmed_at',
        'agent_confirmation_deadline',
        'status',
        'reversal_reason',
        'reversed_by',
        'reversed_at',
    ];

    protected $casts = [
        'allocated_amount'             => 'decimal:4',
        'agent_confirmed'              => 'boolean',
        'verified_at'                  => 'datetime',
        'agent_confirmed_at'           => 'datetime',
        'agent_confirmation_deadline'  => 'datetime',
        'reversed_at'                  => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->allocation_id)) {
                $model->allocation_id = (string) Str::uuid();
            }
            // Set agent confirmation deadline — 3 days from now
            if (empty($model->agent_confirmation_deadline)) {
                $model->agent_confirmation_deadline = now()->addDays(3);
            }
        });

        // When confirmed — release commission and update vendor payment allocated amount
        static::updated(function ($model) {
            if ($model->isDirty('status') && $model->status === 'CONFIRMED') {
                // Release commission hold
                $claim = Claim::find($model->claim_id);
                if ($claim) {
                    $claim->releaseCommission();
                    $claim->update(['status' => 'PAID']);
                }
                // Update vendor payment allocated amount
                $payment = VendorPayment::find($model->payment_id);
                if ($payment) {
                    $payment->increment('allocated_amount', $model->allocated_amount);
                    if ($payment->isFullyAllocated()) {
                        $payment->update(['status' => 'COMPLETED']);
                    }
                }
                // Release commission hold log
                CommissionHoldLog::where('claim_id', $model->claim_id)
                    ->where('status', 'HELD')
                    ->update([
                        'status'      => 'RELEASED',
                        'released_at' => now(),
                        'released_by' => $model->verified_by,
                    ]);
            }
        });
    }

    // Relationships
    public function payment()
    {
        return $this->belongsTo(VendorPayment::class, 'payment_id', 'payment_id');
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id', 'claim_id');
    }

    public function verifier()
    {
        return $this->belongsTo(Agent::class, 'verified_by', 'agent_id');
    }

    public function reverser()
    {
        return $this->belongsTo(Agent::class, 'reversed_by', 'agent_id');
    }

    // Helpers
    public function isExpired(): bool
    {
        return $this->agent_confirmation_deadline &&
               now()->isAfter($this->agent_confirmation_deadline) &&
               !$this->agent_confirmed;
    }

    public function revertToPending(): void
    {
        $this->update(['status' => 'NOT_VERIFIED']);
        $this->claim->update(['status' => 'PENDING']);
    }
}
