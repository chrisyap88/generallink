Write-Host "Running final fix..." -ForegroundColor Green
$base = "C:\xampp\htdocs\generallink"
Set-Location $base

# Fix the seeder - write it directly with correct content
Write-Host "Fixing seeder..." -ForegroundColor Yellow
$seeder = @'
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = Str::uuid()->toString();
        $groupId = Str::uuid()->toString();
        $glId    = Str::uuid()->toString();
        $tlId    = Str::uuid()->toString();
        $i1Id    = Str::uuid()->toString();
        $i2Id    = Str::uuid()->toString();
        $allianzId = Str::uuid()->toString();
        $aiaId     = Str::uuid()->toString();
        $zurichId  = Str::uuid()->toString();
        $motorId   = Str::uuid()->toString();
        $paId      = Str::uuid()->toString();
        $fireId    = Str::uuid()->toString();

        DB::table('agents')->insert([
            'agent_id'=>$adminId,'member_code'=>null,'agent_code'=>'ADMIN-001',
            'full_name'=>'GeneralLink Admin','email'=>'admin@generallink.my',
            'password_hash'=>Hash::make('Admin@12345'),'nric_encrypted'=>encrypt('000000000000'),
            'phone'=>'+60123456789','role'=>'ADMIN','status'=>'ACTIVE',
            'parent_id'=>null,'hierarchy_path'=>'/','group_id'=>null,
            'recruitable_tier_depth'=>0,'recruitment_blocked'=>0,
            'qr_code_token'=>Str::random(40),
            'admin_bank_name'=>'Maybank','admin_bank_account_encrypted'=>encrypt('5621234567890'),
            'commission_balance'=>0,'email_verified_at'=>now(),
            'security_phrase_set'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);

        DB::table('agents')->insert([
            'agent_id'=>$glId,'member_code'=>null,'agent_code'=>'GL-00001',
            'full_name'=>'Chris Yap','email'=>'chrisyap@generallink.my',
            'password_hash'=>Hash::make('Password@123'),'nric_encrypted'=>encrypt('800101015678'),
            'phone'=>'+60112345678','role'=>'GROUP_LEADER','status'=>'ACTIVE',
            'parent_id'=>null,'hierarchy_path'=>"/{$glId}/",'group_id'=>$groupId,
            'recruitable_tier_depth'=>0,'recruitment_blocked'=>0,
            'qr_code_token'=>Str::random(40),'bank_name'=>'CIMB Bank',
            'bank_account_encrypted'=>encrypt('7081234567'),'commission_balance'=>1250.00,
            'email_verified_at'=>now(),'security_phrase_set'=>true,
            'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now(),
        ]);

        DB::table('groups')->insert([
            'group_id'=>$groupId,'group_name'=>'Chris Yap','group_code'=>'C0001',
            'group_email'=>'chrisyap@generallink.my','separator_char'=>'-',
            'root_member_suffix'=>'0','is_active'=>true,
            'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now(),
        ]);

        DB::table('agents')->insert([
            'agent_id'=>$tlId,'member_code'=>'C0001-0','agent_code'=>'TL-00001',
            'full_name'=>'Ahmad Razif','email'=>'ahmad.razif@generallink.my',
            'password_hash'=>Hash::make('Password@123'),'nric_encrypted'=>encrypt('850215086543'),
            'phone'=>'+60198765432','role'=>'TEAM_LEADER','status'=>'ACTIVE',
            'parent_id'=>$glId,'hierarchy_path'=>"/{$glId}/{$tlId}/",'group_id'=>$groupId,
            'recruitable_tier_depth'=>0,'recruitment_blocked'=>0,
            'qr_code_token'=>Str::random(40),'bank_name'=>'Public Bank',
            'bank_account_encrypted'=>encrypt('3141234567'),'commission_balance'=>680.00,
            'email_verified_at'=>now(),'security_phrase_set'=>true,
            'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now(),
        ]);

        DB::table('agents')->insert([
            'agent_id'=>$i1Id,'member_code'=>'C0001-0-1','agent_code'=>'I-00001',
            'full_name'=>'Siti Nurhaliza','email'=>'siti.nurhaliza@generallink.my',
            'password_hash'=>Hash::make('Password@123'),'nric_encrypted'=>encrypt('900303075432'),
            'phone'=>'+60171234567','role'=>'INTRODUCER','status'=>'ACTIVE',
            'parent_id'=>$tlId,'hierarchy_path'=>"/{$glId}/{$tlId}/{$i1Id}/",'group_id'=>$groupId,
            'recruitable_tier_depth'=>1,'recruitment_blocked'=>0,
            'qr_code_token'=>Str::random(40),'bank_name'=>'Maybank',
            'bank_account_encrypted'=>encrypt('1234567890'),'commission_balance'=>320.00,
            'email_verified_at'=>now(),'security_phrase_set'=>true,
            'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now(),
        ]);

        DB::table('tier_recruitment_config')->insert([
            'config_id'=>Str::uuid()->toString(),'group_id'=>null,'max_tier_limit'=>2,
            'is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now(),
        ]);

        DB::table('vendors')->insert([
            ['vendor_id'=>$allianzId,'vendor_name'=>'Allianz Malaysia','vendor_code'=>'ALZ','is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now()],
            ['vendor_id'=>$aiaId,'vendor_name'=>'AIA Malaysia','vendor_code'=>'AIA','is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now()],
            ['vendor_id'=>$zurichId,'vendor_name'=>'Zurich Insurance','vendor_code'=>'ZUR','is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now()],
        ]);

        DB::table('products')->insert([
            ['product_id'=>$motorId,'vendor_id'=>$allianzId,'product_name'=>'Motor Comprehensive','product_code'=>'ALZ-MCOMP','product_type'=>'MOTOR','is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now()],
            ['product_id'=>$paId,'vendor_id'=>$aiaId,'product_name'=>'PA Plus','product_code'=>'AIA-PAPLUS','product_type'=>'PERSONAL_ACCIDENT','is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now()],
            ['product_id'=>$fireId,'vendor_id'=>$zurichId,'product_name'=>'Householder Fire','product_code'=>'ZUR-FIRE','product_type'=>'FIRE','is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now()],
        ]);

        DB::table('commission_structures')->insert([
            ['structure_id'=>Str::uuid()->toString(),'vendor_id'=>$allianzId,'product_id'=>$motorId,'commission_basis'=>'PREMIUM_PCT','total_commission_pct'=>10.0000,'introducer_pct'=>50.0000,'team_leader_pct'=>25.0000,'group_leader_pct'=>25.0000,'valid_from'=>'2026-01-01','valid_to'=>null,'is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now()],
            ['structure_id'=>Str::uuid()->toString(),'vendor_id'=>$aiaId,'product_id'=>$paId,'commission_basis'=>'PREMIUM_PCT','total_commission_pct'=>25.0000,'introducer_pct'=>60.0000,'team_leader_pct'=>25.0000,'group_leader_pct'=>15.0000,'valid_from'=>'2026-01-01','valid_to'=>null,'is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now()],
            ['structure_id'=>Str::uuid()->toString(),'vendor_id'=>$zurichId,'product_id'=>$fireId,'commission_basis'=>'PREMIUM_PCT','total_commission_pct'=>15.0000,'introducer_pct'=>55.0000,'team_leader_pct'=>25.0000,'group_leader_pct'=>20.0000,'valid_from'=>'2026-01-01','valid_to'=>null,'is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now()],
        ]);

        DB::table('reward_points_rates')->insert([
            'rate_id'=>Str::uuid()->toString(),'vendor_id'=>null,'product_id'=>null,
            'points_per_rm'=>2.5000,'valid_from'=>'2026-01-01','valid_to'=>null,
            'is_active'=>true,'created_by'=>$adminId,'created_at'=>now(),'updated_at'=>now(),
        ]);

        $this->command->info('GeneralLink seeded! Admin: admin@generallink.my / Admin@12345');
    }
}
'@
Set-Content -Path "$base\database\seeders\DatabaseSeeder.php" -Value $seeder -Encoding UTF8
Write-Host "Seeder fixed!" -ForegroundColor Green

# Now run migrate fresh with seed
Write-Host "Running migrations and seed..." -ForegroundColor Yellow
& php artisan migrate:fresh --seed --force

Write-Host ""
Write-Host "Done! Starting server..." -ForegroundColor Green
Write-Host "Open: http://127.0.0.1:8000" -ForegroundColor Cyan
Write-Host "Login: admin@generallink.my / Admin@12345" -ForegroundColor Cyan
& php artisan serve
