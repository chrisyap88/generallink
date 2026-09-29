<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommissionHoldLog extends Model
{
    protected $primaryKey = 'hold_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'hold_id',
        'claim_id',
        'commission_txn_id',
        'agent_id',
        'role_at_transaction',
        'held_amount',
        'hold_reason',
        'held_at',
        'released_at',
        'released_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'held_amount'  => 'decimal:4',
        'held_at'      => 'datetime',
        'released_at'  => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->hold_id)) {
                $model->hold_id = (string) Str::uuid();
            }
            if (empty($model->held_at)) {
                $model->held_at = now();
            }
        });
    }

    // Relationships
    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id', 'claim_id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function releaser()
    {
        return $this->belongsTo(Agent::class, 'released_by', 'agent_id');
    }

    // Scopes
    public function scopeHeld($query)
    {
        return $query->where('status', 'HELD');
    }

    public function scopeReleased($query)
    {
        return $query->where('status', 'RELEASED');
    }

    // Static helper — place hold for all agents in commission chain
    public static function placeHoldForClaim(Claim $claim): void
    {
        $commissions = \DB::table('commission_transactions')
            ->where('policy_id', $claim->policy_id)
            ->where('status', 'CONFIRMED')
            ->get();

        foreach ($commissions as $commission) {
            self::create([
                'claim_id'            => $claim->claim_id,
                'commission_txn_id'   => $commission->txn_id,
                'agent_id'            => $commission->agent_id,
                'role_at_transaction' => $commission->role_at_transaction,
                'held_amount'         => $commission->commission_amount,
                'hold_reason'         => 'CLAIM_PENDING',
                'status'              => 'HELD',
            ]);
        }
    }
}
