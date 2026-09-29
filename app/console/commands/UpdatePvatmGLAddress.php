<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 23 Jul 2026 — per Chris: after renaming the PVATM GL record to
// "PVATM HQ" (see RenamePvatmGL), also set its office address and
// phone number:
//   Wisma Pahlawan, Jalan Sultan Sulaiman, Kampung Attap,
//   50000 Kuala Lumpur, Wilayah Persekutuan Kuala Lumpur
//   Telefon: 03-22723932
//
// Phone lives on agents.phone; the rest (address/city/state/postcode)
// lives on agent_profiles, which may not have a row yet for this
// agent — updateOrInsert() creates it if missing, updates it if not.
//
// Run via: php artisan pvatm:update-gl-address
// Shows the before/after and asks for a y/n confirmation first.
// -------------------------------------------------------
class UpdatePvatmGLAddress extends Command
{
    protected $signature = 'pvatm:update-gl-address';
    protected $description = 'Set the PVATM HQ GL record\'s office address and phone number';

    private const EMAIL = 'pvatmhq@generallink.my';
    private const ADDRESS = 'Wisma Pahlawan, Jalan Sultan Sulaiman, Kampung Attap';
    private const CITY = 'Kuala Lumpur';
    private const STATE = 'Wilayah Persekutuan Kuala Lumpur';
    private const POSTCODE = '50000';
    private const PHONE = '03-22723932';

    public function handle(): int
    {
        $agent = DB::table('agents')->where('email', self::EMAIL)->where('is_deleted', false)->first();

        if (!$agent) {
            $this->error('No agent found with email ' . self::EMAIL . ' — nothing changed.');
            $this->line('Did the rename (pvatm:rename-gl) run successfully first?');
            return self::FAILURE;
        }

        $label = DB::table('group_labels')->where('slug', 'pvatm')->first();
        if ($agent->role !== 'GROUP_LEADER' || !$label || $agent->group_label_id !== $label->group_label_id) {
            $this->error('That email does not belong to the PVATM Group Leader — refusing to touch it as a safety check.');
            return self::FAILURE;
        }

        $profile = DB::table('agent_profiles')->where('agent_id', $agent->agent_id)->first();

        $this->info("Found: {$agent->full_name} <{$agent->email}>");
        $this->line('  Current phone  : ' . ($agent->phone ?: '(none)'));
        $this->line('  Current address: ' . ($profile->address ?? '(none)'));
        $this->line('  Current city   : ' . ($profile->city ?? '(none)'));
        $this->line('  Current state  : ' . ($profile->state ?? '(none)'));
        $this->line('  Current postcode: ' . ($profile->postcode ?? '(none)'));
        $this->line('');
        $this->info('Will change it to:');
        $this->line('  Phone   : ' . self::PHONE);
        $this->line('  Address : ' . self::ADDRESS);
        $this->line('  City    : ' . self::CITY);
        $this->line('  State   : ' . self::STATE);
        $this->line('  Postcode: ' . self::POSTCODE);
        $this->line('');

        if (!$this->confirm('Apply this change now?', false)) {
            $this->warn('Cancelled — nothing changed.');
            return self::SUCCESS;
        }

        DB::table('agents')->where('agent_id', $agent->agent_id)->update([
            'phone'      => self::PHONE,
            'updated_at' => now(),
        ]);

        DB::table('agent_profiles')->updateOrInsert(
            ['agent_id' => $agent->agent_id],
            [
                'profile_id' => $profile->profile_id ?? Str::uuid()->toString(),
                'address'    => self::ADDRESS,
                'city'       => self::CITY,
                'state'      => self::STATE,
                'postcode'   => self::POSTCODE,
                'updated_at' => now(),
                'created_at' => $profile->created_at ?? now(),
            ]
        );

        $this->info('Done — PVATM HQ\'s address and phone number are updated.');
        return self::SUCCESS;
    }
}
