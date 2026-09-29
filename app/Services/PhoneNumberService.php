<?php

namespace App\Services;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Chris asked for ONE standard phone number format
// across the whole app, accepted by EspoCRM, with bad input rejected
// everywhere (not just on some forms). Before this, validation was
// inconsistent: most forms had no phone format check at all, a couple
// used a mobile-only regex (AuthController registration), and only
// SpecialGroupController had the correct mobile+landline check (added
// 24 Jul 2026 after Chris hit a real bug there with a KL landline).
//
// This service centralizes that one correct rule (lifted from
// SpecialGroupController::phoneRule(), unchanged) so every controller
// in the app validates and stores phone numbers the same way, instead
// of each screen inventing (or skipping) its own check.
//
// Two different jobs, on purpose:
//   - isValid()/rule()/nullableRule()  -> validation, used in every
//     $request->validate([...]) call that has a phone field.
//   - normalize()                      -> what actually gets SAVED to
//     the database. Matches the exact "+6" + original local formatting
//     convention already applied to existing Customer/Agent/Vendor rows
//     by the one-time `phones:add-country-code` backfill command, so
//     old and new data look and sort the same way everywhere (Customer
//     list, Agent profile, Vendor Maintenance, etc).
//
// EspoCRM itself is unaffected by this file — EspoCrmService has its
// own separate sanitizePhone() that converts whatever is stored here
// into strict E.164 (e.g. "+60166860771") purely for the EspoCRM API
// call, since EspoCRM's phoneNumber field requires that stricter format.
// -------------------------------------------------------
class PhoneNumberService
{
    /**
     * True if $raw is a recognizable Malaysian mobile or landline number,
     * with or without a +60 / 60 / 0060 country code, with or without
     * dashes/spaces. Same pattern SpecialGroupController has used since
     * 24 Jul 2026: local leading 0, then 1-9, then 7-9 more digits
     * (9-11 digits total including the leading 0) — covers both mobile
     * (01X-XXXXXXX) and landline (0X-XXXXXXX) numbers.
     */
    public static function isValid(?string $raw): bool
    {
        if ($raw === null || trim($raw) === '') {
            return false;
        }

        return (bool) preg_match('/^0[1-9][0-9]{7,9}$/', self::localDigits($raw));
    }

    /**
     * Strip everything down to the local digit form (leading 0, no
     * dashes/spaces, no country code) purely for validation purposes.
     */
    private static function localDigits(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (str_starts_with($digits, '0060')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '60') && strlen($digits) > 9) {
            $digits = substr($digits, 2);
        }

        if ($digits !== '' && !str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    /**
     * What gets stored in the database. Standard is "+6" followed by the
     * local number, e.g. "016-6860771" -> "+6016-6860771" — same
     * convention the phones:add-country-code backfill already applied to
     * existing records. Only call this on input that has already passed
     * isValid().
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '+')) {
            return $trimmed; // already in the standard format
        }

        $digits = preg_replace('/\D/', '', $trimmed) ?? '';

        if (str_starts_with($digits, '0060')) {
            return '+60' . substr($digits, 4);
        }
        if (str_starts_with($digits, '60') && strlen($digits) > 9) {
            return '+60' . substr($digits, 2);
        }

        // Typical case: agent typed local format, e.g. "016-6860771" —
        // keep their original formatting (dashes/spaces), just add +6.
        return '+6' . $trimmed;
    }

    /**
     * Validation closure for a REQUIRED phone field, e.g.:
     *   'phone' => ['required', 'string', PhoneNumberService::rule()],
     */
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (!self::isValid((string) $value)) {
                $fail('The :attribute must be a valid Malaysian mobile or landline number (e.g. 012-3456789 or 03-22723932, with or without the +60 country code).');
            }
        };
    }

    /**
     * Same check, but skips validation entirely when the field is blank —
     * for OPTIONAL phone fields (e.g. Beneficiary phone).
     */
    public static function nullableRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value === null || trim((string) $value) === '') {
                return;
            }
            if (!self::isValid((string) $value)) {
                $fail('The :attribute must be a valid Malaysian mobile or landline number (e.g. 012-3456789 or 03-22723932, with or without the +60 country code).');
            }
        };
    }
}
