<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

// NEW 22 Aug 2026 — per Chris: imports the real master list of Malaysian
// Taoist temples (马来西亚道教总会_州分类整理_已处理-GLADE.xlsx, 591 rows
// across 13 state sheets) into the real cbe_hierarchy_nodes tree —
// 1 HQ node -> 13 State nodes -> 591 Temple nodes, each Temple linked to
// its State via parent_node_id so the drill-down (HQ -> State -> Temple
// -> Member) works with real data instead of duplicated records.
//
// node_code follows the format locked with Chris: {STATE-3}-{CITY-3}-
// {TEMPLE-4}, e.g. SGR-KLA-0001. City is a plain descriptive field on
// the Temple node (not its own hierarchy level, per Chris's Klang
// discussion — Meru/Kapar/Pulau Ketam/Klang City/Pelabuhan Klang are
// browsing/reporting groupings only, not separate governance nodes).
//
// The source file has real-world messiness in the City column — same
// place spelled in different cases, a few typos, and a couple of
// redundant "X, State" values. canonicalizeCity() below cleans that up
// so temples that are really in the same place end up grouped under one
// City value instead of splintering into near-duplicates. This was
// checked against the full 591-row file before writing this command —
// every row produces a unique node_code, no collisions.
class ImportCbeTemples extends Command
{
    protected $signature = 'cbe:import-temples {--fresh : Delete any previously imported nodes for this group first, then reimport}';
    protected $description = 'Import the 591-temple Taoist Federation master list into cbe_hierarchy_nodes';

    private const GROUP_NAME = '马来西亚道教总会';
    private const GROUP_NAME_EN = 'Malaysia Taoist Association (Federation)';

    private const STATE_CODE = [
        'Johor' => 'JHR', 'Kedah' => 'KDH', 'Kelantan' => 'KTN', 'Kuala Lumpur' => 'KUL',
        'Melaka' => 'MLK', 'Negeri Sembilan' => 'NSN', 'Pahang' => 'PHG', 'Perak' => 'PRK',
        'Perlis' => 'PLS', 'Pulau Pinang' => 'PNG', 'Sabah' => 'SBH', 'Sarawak' => 'SWK',
        'Selangor' => 'SGR',
    ];

    private const STATE_NAME_ZH = [
        'Johor' => '柔佛', 'Kedah' => '吉打', 'Kelantan' => '吉兰丹', 'Kuala Lumpur' => '吉隆坡',
        'Melaka' => '马六甲', 'Negeri Sembilan' => '森美兰', 'Pahang' => '彭亨', 'Perak' => '霹雳',
        'Perlis' => '玻璃市', 'Pulau Pinang' => '槟城', 'Sabah' => '沙巴', 'Sarawak' => '砂拉越',
        'Selangor' => '雪兰莪',
    ];

    // Known data-entry typos/redundancies found in the source file, fixed
    // so the same real place doesn't end up as two different City values.
    // Keys are UPPERCASE after trimming/comma-splitting.
    private const CITY_FIX = [
        'PERLABUHAN KLANG' => 'PELABUHAN KLANG',
        'PETAING JAYA' => 'PETALING JAYA',
        'KLANG SELANGOR' => 'KLANG',
        'ULU SELANGOR' => 'HULU SELANGOR',
        'K.L' => 'KUALA LUMPUR',
        'GEORGETOWN' => 'GEORGE TOWN',
        'ISKANDA PUTERI JOHOR BAHRU' => 'ISKANDAR PUTERI JOHOR BAHRU',
    ];

    public function handle(): int
    {
        $path = storage_path('app/imports/tao-temples.xlsx');
        if (! file_exists($path)) {
            $this->error("File not found: $path");
            return self::FAILURE;
        }

        $groupLabelId = DB::table('group_labels')->where('group_name', self::GROUP_NAME)->value('group_label_id');

        if (! $groupLabelId) {
            $groupLabelId = (string) Str::uuid();
            DB::table('group_labels')->insert([
                'group_label_id' => $groupLabelId,
                'group_name'     => self::GROUP_NAME,
                'description'    => 'Imported ' . now()->format('d M Y') . ' from the official Taoist temple master list (591 temples, 13 states).',
                'group_type'     => 'CBE',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $this->info('Created group_label: ' . self::GROUP_NAME);
        }

        $existingHq = DB::table('cbe_hierarchy_nodes')
            ->where('group_label_id', $groupLabelId)
            ->where('node_code', 'HQ')
            ->first();

        if ($existingHq && ! $this->option('fresh')) {
            $this->error('This group already has an imported tree (HQ node exists). Run again with --fresh to wipe and reimport.');
            return self::FAILURE;
        }

        if ($existingHq && $this->option('fresh')) {
            $this->warn('Removing previously imported nodes for this group...');
            // Children before parents — parent_node_id FK is onDelete('restrict').
            DB::table('cbe_hierarchy_nodes')->where('group_label_id', $groupLabelId)->where('parent_node_id', '!=', $existingHq->node_id)
                ->whereIn('parent_node_id', function ($q) use ($groupLabelId) {
                    $q->select('node_id')->from('cbe_hierarchy_nodes')->where('group_label_id', $groupLabelId);
                })->delete();
            DB::table('cbe_hierarchy_nodes')->where('group_label_id', $groupLabelId)->where('node_id', '!=', $existingHq->node_id)->delete();
            DB::table('cbe_hierarchy_nodes')->where('node_id', $existingHq->node_id)->delete();
        }

        $this->info('Reading ' . $path . ' ...');
        $spreadsheet = IOFactory::load($path);

        DB::beginTransaction();
        try {
            // --- Levels: HQ(1) -> State(2) -> Temple(3) ---
            $levelIds = [];
            foreach (['HQ' => 1, 'State' => 2, 'Temple' => 3] as $name => $order) {
                $levelId = DB::table('cbe_hierarchy_levels')
                    ->where('group_label_id', $groupLabelId)->where('level_order', $order)->value('level_id');
                if (! $levelId) {
                    $levelId = (string) Str::uuid();
                    DB::table('cbe_hierarchy_levels')->insert([
                        'level_id' => $levelId, 'group_label_id' => $groupLabelId,
                        'level_order' => $order, 'level_name' => $name,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $levelIds[$name] = $levelId;
            }

            // --- HQ node ---
            $hqId = (string) Str::uuid();
            DB::table('cbe_hierarchy_nodes')->insert([
                'node_id' => $hqId, 'group_label_id' => $groupLabelId, 'level_id' => $levelIds['HQ'],
                'parent_node_id' => null, 'node_code' => 'HQ',
                'node_name' => self::GROUP_NAME_EN, 'node_name_zh' => self::GROUP_NAME,
                'city' => null, 'postcode' => null, 'address' => null,
                'contact_person_1' => null, 'contact_person_2' => null,
                'external_reference_no' => null, 'hierarchy_path' => "/$hqId/",
                'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $stateCounts = [];
            $totalTemples = 0;

            foreach (self::STATE_CODE as $sheetName => $stateCode) {
                $sheet = $spreadsheet->getSheetByName($sheetName);
                if (! $sheet) {
                    $this->warn("Sheet not found, skipped: $sheetName");
                    continue;
                }

                // --- State node ---
                $stateId = (string) Str::uuid();
                DB::table('cbe_hierarchy_nodes')->insert([
                    'node_id' => $stateId, 'group_label_id' => $groupLabelId, 'level_id' => $levelIds['State'],
                    'parent_node_id' => $hqId, 'node_code' => $stateCode,
                    'node_name' => $sheetName, 'node_name_zh' => self::STATE_NAME_ZH[$sheetName] ?? null,
                    'city' => null, 'postcode' => null, 'address' => null,
                    'contact_person_1' => null, 'contact_person_2' => null,
                    'external_reference_no' => null, 'hierarchy_path' => "/$hqId/$stateId/",
                    'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);

                $usedCodesInState = [];
                $counterPerCityCode = [];
                $rowCount = 0;
                $highestRow = $sheet->getHighestRow();

                for ($r = 2; $r <= $highestRow; $r++) {
                    $serial = $sheet->getCell("A$r")->getValue();
                    $nameZh = trim((string) $sheet->getCell("B$r")->getValue());
                    $nameEn = trim((string) $sheet->getCell("C$r")->getValue());
                    $addr = trim((string) $sheet->getCell("D$r")->getValue());
                    $postcode = trim((string) $sheet->getCell("E$r")->getValue());
                    $cityRaw = (string) $sheet->getCell("F$r")->getValue();
                    $tel1Raw = trim((string) $sheet->getCell("H$r")->getValue());
                    $tel2Raw = trim((string) $sheet->getCell("I$r")->getValue());
                    $cp1 = trim((string) $sheet->getCell("J$r")->getValue());
                    $cp2 = trim((string) $sheet->getCell("K$r")->getValue());
                    $serial2 = trim((string) $sheet->getCell("L$r")->getValue());

                    if ($serial === null && $nameZh === '' && $nameEn === '') {
                        continue; // blank row
                    }

                    $nodeName = $nameEn !== '' ? $nameEn : $nameZh; // node_name is required
                    $city = $this->canonicalizeCity($cityRaw);
                    $cityCode = $this->cityCode($city, $usedCodesInState);
                    $usedCodesInState[$cityCode] = $city;

                    $counterPerCityCode[$cityCode] = ($counterPerCityCode[$cityCode] ?? 0) + 1;
                    $seq = str_pad((string) $counterPerCityCode[$cityCode], 4, '0', STR_PAD_LEFT);
                    $nodeCode = "$stateCode-$cityCode-$seq";

                    $templeId = (string) Str::uuid();
                    DB::table('cbe_hierarchy_nodes')->insert([
                        'node_id' => $templeId, 'group_label_id' => $groupLabelId, 'level_id' => $levelIds['Temple'],
                        'parent_node_id' => $stateId, 'node_code' => $nodeCode,
                        'node_name' => $nodeName, 'node_name_zh' => $nameZh !== '' ? $nameZh : null,
                        'city' => $city !== '' ? $city : null,
                        'postcode' => $postcode !== '' ? $postcode : null,
                        'address' => $addr !== '' ? $addr : null,
                        'contact_person_1' => $cp1 !== '' ? $cp1 : null,
                        'contact_person_2' => $cp2 !== '' ? $cp2 : null,
                        'external_reference_no' => $serial2 !== '' ? $serial2 : null,
                        'hierarchy_path' => "/$hqId/$stateId/$templeId/",
                        'display_order' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);

                    // Telephone 1 and Telephone 2 cells can each hold more
                    // than one real number (a couple of temples in the
                    // source file have 2 or 3 numbers jammed into one
                    // cell, sometimes with a "(name)" note) — split each
                    // cell into individual phone rows rather than storing
                    // one long unusable string.
                    $order = 0;
                    foreach ([$tel1Raw, $tel2Raw] as $rawCell) {
                        foreach ($this->parsePhoneNumbers($rawCell) as $phone) {
                            DB::table('cbe_hierarchy_node_phones')->insert([
                                'phone_id' => (string) Str::uuid(),
                                'node_id' => $templeId,
                                'phone_number' => $phone['number'],
                                'contact_note' => $phone['note'],
                                'display_order' => $order++,
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                    }

                    $rowCount++;
                    $totalTemples++;
                }

                $stateCounts[$sheetName] = $rowCount;
                $this->info(sprintf('%-18s %s  (%d temples)', $sheetName, $stateCode, $rowCount));
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed, rolled back: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Done — 1 HQ node, 13 State nodes, $totalTemples Temple nodes imported.");
        if ($totalTemples !== 591) {
            $this->warn("Expected 591 temples, got $totalTemples — please check the counts above against your Excel file.");
        }

        return self::SUCCESS;
    }

    private function canonicalizeCity(string $raw): string
    {
        $c = trim($raw);
        if ($c === '') {
            return '';
        }
        $c = preg_replace('/\s+/', ' ', $c);
        if (str_contains($c, ',')) {
            $c = trim(explode(',', $c)[0]);
        }
        $upper = strtoupper($c);
        $upper = self::CITY_FIX[$upper] ?? $upper;
        return ucwords(strtolower($upper));
    }

    // Splits a raw Telephone 1/2 cell into individual real numbers. Most
    // cells hold exactly one number and come back as a single-item array.
    // A couple of temples in the source file jam 2-3 numbers into one
    // cell, sometimes with a "(name)" note next to a number (e.g. "(苏)"
    // — the contact's surname) — each number becomes its own row, with
    // that note attached only to the number it actually follows.
    private function parsePhoneNumbers(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        preg_match_all('/0\d{1,2}[-\s]?\d{3,4}[-\s]?\d{3,4}/', $raw, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            // Couldn't recognize a number shape — keep the raw text
            // rather than silently dropping it.
            return [['number' => $raw, 'note' => null]];
        }

        $results = [];
        $count = count($matches[0]);
        foreach ($matches[0] as $i => [$number, $offset]) {
            $endOfNumber = $offset + strlen($number);
            $nextStart = ($i + 1 < $count) ? $matches[0][$i + 1][1] : strlen($raw);
            $between = substr($raw, $endOfNumber, max(0, $nextStart - $endOfNumber));

            $note = null;
            if (preg_match('/[\(（]([^\)）]*)[\)）]/u', $between, $noteMatch)) {
                $note = trim($noteMatch[1]) !== '' ? trim($noteMatch[1]) : null;
            }

            $results[] = ['number' => trim($number), 'note' => $note];
        }

        return $results;
    }

    private function cityCode(string $city, array &$usedInState): string
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $city));
        $base = str_pad(substr($letters, 0, 3), 3, 'X');

        if (! isset($usedInState[$base]) || $usedInState[$base] === $city) {
            return $base;
        }

        for ($i = 2; $i <= 9; $i++) {
            $cand = str_pad(substr($letters, 0, 2) . $i, 3, 'X');
            if (! isset($usedInState[$cand]) || $usedInState[$cand] === $city) {
                return $cand;
            }
        }

        // Extremely unlikely fallback — random-suffixed, still deterministic-length.
        return str_pad(substr($letters, 0, 1), 2, 'X') . random_int(0, 9);
    }
}
