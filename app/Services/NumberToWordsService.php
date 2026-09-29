<?php

namespace App\Services;

// NEW 10 Sep 2026 (Task #397 follow-up, Phase 17) — per Chris: "ALL Ngo
// practice and all donor need a receipts" — every official receipt
// printed by a Malaysian NGO/temple traditionally spells the amount out
// in words ("Ringgit Malaysia Five Hundred Only") in addition to the
// figures, the same convention as a bank cheque, so there's no ambiguity
// about the amount on a physical document. Small, self-contained
// converter — no external package needed for a number range no
// association's single receipt will ever exceed.
class NumberToWordsService
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    /**
     * "Ringgit Malaysia Five Hundred and Fifty Only" / "...Five Hundred
     * and Fifty Sen Only" style output, matching the standard wording
     * printed on Malaysian cheques and official receipts.
     */
    public static function ringgit(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $ringgit = (int) floor($amount);
        $sen = (int) round(($amount - $ringgit) * 100);

        $words = 'Ringgit Malaysia '.($ringgit > 0 ? self::convert($ringgit) : 'Zero');
        if ($sen > 0) {
            $words .= ' and '.self::convert($sen).' Sen';
        }

        return $words.' Only';
    }

    private static function convert(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];
        if ($number >= 1000000) {
            $parts[] = self::convert(intdiv($number, 1000000)).' Million';
            $number %= 1000000;
        }
        if ($number >= 1000) {
            $parts[] = self::convert(intdiv($number, 1000)).' Thousand';
            $number %= 1000;
        }
        if ($number >= 100) {
            $parts[] = self::ONES[intdiv($number, 100)].' Hundred';
            $number %= 100;
        }
        if ($number > 0) {
            if (! empty($parts)) {
                $parts[] = 'and';
            }
            if ($number < 20) {
                $parts[] = self::ONES[$number];
            } else {
                $tensWord = self::TENS[intdiv($number, 10)];
                $onesWord = self::ONES[$number % 10];
                $parts[] = $onesWord ? $tensWord.'-'.$onesWord : $tensWord;
            }
        }

        return implode(' ', $parts);
    }
}
