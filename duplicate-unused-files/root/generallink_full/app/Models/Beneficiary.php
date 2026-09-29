<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Beneficiary extends Model
{
    protected $table      = 'beneficiaries';
    protected $primaryKey = 'beneficiary_id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'beneficiary_id', 'agent_id', 'full_name', 'nric_encrypted',
        'relationship', 'phone', 'email', 'address',
        'bank_name', 'bank_account_encrypted',
        'priority_order', 'takeover_triggered', 'takeover_at',
        'takeover_by', 'takeover_notes', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $hidden = ['nric_encrypted', 'bank_account_encrypted'];

    protected $casts = [
        'takeover_triggered' => 'boolean',
        'takeover_at'        => 'datetime',
        'is_active'          => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->beneficiary_id = $m->beneficiary_id ?: Str::uuid()->toString());
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
