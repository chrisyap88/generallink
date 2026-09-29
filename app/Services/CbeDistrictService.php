<?php

namespace App\Services;

// NEW 26 Aug 2026 — per Chris: "the branch suppose to show the city call
// klang and klang consists of 5 area, klang, Pelabuhan Klang, Kapar,
// Meru, Pulau Ketam." Tao's real cbe_hierarchy_levels only go 3 deep
// (HQ -> State -> Temple) — there is no real 4th "Branch" level in the
// data. What Chris actually means by "Branch" is a real-world district
// grouping several individual town/city values (stored on the Temple
// node's own `city` column) into one combined selectable area, so
// "Klang" district shows as ONE entry covering every temple across its
// constituent towns, not 5 separate tiny entries.
//
// This is fixed Malaysian administrative geography (which towns belong
// to which district), not a per-client business figure like a fee or a
// level name — so a small static mapping here is appropriate and safe
// to hardcode, unlike level names/counts which must stay data-driven.
// Extend DISTRICTS as more districts are needed; any city NOT listed
// here simply stands alone as its own single-city group — nothing
// breaks or disappears for cities we haven't mapped yet.
class CbeDistrictService
{
    private const DISTRICTS = [
        'Klang' => ['Klang', 'Pelabuhan Klang', 'Kapar', 'Meru', 'Pulau Ketam'],
    ];

    /**
     * Fold a flat list of city values into district groups. Cities that
     * belong to a known district are combined under that district's
     * name; everything else stands alone as its own single-city group.
     *
     * @param  string[]  $cities
     * @return array<string, string[]> district/city name => member city values
     */
    public static function groupCities(array $cities): array
    {
        $used = [];
        $result = [];

        foreach (self::DISTRICTS as $district => $members) {
            $present = array_values(array_intersect($members, $cities));
            if (! empty($present)) {
                $result[$district] = $present;
                foreach ($present as $c) {
                    $used[$c] = true;
                }
            }
        }

        foreach ($cities as $c) {
            if (! isset($used[$c])) {
                $result[$c] = [$c];
            }
        }

        return $result;
    }

    /**
     * The city values that belong to a given district name (or, for an
     * unmapped name, just that single city itself).
     *
     * @return string[]
     */
    public static function citiesInDistrict(string $district): array
    {
        return self::DISTRICTS[$district] ?? [$district];
    }
}
