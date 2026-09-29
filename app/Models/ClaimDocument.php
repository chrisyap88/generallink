<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClaimDocument extends Model
{
    protected $primaryKey = 'document_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'document_id',
        'claim_id',
        'document_type',
        'file_name',
        'file_path',
        'file_size',
        'uploaded_by',
        'uploaded_at',
        'is_deleted',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'is_deleted'  => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->document_id)) {
                $model->document_id = (string) Str::uuid();
            }
        });
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id', 'claim_id');
    }

    public function uploader()
    {
        return $this->belongsTo(Agent::class, 'uploaded_by', 'agent_id');
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $kb = $this->file_size / 1024;
        if ($kb < 1024) return round($kb, 1) . ' KB';
        return round($kb / 1024, 1) . ' MB';
    }
}
