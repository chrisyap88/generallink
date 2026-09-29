<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Customer extends Model
{
    protected $table      = 'customers';
    protected $primaryKey = 'customer_id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'customer_id', 'nric_encrypted', 'nric_hash',
        'full_name', 'email', 'phone', 'address',
        'postcode', 'city', 'state',
        'owned_by_agent_id', 'is_deleted',
        'created_by', 'updated_by',
    ];

    protected $hidden = ['nric_encrypted'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->customer_id = $m->customer_id ?: Str::uuid()->toString());
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'owned_by_agent_id', 'agent_id');
    }

    public function policies()
    {
        return $this->hasMany(SalesTransaction::class, 'customer_id', 'customer_id');
    }
}
