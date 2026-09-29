<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use App\Models\Scopes\GroupIsolationScope;

class Agent extends Authenticatable
{
    use Notifiable;

    protected $table      = 'agents';
    protected $primaryKey = 'agent_id';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'agent_id', 'member_code', 'agent_code', 'full_name', 'second_name', 'email',
        'password_hash', 'nric_encrypted', 'phone', 'role', 'status',
        'parent_id', 'hierarchy_path', 'group_id',
        'recruitable_tier_depth', 'recruitment_blocked',
        'qr_code_token', 'bank_name', 'bank_account_encrypted',
        'admin_bank_name', 'admin_bank_account_encrypted',
        'commission_balance', 'email_verified_at', 'email_verification_token',
        'security_phrase', 'security_phrase_set',
        'failed_login_attempts', 'locked_until',
        'is_deleted', 'created_by', 'updated_by',
    ];

    protected $hidden = [
        'password_hash', 'nric_encrypted', 'bank_account_encrypted',
        'admin_bank_account_encrypted', 'security_phrase',
        'email_verification_token',
    ];

    protected $casts = [
        'email_verified_at'   => 'datetime',
        'locked_until'        => 'datetime',
        'recruitment_blocked' => 'boolean',
        'security_phrase_set' => 'boolean',
        'is_deleted'          => 'boolean',
        'commission_balance'  => 'decimal:4',
    ];

    // Laravel auth expects getAuthPassword() to return the hashed password
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // Auto-generate UUID on create
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->agent_id)) {
                $model->agent_id = Str::uuid()->toString();
            }
            // NEW 25 Jul 2026 (task #210) — Growth & Outreach Center
            // referral links need every agent to have a qr_code_token.
            // This used to only get backfilled by a one-off script
            // (generate_qr_tokens.php) run by hand — new agents created
            // since then had no token at all. Auto-generating it here
            // means it's never missing again, for anyone registered from
            // now on, however they're created (public register, Admin
            // batch upload, Organization Rewards Group appoint, etc.).
            if (empty($model->qr_code_token)) {
                do {
                    $token = Str::random(10);
                } while (static::where('qr_code_token', $token)->exists());
                $model->qr_code_token = $token;
            }
        });

        // NEW — Organization Rewards Group isolation, applied automatically
        // to every Agent:: query, everywhere in the app. Confirmed
        // decision (06 Jul 2026). See GroupIsolationScope.php for the
        // full logic.
        static::addGlobalScope(new GroupIsolationScope());
    }

    // -------------------------------------------------------
    // Role helpers
    // -------------------------------------------------------
    public function isAdmin(): bool        { return $this->role === 'ADMIN'; }
    public function isGroupLeader(): bool  { return $this->role === 'GROUP_LEADER'; }
    public function isTeamLeader(): bool   { return $this->role === 'TEAM_LEADER'; }
    public function isIntroducer(): bool   { return $this->role === 'INTRODUCER'; }

    public function isActive(): bool       { return $this->status === 'ACTIVE'; }
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    // -------------------------------------------------------
    // Relationships
    // -------------------------------------------------------
    public function parent()
    {
        return $this->belongsTo(Agent::class, 'parent_id', 'agent_id');
    }

    public function downlines()
    {
        return $this->hasMany(Agent::class, 'parent_id', 'agent_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_id');
    }

    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class, 'agent_id', 'agent_id');
    }

    public function commissionTransactions()
    {
        return $this->hasMany(CommissionTransaction::class, 'agent_id', 'agent_id');
    }

    public function rewardPointsLedger()
    {
        return $this->hasMany(RewardPointsLedger::class, 'agent_id', 'agent_id');
    }

    // -------------------------------------------------------
    // Hierarchy scope helpers
    // -------------------------------------------------------

    /**
     * Returns a query scope filtering agents visible to this user.
     * Admin sees all. GL sees own group. TL sees own subtree. Introducer sees own downlines.
     */
    public function visibleAgentsQuery()
    {
        $query = Agent::where('is_deleted', false);

        if ($this->isAdmin()) {
            return $query;
        }

        if ($this->isGroupLeader()) {
            return $query->where('group_id', $this->group_id);
        }

        if ($this->isTeamLeader()) {
            // Everyone whose hierarchy_path contains this TL's ID
            return $query->where('hierarchy_path', 'like', "%/{$this->agent_id}/%");
        }

        // Introducer — direct downlines only
        return $query->where('parent_id', $this->agent_id);
    }

    // -------------------------------------------------------
    // Points balance (computed from ledger — never a stored field)
    // -------------------------------------------------------
    public function getPointsBalanceAttribute(): float
    {
        return (float) \DB::table('reward_points_ledger')
            ->where('agent_id', $this->agent_id)
            ->selectRaw('COALESCE(SUM(points_in) - SUM(points_out), 0) as balance')
            ->value('balance');
    }
}
