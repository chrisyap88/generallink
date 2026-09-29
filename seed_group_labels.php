<?php
$prihatin = Illuminate\Support\Str::uuid();
$rela = Illuminate\Support\Str::uuid();

DB::table('group_labels')->insert([
    ['group_label_id' => $prihatin, 'group_name' => 'prihatin2u', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
    ['group_label_id' => $rela,     'group_name' => 'rela2u',     'description' => null, 'created_at' => now(), 'updated_at' => now()],
]);

$chrisYap = App\Models\Agent::where('full_name', 'like', '%Chris Yap%')->where('role', 'GROUP_LEADER')->first();
$amyTan   = App\Models\Agent::where('full_name', 'like', '%Amy Tan%')->where('role', 'GROUP_LEADER')->first();
$davidLim = App\Models\Agent::where('full_name', 'like', '%David Lim%')->where('role', 'GROUP_LEADER')->first();

if ($chrisYap) { $chrisYap->update(['group_label_id' => $prihatin]); echo "Chris Yap -> prihatin2u" . PHP_EOL; }
if ($amyTan)   { $amyTan->update(['group_label_id' => $prihatin]);   echo "Amy Tan -> prihatin2u" . PHP_EOL; }
if ($davidLim) { $davidLim->update(['group_label_id' => $rela]);     echo "David Lim -> rela2u" . PHP_EOL; }

echo PHP_EOL . 'Done.' . PHP_EOL;
