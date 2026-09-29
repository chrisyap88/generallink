<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SalesTransaction extends Model
{
    protected $table      = 'sales_transactions';
    protected $primaryKey = 'policy_id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'policy_id', 'policy_number', 'vendor_id', 'product_id',
        'customer_id', 'agent_id', 'premium_amount', 'sum_insured',
        'coverage_start', 'coverage_end', 'renewal_date', 'status',
        'upload_batch_id', 'template_id', 'version', 'previous_version_id',
        'is_deleted', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'coverage_start' => 'date',
        'coverage_end'   => 'date',
        'renewal_date'   => 'date',
        'is_deleted'     => 'boolean',
        'premium_amount' => 'decimal:4',
        'sum_insured'    => 'decimal:4',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->policy_id = $m->policy_id ?: Str::uuid()->toString());
    }

    public function customer()   { return $this->belongsTo(Customer::class, 'customer_id', 'customer_id'); }
    public function agent()      { return $this->belongsTo(Agent::class, 'agent_id', 'agent_id'); }
    public function vendor()     { return $this->belongsTo(\stdClass::class, 'vendor_id'); }
    public function product()    { return $this->belongsTo(\stdClass::class, 'product_id'); }
    public function attributes() { return $this->hasMany(\stdClass::class, 'policy_id', 'policy_id'); }
}
