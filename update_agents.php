<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "Starting agent update/insert...\n";

// Get GL agent_ids
$glChris  = DB::table('agents')->where('full_name', 'like', '%Chris Yap%')->where('role', 'GROUP_LEADER')->first();
$glAmy    = DB::table('agents')->where('full_name', 'like', '%Amy Tan%')->where('role', 'GROUP_LEADER')->first();
$glDavid  = DB::table('agents')->where('full_name', 'like', '%David Lim%')->where('role', 'GROUP_LEADER')->first();

echo "GL Chris: " . ($glChris ? $glChris->agent_id : 'NOT FOUND') . "\n";
echo "GL Amy: "   . ($glAmy   ? $glAmy->agent_id   : 'NOT FOUND') . "\n";
echo "GL David: " . ($glDavid ? $glDavid->agent_id  : 'NOT FOUND') . "\n";

// Get group_ids
$groupChris = $glChris ? $glChris->group_id : null;
$groupAmy   = $glAmy   ? $glAmy->group_id   : null;
$groupDavid = $glDavid ? $glDavid->group_id  : null;

// All agents from Excel with their codes, roles, GL group
$agents = [
    // Chris Yap group (code prefix 1)
    ['name'=>'Chris Yap',        'code'=>'1',    'role'=>'GROUP_LEADER', 'group'=>$groupChris, 'parent'=>null,          'joined'=>'2025-01-15'],
    ['name'=>'Lim Swee Keat',    'code'=>'1-2',  'role'=>'TEAM_LEADER',  'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-07'],
    ['name'=>'Noor Azman Rashid','code'=>'1-3',  'role'=>'TEAM_LEADER',  'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-06'],
    ['name'=>'Tan Boon Hwa',     'code'=>'1-4',  'role'=>'TEAM_LEADER',  'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-08'],
    ['name'=>'Ganesan Pillai',   'code'=>'1-5',  'role'=>'TEAM_LEADER',  'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-25'],
    ['name'=>'Cindy Loh',        'code'=>'1-6',  'role'=>'TEAM_LEADER',  'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-09'],
    ['name'=>'Hafizuddin Malik', 'code'=>'1-7',  'role'=>'TEAM_LEADER',  'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-13'],
    ['name'=>'Patricia Ong',     'code'=>'1-8',  'role'=>'TEAM_LEADER',  'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-07'],
    // Chris Yap direct Introducers
    ['name'=>'Vincent Ho',       'code'=>'1-9',  'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-09'],
    ['name'=>'Nadia Zulkifli',   'code'=>'1-10', 'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-14'],
    ['name'=>'Chew Weng Hoong',  'code'=>'1-11', 'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-19'],
    ['name'=>'Kavitha Devi',     'code'=>'1-12', 'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-22'],
    ['name'=>'Raymond Goh',      'code'=>'1-13', 'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-01'],
    ['name'=>'Shirley Tan',      'code'=>'1-14', 'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-19'],
    ['name'=>'Mohd Asyraf',      'code'=>'1-15', 'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-06'],
    ['name'=>'Loke Boon Ping',   'code'=>'1-16', 'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-11'],
    ['name'=>'Sangeetha Raj',    'code'=>'1-17', 'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-23'],
    ['name'=>'Eric Leong',       'code'=>'1-18', 'role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Chris Yap',   'joined'=>'2026-06-09'],
    // Lim Swee Keat's Introducers
    ['name'=>'Ricky Teoh',       'code'=>'1-2-1','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Lim Swee Keat','joined'=>'2026-06-19'],
    ['name'=>'Zainab Othman',    'code'=>'1-2-2','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Lim Swee Keat','joined'=>'2026-06-16'],
    ['name'=>'Ben Sim',          'code'=>'1-2-3','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Lim Swee Keat','joined'=>'2026-06-14'],
    ['name'=>'Lily Chong',       'code'=>'1-2-4','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Lim Swee Keat','joined'=>'2026-06-07'],
    // Noor Azman's Introducers
    ['name'=>'Harish Kumar',     'code'=>'1-3-1','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Noor Azman Rashid','joined'=>'2026-06-02'],
    ['name'=>'Noraini Said',     'code'=>'1-3-2','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Noor Azman Rashid','joined'=>'2026-06-03'],
    ['name'=>'David Foo',        'code'=>'1-3-3','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Noor Azman Rashid','joined'=>'2026-06-13'],
    // Tan Boon Hwa's Introducers
    ['name'=>'Wendy Koh',        'code'=>'1-4-1','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Tan Boon Hwa','joined'=>'2026-06-12'],
    ['name'=>'Azrul Nizam',      'code'=>'1-4-2','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Tan Boon Hwa','joined'=>'2026-06-25'],
    ['name'=>'Grace Tan',        'code'=>'1-4-3','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Tan Boon Hwa','joined'=>'2026-06-05'],
    ['name'=>'Peter Yong',       'code'=>'1-4-4','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Tan Boon Hwa','joined'=>'2026-06-18'],
    // Ganesan Pillai's Introducers
    ['name'=>'Siva Subramaniam', 'code'=>'1-5-1','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Ganesan Pillai','joined'=>'2026-06-19'],
    ['name'=>'Maggie Leong',     'code'=>'1-5-2','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Ganesan Pillai','joined'=>'2026-06-13'],
    ['name'=>'Faisal Hashim',    'code'=>'1-5-3','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Ganesan Pillai','joined'=>'2026-06-02'],
    // Cindy Loh's Introducers
    ['name'=>'Annie Liew',       'code'=>'1-6-1','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Cindy Loh',  'joined'=>'2026-06-13'],
    ['name'=>'Mohd Rodzi',       'code'=>'1-6-2','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Cindy Loh',  'joined'=>'2026-06-03'],
    ['name'=>'Susan Chan',       'code'=>'1-6-3','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Cindy Loh',  'joined'=>'2026-06-14'],
    // Hafizuddin Malik's Introducers
    ['name'=>'Kelvin Yap',       'code'=>'1-7-1','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Hafizuddin Malik','joined'=>'2026-06-07'],
    ['name'=>'Rohana Kassim',    'code'=>'1-7-2','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Hafizuddin Malik','joined'=>'2026-06-23'],
    ['name'=>'Danny Teh',        'code'=>'1-7-3','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Hafizuddin Malik','joined'=>'2026-06-22'],
    ['name'=>'Priya Krishnan',   'code'=>'1-7-4','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Hafizuddin Malik','joined'=>'2026-06-01'],
    // Patricia Ong's Introducers
    ['name'=>'Jason Ong',        'code'=>'1-8-1','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Patricia Ong','joined'=>'2026-06-15'],
    ['name'=>'Fatimah Zahra',    'code'=>'1-8-2','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Patricia Ong','joined'=>'2026-06-03'],
    ['name'=>'Tommy Lim',        'code'=>'1-8-3','role'=>'INTRODUCER',   'group'=>$groupChris, 'parent'=>'Patricia Ong','joined'=>'2026-06-21'],

    // Amy Tan group (code prefix 1-1)
    ['name'=>'Amy Tan',          'code'=>'1-1',      'role'=>'GROUP_LEADER','group'=>$groupAmy,'parent'=>null,           'joined'=>'2026-06-22'],
    ['name'=>'Sarah Wong',       'code'=>'1-1-1',    'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-18'],
    ['name'=>'Kevin Ng',         'code'=>'1-1-2',    'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-07'],
    ['name'=>'Linda Chia',       'code'=>'1-1-3',    'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-25'],
    ['name'=>'Raymond Teh',      'code'=>'1-1-4',    'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-18'],
    ['name'=>'Lee Chong Wei',    'code'=>'1-1-5',    'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-06'],
    ['name'=>'Ahmad Razif',      'code'=>'1-1-6',    'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-23'],
    ['name'=>'Siti Rahimah',     'code'=>'1-1-7',    'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-12'],
    ['name'=>'Tan Ah Kow',       'code'=>'1-1-8',    'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-21'],
    ['name'=>'Wong Mei Ling',    'code'=>'1-1-9',    'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-18'],
    ['name'=>'Fong Sow Leng',    'code'=>'1-1-10',   'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-03'],
    ['name'=>'Rajesh Kumar',     'code'=>'1-1-11',   'role'=>'TEAM_LEADER', 'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-24'],
    // Amy Tan direct Introducers
    ['name'=>'Lenny Tan',        'code'=>'1-1-12',   'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-07'],
    ['name'=>'Azura Hamid',      'code'=>'1-1-13',   'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-10'],
    ['name'=>'Choo Beng Kee',    'code'=>'1-1-14',   'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-13'],
    ['name'=>'Indra Devi',       'code'=>'1-1-15',   'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-18'],
    ['name'=>'Marcus Lew',       'code'=>'1-1-16',   'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-23'],
    ['name'=>'Norzila Bt',       'code'=>'1-1-17',   'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Amy Tan',      'joined'=>'2026-06-08'],
    // Sarah Wong's Introducers
    ['name'=>'Ali Hassan',       'code'=>'1-1-1-1',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Mei Ling',         'code'=>'1-1-1-2',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Rajan Kumar',      'code'=>'1-1-1-3',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Farah Aziz',       'code'=>'1-1-1-4',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Zack Musa',        'code'=>'1-1-1-5',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Nurul Ain',        'code'=>'1-1-1-6',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Jason Lim',        'code'=>'1-1-1-7',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Priya Nair',       'code'=>'1-1-1-8',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Hafiz Shah',       'code'=>'1-1-1-9',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Cindy Tan',        'code'=>'1-1-1-10', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Bobby Chan',       'code'=>'1-1-1-11', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    ['name'=>'Siti Rahimah Jr',  'code'=>'1-1-1-12', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Sarah Wong',   'joined'=>'2026-06-22'],
    // Kevin Ng's Introducers
    ['name'=>'Ben Ooi',          'code'=>'1-1-2-1',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Grace Yeo',        'code'=>'1-1-2-2',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Amir Hamzah',      'code'=>'1-1-2-3',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Lisa Koh',         'code'=>'1-1-2-4',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Tom Wee',          'code'=>'1-1-2-5',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Rose Lau',         'code'=>'1-1-2-6',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Hadi Azman',       'code'=>'1-1-2-7',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Wendy Foo',        'code'=>'1-1-2-8',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Kelvin Sim',       'code'=>'1-1-2-9',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Nadia Yusof',      'code'=>'1-1-2-10', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Darren Kok',       'code'=>'1-1-2-11', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    ['name'=>'Suraya Malik',     'code'=>'1-1-2-12', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Kevin Ng',     'joined'=>'2026-06-22'],
    // Linda Chia's Introducers
    ['name'=>'Eric Pang',        'code'=>'1-1-3-1',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Nora Ismail',      'code'=>'1-1-3-2',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'William Hew',      'code'=>'1-1-3-3',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Jasmine Low',      'code'=>'1-1-3-4',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Fong Wei',         'code'=>'1-1-3-5',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Haslinda Bt',      'code'=>'1-1-3-6',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Chester Yap',      'code'=>'1-1-3-7',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Irene Soh',        'code'=>'1-1-3-8',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Azrul Hadi',       'code'=>'1-1-3-9',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Penny Khor',       'code'=>'1-1-3-10', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Rizal Ahmad',      'code'=>'1-1-3-11', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    ['name'=>'Connie Lim',       'code'=>'1-1-3-12', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Linda Chia',   'joined'=>'2026-06-22'],
    // Raymond Teh's Introducers
    ['name'=>'Sunny Tan',        'code'=>'1-1-4-1',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Halimah Bt',       'code'=>'1-1-4-2',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Derek Chong',      'code'=>'1-1-4-3',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Shila Hamid',      'code'=>'1-1-4-4',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Benny Hoe',        'code'=>'1-1-4-5',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Fatin Najwa',      'code'=>'1-1-4-6',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Ivan Chew',        'code'=>'1-1-4-7',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Zura Bakar',       'code'=>'1-1-4-8',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Patrick Goh',      'code'=>'1-1-4-9',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Mimi Leong',       'code'=>'1-1-4-10', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Norman Idris',     'code'=>'1-1-4-11', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    ['name'=>'Diana Putri',      'code'=>'1-1-4-12', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Raymond Teh',  'joined'=>'2026-06-22'],
    // Lee Chong Wei's Introducers
    ['name'=>'Yap Siew Ling',    'code'=>'1-1-5-1',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Lee Chong Wei','joined'=>'2026-06-22'],
    ['name'=>'Chong Wei Kiat',   'code'=>'1-1-5-2',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Lee Chong Wei','joined'=>'2026-06-22'],
    ['name'=>'Lim Boon Seng',    'code'=>'1-1-5-3',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Lee Chong Wei','joined'=>'2026-06-22'],
    // Ahmad Razif's Introducers
    ['name'=>'Farah Nadia',      'code'=>'1-1-6-1',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Ahmad Razif',  'joined'=>'2026-06-22'],
    ['name'=>'Mohd Hafiz',       'code'=>'1-1-6-2',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Ahmad Razif',  'joined'=>'2026-06-22'],
    ['name'=>'Nurul Ain B',      'code'=>'1-1-6-3',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Ahmad Razif',  'joined'=>'2026-06-22'],
    // Siti Rahimah's Introducers
    ['name'=>'Azman Hashim',     'code'=>'1-1-7-1',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Siti Rahimah', 'joined'=>'2026-06-22'],
    ['name'=>'Rohani Ismail',    'code'=>'1-1-7-2',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Siti Rahimah', 'joined'=>'2026-06-22'],
    ['name'=>'Zulkifli Omar',    'code'=>'1-1-7-3',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Siti Rahimah', 'joined'=>'2026-06-22'],
    // Tan Ah Kow's Introducers
    ['name'=>'Tan Bee Leng',     'code'=>'1-1-8-1',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Tan Ah Kow',   'joined'=>'2026-06-22'],
    ['name'=>'Ng Ah Seng',       'code'=>'1-1-8-2',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Tan Ah Kow',   'joined'=>'2026-06-22'],
    ['name'=>'Loh Chun Meng',    'code'=>'1-1-8-3',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Tan Ah Kow',   'joined'=>'2026-06-22'],
    // Wong Mei Ling's Introducers
    ['name'=>'Chan Soo Fong',    'code'=>'1-1-9-1',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Wong Mei Ling','joined'=>'2026-06-22'],
    ['name'=>'Teo Ah Mui',       'code'=>'1-1-9-2',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Wong Mei Ling','joined'=>'2026-06-22'],
    ['name'=>'Ong Bak Cheng',    'code'=>'1-1-9-3',  'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Wong Mei Ling','joined'=>'2026-06-22'],
    // Fong Sow Leng's Introducers
    ['name'=>'Lim Pei Shan',     'code'=>'1-1-10-1', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Fong Sow Leng','joined'=>'2026-06-23'],
    ['name'=>'Wong Kar Wai',     'code'=>'1-1-10-2', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Fong Sow Leng','joined'=>'2026-06-23'],
    ['name'=>'Tan Mei Hua',      'code'=>'1-1-10-3', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Fong Sow Leng','joined'=>'2026-06-23'],
    // Rajesh Kumar's Introducers
    ['name'=>'Priya Devi',       'code'=>'1-1-11-1', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Rajesh Kumar', 'joined'=>'2026-06-22'],
    ['name'=>'Suresh Pillai',    'code'=>'1-1-11-2', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Rajesh Kumar', 'joined'=>'2026-06-22'],
    ['name'=>'Kavitha Raj',      'code'=>'1-1-11-3', 'role'=>'INTRODUCER',  'group'=>$groupAmy,'parent'=>'Rajesh Kumar', 'joined'=>'2026-06-22'],

    // David Lim group (code prefix 2)
    ['name'=>'David Lim',        'code'=>'2',    'role'=>'GROUP_LEADER','group'=>$groupDavid,'parent'=>null,             'joined'=>'2025-03-10'],
    ['name'=>'Ong Cheng Huat',   'code'=>'2-1',  'role'=>'TEAM_LEADER', 'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-07'],
    ['name'=>'Nabilah Yusoff',   'code'=>'2-2',  'role'=>'TEAM_LEADER', 'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-07'],
    ['name'=>'Selvam Pillai',    'code'=>'2-3',  'role'=>'TEAM_LEADER', 'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-10'],
    ['name'=>'Florence Chin',    'code'=>'2-4',  'role'=>'TEAM_LEADER', 'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-18'],
    ['name'=>'Zamri bin Bakar',  'code'=>'2-5',  'role'=>'TEAM_LEADER', 'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-08'],
    ['name'=>'Cecilia Wong',     'code'=>'2-6',  'role'=>'TEAM_LEADER', 'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-18'],
    // David Lim direct Introducers
    ['name'=>'Faizal Izwan',     'code'=>'2-7',  'role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-16'],
    ['name'=>'Grace Koh',        'code'=>'2-8',  'role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-24'],
    ['name'=>'Hafifi Hamzah',    'code'=>'2-9',  'role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-04'],
    ['name'=>'Ivan Leong',       'code'=>'2-10', 'role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-19'],
    ['name'=>'Jumaah binti Ali', 'code'=>'2-11', 'role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-02'],
    ['name'=>'Kenneth Foo',      'code'=>'2-12', 'role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-23'],
    ['name'=>'Lalitha Suresh',   'code'=>'2-13', 'role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-05'],
    ['name'=>'Megat Zulkifli',   'code'=>'2-14', 'role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'David Lim',     'joined'=>'2026-06-03'],
    // Ong Cheng Huat's Introducers
    ['name'=>'Azlan Nordin',     'code'=>'2-1-1','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Ong Cheng Huat','joined'=>'2026-06-01'],
    ['name'=>'Ting Siew Hua',    'code'=>'2-1-2','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Ong Cheng Huat','joined'=>'2026-06-16'],
    ['name'=>'Rajan Nair',       'code'=>'2-1-3','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Ong Cheng Huat','joined'=>'2026-06-17'],
    // Nabilah Yusoff's Introducers
    ['name'=>'Hafeez Rashdan',   'code'=>'2-2-1','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Nabilah Yusoff','joined'=>'2026-06-11'],
    ['name'=>'Lim Poh Choo',     'code'=>'2-2-2','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Nabilah Yusoff','joined'=>'2026-06-18'],
    ['name'=>'Shalini Devi',     'code'=>'2-2-3','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Nabilah Yusoff','joined'=>'2026-06-16'],
    ['name'=>'Edmund Tay',       'code'=>'2-2-4','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Nabilah Yusoff','joined'=>'2026-06-07'],
    // Selvam Pillai's Introducers
    ['name'=>'Mohamad Faris',    'code'=>'2-3-1','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Selvam Pillai', 'joined'=>'2026-06-23'],
    ['name'=>'Tan Seok Ling',    'code'=>'2-3-2','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Selvam Pillai', 'joined'=>'2026-06-20'],
    ['name'=>'Vijay Kumar',      'code'=>'2-3-3','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Selvam Pillai', 'joined'=>'2026-06-03'],
    // Florence Chin's Introducers
    ['name'=>'Anuar Ariffin',    'code'=>'2-4-1','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Florence Chin', 'joined'=>'2026-06-24'],
    ['name'=>'Pang Soo Yee',     'code'=>'2-4-2','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Florence Chin', 'joined'=>'2026-06-04'],
    ['name'=>'Rashidah Salleh',  'code'=>'2-4-3','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Florence Chin', 'joined'=>'2026-06-03'],
    ['name'=>'Steven Goh',       'code'=>'2-4-4','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Florence Chin', 'joined'=>'2026-06-09'],
    // Zamri bin Bakar's Introducers
    ['name'=>'Amirul Ashraf',    'code'=>'2-5-1','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Zamri bin Bakar','joined'=>'2026-06-04'],
    ['name'=>'Doris Lau',        'code'=>'2-5-2','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Zamri bin Bakar','joined'=>'2026-06-02'],
    ['name'=>'Karuppiah a/l Mundy','code'=>'2-5-3','role'=>'INTRODUCER','group'=>$groupDavid,'parent'=>'Zamri bin Bakar','joined'=>'2026-06-20'],
    // Cecilia Wong's Introducers
    ['name'=>'Bakri Othman',     'code'=>'2-6-1','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Cecilia Wong',  'joined'=>'2026-06-19'],
    ['name'=>'Yap Hui Shan',     'code'=>'2-6-2','role'=>'INTRODUCER',  'group'=>$groupDavid,'parent'=>'Cecilia Wong',  'joined'=>'2026-06-24'],
    ['name'=>'Nalini Ramachandran','code'=>'2-6-3','role'=>'INTRODUCER','group'=>$groupDavid,'parent'=>'Cecilia Wong',  'joined'=>'2026-06-01'],
];

// Build a map of name -> agent_id for parent lookups
$nameToId = [];
$updated = 0;
$inserted = 0;

// First pass: update existing agents' codes
foreach ($agents as $a) {
    $existing = DB::table('agents')
        ->where('full_name', 'like', '%' . explode(' ', $a['name'])[0] . '%')
        ->where('group_id', $a['group'])
        ->where('role', $a['role'])
        ->first();

    if ($existing) {
        DB::table('agents')->where('agent_id', $existing->agent_id)->update([
            'agent_code' => $a['code'],
            'updated_at' => now(),
        ]);
        $nameToId[$a['name']] = $existing->agent_id;
        $updated++;
        echo "Updated: {$a['name']} → {$a['code']}\n";
    } else {
        $nameToId[$a['name']] = null; // Will be inserted in second pass
    }
}

echo "\n--- Second pass: insert new agents ---\n";

// Second pass: insert new agents
foreach ($agents as $a) {
    if (isset($nameToId[$a['name']]) && $nameToId[$a['name']] !== null) continue;

    // Get parent_id
    $parentId = null;
    if ($a['parent']) {
        $parentId = $nameToId[$a['parent']] ?? null;
        if (!$parentId) {
            // Try DB lookup
            $p = DB::table('agents')->where('full_name', 'like', '%'.explode(' ',$a['parent'])[0].'%')->where('group_id', $a['group'])->first();
            if ($p) $parentId = $p->agent_id;
        }
    }

    $agentId = (string) Str::uuid();
    $email = strtolower(str_replace(' ', '.', $a['name'])) . '@generallink.my';

    DB::table('agents')->insert([
        'agent_id'    => $agentId,
        'agent_code'  => $a['code'],
        'member_code' => $a['code'],
        'full_name'   => $a['name'],
        'email'       => $email,
        'password_hash' => bcrypt('password123'),
        'nric_encrypted' => 'PENDING',
        'phone' => '0000000000',
        'hierarchy_path' => '',
        'recruitment_blocked' => false,
        'security_phrase_set' => false,
        'failed_login_attempts' => 0,
        'role'        => $a['role'],
        'status'      => 'ACTIVE',
        'group_id'    => $a['group'],
        'parent_id'   => $parentId,
        'is_deleted'  => false,
        'created_at'  => $a['joined'],
        'updated_at'  => now(),
    ]);

    $nameToId[$a['name']] = $agentId;
    $inserted++;
    echo "Inserted: {$a['name']} ({$a['code']})\n";
}

echo "\nDone! Updated: $updated, Inserted: $inserted\n";
echo "Total agents: " . DB::table('agents')->count() . "\n";
