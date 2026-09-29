<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupIsolationScope implements Scope
{
    // Re-entrancy guard — prevents infinite recursion. Calling
    // Auth::guard('agent')->id() below internally calls Laravel's own
    // user() resolution the FIRST time it runs in a request (Laravel's
    // SessionGuard::id() calls user() whenever no user is cached yet),
    // which queries the Agent model again, which re-triggers THIS SAME
    // apply() method — infinitely, exhausting memory. This flag makes
    // any nested/recursive call return immediately without scoping,
    // breaking the cycle. Confirmed root cause and fix (13 Jul 2026) of
    // the "Allowed memory size exhausted" crash on login-as/pvatm.
    private static bool $resolving = false;

    public function apply(Builder $builder, Model $model): void
    {
        if (self::$resolving) {
            return;
        }

        self::$resolving = true;

        try {
            $viewerId = Auth::guard('agent')->id();

            if (!$viewerId) {
                return; // not logged in — nothing to scope
            }

            // Load the viewer's own record WITHOUT this scope applying to
            // THIS lookup — prevents the same recursion problem.
            $viewer = \App\Models\Agent::withoutGlobalScope(self::class)->find($viewerId);

            if (!$viewer) {
                return;
            }

            if ($viewer->role === 'ADMIN') {
                // Admin gets a CHOICE, based on how they logged in — not
                // always unrestricted. Confirmed decision (06 Jul 2026):
                // Admin manages standard and Organization Rewards Groups
                // SEPARATELY when working within one, but also needs a
                // combined/consolidated view across everything.
                //
                // Logging in via a group's own branded page (login-as/X)
                // signals "I'm managing this group specifically" — isolate
                // to that one group for this session. Logging in via the
                // standard page signals "give me the full combined view" —
                // no isolation at all.
                $originSlug = session('login_origin_slug');
                if ($originSlug) {
                    $originLabel = DB::table('group_labels')->where('slug', $originSlug)->first();
                    if ($originLabel) {
                        // IMPORTANT: always allow the viewer to see their
                        // OWN record too — Admin's own group_label_id is
                        // NULL (Admin belongs to no group), so without this,
                        // Admin's own account would be incorrectly filtered
                        // out while isolated to a specific group, breaking
                        // anything that needs to reload "who am I".
                        $builder->where(function ($q) use ($originLabel, $viewerId) {
                            $q->where('group_label_id', $originLabel->group_label_id)
                              ->orWhere('agent_id', $viewerId);
                        });
                    }
                }
                return;
            }

            if (!$viewer->group_label_id) {
                return; // standard group (or no group) — no special isolation
            }

            // Only actually isolate if the viewer's own group is a genuine
            // Organization Rewards Group — standard groups are NOT mutually
            // isolated from each other, only Organization Rewards Groups are.
            $isSpecialGroup = DB::table('group_labels')
                ->where('group_label_id', $viewer->group_label_id)
                ->where('promotion_demotion_enabled', false)
                ->exists();

            if ($isSpecialGroup) {
                $builder->where('group_label_id', $viewer->group_label_id);
            }
        } finally {
            self::$resolving = false;
        }
    }
}