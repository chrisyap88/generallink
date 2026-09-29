<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClaimMatchingLog extends Model
{
    protected $primaryKey = 'log_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'log_id',
        'payment_id',
        'claim_id',
        'stage',
        'action',
        'performed_by',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->log_id)) {
                $model->log_id = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function payment()
    {
        return $this->belongsTo(VendorPayment::class, 'payment_id', 'payment_id');
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id', 'claim_id');
    }

    public function performer()
    {
        return $this->belongsTo(Agent::class, 'performed_by', 'agent_id');
    }
}
