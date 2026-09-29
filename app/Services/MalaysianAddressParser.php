<?php

namespace App\Services;

/**
 * NEW 18 Jul 2026 — splits a Malaysian mailing address into its
 * address/postcode/city/state parts. Needed by the position-based
 * document calibration engine (CoordinateCalibrationService reads a
 * customer's address off a real policy as one merged multi-line
 * string; the customers table has separate postcode/city/state
 * columns that were sitting unused until now), and reused by
 * Shared\CustomerController for its state dropdown so there's a single
 * list of valid states instead of two copies that could drift apart.
 *
 * Malaysian addresses reliably end with "<5-digit postcode> <city>
 * <state>" on their own line/segment — e.g. "42200 KAPAR SELANGOR".
 * This looks for that pattern from the END of the string backwards
 * (state name, then postcode), which is far more reliable than trying
 * to parse from the front, since the number of address lines before
 * the postcode varies.
 */
class MalaysianAddressParser
{
    public const STATES = [
        'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang',
        'Perak', 'Perlis', 'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor',
        'Terengganu', 'Kuala Lumpur', 'Labuan', 'Putrajaya',
    ];

    /**
     * @return array{address: ?string, postcode: ?string, city: ?string, state: ?string}
     */
    public static function parse(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        if ($raw === '') {
            return ['address' => null, 'postcode' => null, 'city' => null, 'state' => null];
        }

        // Find whichever known state name appears CLOSEST to the end
        // of the string — real addresses sometimes have the state
        // abbreviated or written with slightly different spacing, but
        // matching against the exact known list avoids ever grabbing
        // an unrelated word as if it were a state.
        $stateFound = null;
        $stateAt = null;
        foreach (self::STATES as $state) {
            $pattern = '/\b' . preg_quote(strtoupper($state), '/') . '\b/';
            if (preg_match($pattern, strtoupper($raw), $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                if ($stateAt === null || $pos > $stateAt) {
                    $stateAt = $pos;
                    $stateFound = $state;
                }
            }
        }

        if ($stateFound === null) {
            // No recognisable state — can't reliably split anything;
            // return the whole thing as the address rather than guess.
            return ['address' => $raw, 'postcode' => null, 'city' => null, 'state' => null];
        }

        $beforeState = trim(substr($raw, 0, $stateAt));

        // Postcode: the LAST 5-digit number appearing before the state.
        if (!preg_match('/(\d{5})\s*(.*)$/', $beforeState, $m)) {
            // State found but no postcode — still better than nothing.
            return ['address' => $beforeState !== '' ? $beforeState : null, 'postcode' => null, 'city' => null, 'state' => $stateFound];
        }

        $postcode = $m[1];
        $city = trim($m[2]);
        $address = trim(substr($beforeState, 0, strpos($beforeState, $postcode)));

        return [
            'address'  => $address !== '' ? $address : null,
            'postcode' => $postcode,
            'city'     => $city !== '' ? $city : null,
            'state'    => $stateFound,
        ];
    }
}
