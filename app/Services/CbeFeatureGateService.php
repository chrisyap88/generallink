<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// NEW 25 Aug 2026 — per Chris's Free vs Subscription business model:
// the single gate every "Subscription Version" feature checks before
// showing itself. FREE = standalone single-unit organization (e.g.
// Rotary Club Uptown). PAID = unlocks multi-level HQ/State/Branch/
// Temple management, consolidated reports, and every other item on the
// "Subscription Version" list Chris specified per role.
class CbeFeatureGateService
{
    public static function isPaid(?string $groupLabelId): bool
    {
        if (! $groupLabelId) {
            return false;
        }
        return DB::table('group_labels')->where('group_label_id', $groupLabelId)->value('subscription_tier') === 'PAID';
    }

    public static function tier(?string $groupLabelId): string
    {
        if (! $groupLabelId) {
            return 'FREE';
        }
        return DB::table('group_labels')->where('group_label_id', $groupLabelId)->value('subscription_tier') ?: 'FREE';
    }
}
