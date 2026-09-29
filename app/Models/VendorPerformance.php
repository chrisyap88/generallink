<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VendorPerformance extends Model
{
    protected $primaryKey = 'perf_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'perf_id',
        'vendor_id',
        'total_payments',
        'remittance_provided_count',
        'auto_match_rate',
        'avg_days_to_pay',
        'flag_count',
        'dispute_count',
        'cooperation_score',
        'termination_warned',
        'termination_warned_at',
        'last_payment_at',
    ];

    protected $casts = [
        'auto_match_rate'       => 'decimal:2',
        'avg_days_to_pay'       => 'decimal:2',
        'termination_warned'    => 'boolean',
        'termination_warned_at' => 'datetime',
        'last_payment_at'       => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->perf_id)) {
                $model->perf_id = (string) Str::uuid();
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'vendor_id');
    }

    // Recalculate cooperation score based on flag count
    public static function updateCooperationScore(string $vendorId): void
    {
        $perf = self::firstOrCreate(
            ['vendor_id' => $vendorId],
            ['perf_id'   => (string) Str::uuid()]
        );

        $score = match(true) {
            $perf->flag_count >= 5  => 'NONE',
            $perf->flag_count >= 3  => 'POOR',
            $perf->flag_count >= 1  => 'MODERATE',
            default                 => 'GOOD',
        };

        $perf->update(['cooperation_score' => $score]);

        // Auto termination warning at 3 flags
        if ($perf->flag_count >= 3 && !$perf->termination_warned) {
            $perf->update([
                'termination_warned'    => 1,
                'termination_warned_at' => now(),
            ]);
        }
    }

    // Recalculate all stats after a payment is processed
    public static function recalculate(string $vendorId): void
    {
        $payments = VendorPayment::where('vendor_id', $vendorId)
            ->where('is_deleted', 0)
            ->get();

        $totalPayments          = $payments->count();
        $remittanceCount        = $payments->where('remittance_provided', 1)->count();
        $flagCount              = $payments->sum('flag_count');
        $disputeCount           = $payments->where('status', 'DISPUTED')->count();

        $autoMatched = VendorPaymentAllocation::whereIn('payment_id', $payments->pluck('payment_id'))
            ->where('match_method', 'AUTO')
            ->count();
        $totalAllocations = VendorPaymentAllocation::whereIn('payment_id', $payments->pluck('payment_id'))
            ->count();
        $autoMatchRate = $totalAllocations > 0
            ? round(($autoMatched / $totalAllocations) * 100, 2)
            : 0;

        $score = match(true) {
            $flagCount >= 5 => 'NONE',
            $flagCount >= 3 => 'POOR',
            $flagCount >= 1 => 'MODERATE',
            default         => 'GOOD',
        };

        self::updateOrCreate(
            ['vendor_id' => $vendorId],
            [
                'perf_id'                   => (string) Str::uuid(),
                'total_payments'            => $totalPayments,
                'remittance_provided_count' => $remittanceCount,
                'auto_match_rate'           => $autoMatchRate,
                'flag_count'                => $flagCount,
                'dispute_count'             => $disputeCount,
                'cooperation_score'         => $score,
                'last_payment_at'           => $payments->max('payment_date'),
            ]
        );
    }
}
