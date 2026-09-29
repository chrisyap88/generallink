<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinancialAuditLog extends Model
{
    protected $primaryKey = 'audit_id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    // Append-only — never update
    protected $fillable = [
        'audit_id',
        'event_type',
        'entity_type',
        'entity_id',
        'amount_before',
        'amount_after',
        'metadata',
        'performed_by',
        'ip_address',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'amount_before' => 'decimal:4',
        'amount_after'  => 'decimal:4',
        'metadata'      => 'array',
        'created_at'    => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->audit_id)) {
                $model->audit_id = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });

        // Prevent updates — immutable log
        static::updating(function () {
            return false;
        });

        // Prevent deletes — immutable log
        static::deleting(function () {
            return false;
        });
    }

    public function performer()
    {
        return $this->belongsTo(Agent::class, 'performed_by', 'agent_id');
    }

    // Static helper to log any financial event
    public static function record(
        string $eventType,
        string $entityType,
        string $entityId,
        string $performedBy,
        ?float $amountBefore = null,
        ?float $amountAfter = null,
        ?array $metadata = null,
        ?string $notes = null
    ): void {
        self::create([
            'event_type'    => $eventType,
            'entity_type'   => $entityType,
            'entity_id'     => $entityId,
            'amount_before' => $amountBefore,
            'amount_after'  => $amountAfter,
            'metadata'      => $metadata,
            'performed_by'  => $performedBy,
            'ip_address'    => request()->ip(),
            'notes'         => $notes,
        ]);
    }
}
