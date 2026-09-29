<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 1. Rule-based (no external AI API) digital
// PDF text extraction for bank statements, per Chris's confirmed scope:
// digital-text PDFs only, not scanned images — if a PDF has no
// extractable text this returns ['ok' => false, 'reason' => 'no_text']
// rather than guessing.
//
// Honest limitation, disclosed to Chris up front: Malaysian bank
// statement layouts vary a great deal (Maybank, CIMB, Public Bank, RHB,
// Hong Leong and others all format their PDFs differently). This parser
// uses generic, conservative pattern-matching — a line is only treated
// as a transaction if it starts with something that looks like a date
// AND ends with something that looks like an amount. It will not
// recognise every bank's layout perfectly on the first try; every
// extracted line keeps its raw original text and a 0-100 confidence
// score specifically so a human reviews low-confidence lines before
// anything is committed, and so the patterns below can be tuned against
// Chris's actual statements once real ones are uploaded.
class PdfStatementExtractionService
{
    private const BANK_NAMES = [
        'MAYBANK' => 'Maybank', 'MALAYAN BANKING' => 'Maybank',
        'CIMB' => 'CIMB Bank', 'PUBLIC BANK' => 'Public Bank',
        'RHB' => 'RHB Bank', 'HONG LEONG BANK' => 'Hong Leong Bank',
        'AMBANK' => 'AmBank', 'AM BANK' => 'AmBank',
        'BANK ISLAM' => 'Bank Islam', 'BSN' => 'Bank Simpanan Nasional',
        'BANK SIMPANAN NASIONAL' => 'Bank Simpanan Nasional',
        'ALLIANCE BANK' => 'Alliance Bank', 'UOB' => 'UOB',
        'OCBC' => 'OCBC Bank', 'HSBC' => 'HSBC Bank',
        'STANDARD CHARTERED' => 'Standard Chartered', 'AFFIN BANK' => 'Affin Bank',
        'BANK RAKYAT' => 'Bank Rakyat', 'BANK MUAMALAT' => 'Bank Muamalat',
    ];

    /**
     * Parse an uploaded PDF file. Returns a structured array — never
     * throws for a "this PDF has no usable text" case, since that is an
     * expected, disclosed limitation rather than an error.
     */
    public function parseFile(string $absolutePath): array
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($absolutePath);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'unreadable', 'message' => $e->getMessage()];
        }

        $pages = $pdf->getPages();
        $pageTexts = [];
        foreach ($pages as $page) {
            $pageTexts[] = $page->getText();
        }
        $fullText = implode("\n", $pageTexts);

        if (trim($fullText) === '' || mb_strlen(trim($fullText)) < 20) {
            // No extractable text — almost certainly a scanned/photographed
            // statement saved as PDF, which this rule-based, no-OCR scope
            // deliberately does not attempt to read.
            return ['ok' => false, 'reason' => 'no_text'];
        }

        // FIXED 16 Sep 2026 — per Chris: a real AmBank statement (the one
        // used to build the "auto-fill statement password" feature) came
        // back with 0 lines found, no detected account number, and no
        // statement period, even though it unlocked and parsed fine.
        // Root cause #1: the underlying PDF text library (Smalot
        // PdfParser) renders the space between words in this statement's
        // bilingual labels as a NON-BREAKING space (U+00A0), not a
        // regular space — invisible on screen, but a literal " " in a
        // regex pattern never matches it, so every label-based regex
        // below silently failed on every bilingual label ("ACCOUNT NO. /
        // NO. AKAUN", "OPENING BALANCE / BAKI PEMBUKAAN", etc). Normalised
        // to a regular space here, once, so every regex in this class
        // works the same whether the source PDF used a normal or a
        // non-breaking space.
        $fullText = str_replace("\xC2\xA0", ' ', $fullText);

        $lines = [];
        foreach ($pageTexts as $pageIndex => $text) {
            $text = str_replace("\xC2\xA0", ' ', $text);
            foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $lines[] = ['page' => $pageIndex + 1, 'text' => $line];
                }
            }
        }

        $period = $this->detectStatementPeriod($fullText);
        // UPDATED 10 Sep 2026 (Task #397 follow-up) — Chris's actual
        // AmBank statements print each transaction line as "12Jan" (day +
        // month abbreviation, no year, no separator) rather than a full
        // date — the year is only stated once, in the statement period
        // above. Threaded through to extractTransactionLines() so a
        // year-less line date can still be completed into a real date.
        $fallbackYear = $period[0] ? (int) substr($period[0], 0, 4) : null;
        $openingBalance = $this->detectBalance($fullText, ['OPENING BALANCE', 'BALANCE B/F', 'BAKI BAWA KE HADAPAN', 'BAKI AWAL']);

        return [
            'ok' => true,
            'page_count' => count($pages),
            'bank_name' => $this->detectBankName($fullText),
            'account_number' => $this->detectAccountNumber($fullText),
            'period' => $period,
            'opening_balance' => $openingBalance,
            // FIXED 16 Sep 2026 — 'BAKI BAWA KE HADAPAN' ("balance carried
            // forward") used to be listed as a valid CLOSING balance label
            // too, but it's the Malay term for the OPENING line ("Baki
            // Bawa Ke Hadapan / Balance b/f"). On Chris's real statement
            // this caused the closing balance to be silently recorded as
            // the balance after the FIRST transaction instead of the
            // actual period-end balance. Removed from this list, and the
            // real Malay closing label ("BAKI PENUTUPAN") added.
            'closing_balance' => $this->detectBalance($fullText, ['CLOSING BALANCE', 'BALANCE C/F', 'BAKI AKHIR', 'BAKI PENUTUPAN', 'ENDING BALANCE']),
            'lines' => $this->extractTransactionLines($lines, $fallbackYear, $openingBalance),
        ];
    }

    private function detectBankName(string $text): ?string
    {
        $upper = mb_strtoupper($text);
        foreach (self::BANK_NAMES as $needle => $label) {
            if (str_contains($upper, $needle)) {
                return $label;
            }
        }
        return null;
    }

    private function detectAccountNumber(string $text): ?string
    {
        // FIXED 16 Sep 2026 — real statements print this as a bilingual
        // label — "ACCOUNT NO. / NO. AKAUN : 8881047161769" — and the
        // library used to extract that label's own English/Malay pairing
        // and the ": value" on a SEPARATE line from the label entirely.
        // The old pattern only allowed whitespace between the label and
        // the value, so it silently matched nothing on a real statement.
        // Widened to allow up to 80 characters of anything (including a
        // line break) between the label and the value — enough to skip
        // over a second bilingual label, but short enough not to
        // accidentally reach into an unrelated number further down the
        // page. "Account No", "Account Number", "No. Akaun", "A/C No"
        // followed eventually by a run of 8-20 digits (with optional
        // spaces/dashes stripped).
        if (preg_match('/(?:account\s*(?:no\.?|number)|a\/c\s*no\.?|no\.?\s*akaun)[\s\S]{0,80}?[:\-]\s*([0-9][0-9\s\-]{6,20}[0-9])/iu', $text, $m)) {
            return preg_replace('/[\s\-]/', '', $m[1]);
        }
        return null;
    }

    private function detectStatementPeriod(string $text): array
    {
        // UPDATED 10 Sep 2026 (Task #397 follow-up) — Chris's actual
        // AmBank statement labels this "STATEMENT DATE / TARIKH PENYATA",
        // not "Statement Period / Tempoh Penyata" as originally guessed.
        // Widened to accept either wording so real statements aren't
        // silently left with a null period.
        // FIXED 16 Sep 2026 — same bilingual-label/line-break gap issue
        // as detectAccountNumber() above ("STATEMENT DATE / TARIKH
        // PENYATA" then ": 01/01/2024 - 31/01/2024" on the next line) —
        // widened the same way.
        if (preg_match('/(?:statement\s*(?:period|date)|tarikh\s*penyata|tempoh\s*penyata)[\s\S]{0,80}?[:\-]?\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})\s*(?:to|-|hingga|until)\s*([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{2,4})/iu', $text, $m)) {
            return [$this->normalizeDate($m[1]), $this->normalizeDate($m[2])];
        }
        return [null, null];
    }

    private function detectBalance(string $text, array $labels): ?float
    {
        foreach ($labels as $label) {
            $labelPat = preg_quote($label, '/');
            // FIXED 16 Sep 2026 — same bilingual-label gap issue as above,
            // applied here too (e.g. "OPENING BALANCE / BAKI PEMBUKAAN").
            // ALSO: on Chris's real statement this summary table's value
            // sometimes prints BEFORE its label on the same line instead
            // of after it ("34,126.11 OPENING BALANCE / BAKI PEMBUKAAN")
            // — a quirk of how the source PDF's table columns get
            // flattened to plain text. Both orders are now tried; "after"
            // first since it's the more common layout industry-wide.
            if (preg_match('/'.$labelPat.'[\s\S]{0,80}?[:\-]?\s*(?:RM)?\s*([0-9][0-9,]*\.[0-9]{2})/iu', $text, $m)) {
                return (float) str_replace(',', '', $m[1]);
            }
            if (preg_match('/([0-9][0-9,]*\.[0-9]{2})\s*(?:RM)?\s*'.$labelPat.'/iu', $text, $m)) {
                return (float) str_replace(',', '', $m[1]);
            }
        }
        return null;
    }

    /**
     * A "transaction line" is any line that starts with something that
     * parses as a date and contains at least one amount-shaped number.
     * Conservative on purpose — a line that doesn't clearly fit this
     * shape is left out of the extraction rather than guessed at, per
     * spec section 21 (never fabricate to complete a transaction).
     */
    private function extractTransactionLines(array $lines, ?int $fallbackYear = null, ?float $openingBalance = null): array
    {
        // UPDATED 10 Sep 2026 (Task #397 follow-up) — added a day+month,
        // no-year alternative ("12Jan", with or without a space) to match
        // Chris's actual AmBank statement format, alongside the
        // originally-assumed full-date formats other banks may use.
        $dateToken = '(?:[0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4}|[0-9]{1,2}\s*[A-Za-z]{3,9}\s+[0-9]{2,4}|[0-9]{1,2}\s*[A-Za-z]{3,9}|[0-9]{6})';
        // FIXED 16 Sep 2026 — per Chris: 0 lines found on a real AmBank
        // statement despite it unlocking and parsing fine. Root cause #2
        // (#1 was the non-breaking-space label bug fixed above): the old
        // pattern required the DATE to be the very first thing on the
        // line ("^date"). On this statement the extracted text glues
        // THIS row's own running balance directly onto the FRONT of the
        // date with no space at all — e.g. one single line reads
        // "33,826.1112Jan INW AMB CHQ /CHQ PRESENTED , , 524626,
        // 524626[tab]300.00" (balance "33,826.11" + date "12Jan" +
        // description + one trailing amount, glued together because the
        // source PDF's BALANCE column happens to be drawn before the
        // DATE column internally, even though it prints visually after
        // it). Every one of these lines failed the old ^-anchored check
        // and was silently skipped. Now captures that optional leading
        // balance separately instead of requiring the date to start the
        // line.
        $lineStart = '/^([0-9][0-9,]*\.[0-9]{2})?\s*('.$dateToken.')\b(.*)$/u';
        $amountAnywhere = '/(-?[0-9][0-9,]*\.[0-9]{2})\s*(DR|CR)?/i';

        $out = [];
        $lineNo = 0;
        // Seeded from the statement's own detected opening balance, then
        // updated after every row (transaction or not) that carries its
        // own balance — used below to work out debit vs credit on rows
        // that print only ONE trailing amount with no DR/CR marker at
        // all, which is this statement's format for every line.
        $previousBalance = $openingBalance;

        // FIXED 16 Sep 2026 — per Chris: even after the two fixes above,
        // one real month (August) still came back with 0 lines despite
        // having a genuine RM5.00 deposit that month. Root cause #3: a
        // long description/reference wraps this statement's transaction
        // row across up to 3 separate physical lines, with the trailing
        // amount landing on its OWN line with nothing else on it —
        // e.g. "29,331.3102Aug CASH DEPOSIT CRM /CRM CSH DEP, , J016,"
        // then "0052" then "5.00" as three consecutive lines, all one
        // transaction. The old loop only ever looked at a single line,
        // found no amount on the date line itself, and silently gave up.
        // Switched from foreach to an index-based loop so a row that
        // starts correctly (balance + date) but has no amount yet can
        // pull in up to 3 more following lines and keep checking — but
        // ONLY while none of those lines themselves look like the start
        // of a fresh transaction row, so a genuinely blank/non-amount row
        // (or the next real transaction) is never swallowed by mistake.
        $lineCount = count($lines);
        $i = 0;
        while ($i < $lineCount) {
            $row = $lines[$i];
            if (! preg_match($lineStart, $row['text'], $dm)) {
                $i++;
                continue;
            }
            $leadingBalance = ($dm[1] ?? '') !== '' ? (float) str_replace(',', '', $dm[1]) : null;
            $date = $this->normalizeDate($dm[2], $fallbackYear);
            $rest = $dm[3];

            if (! $date) {
                $i++;
                continue;
            }

            $linesUsed = 1;
            $wrapped = false;
            preg_match_all($amountAnywhere, $rest, $allAmounts, PREG_SET_ORDER);
            while (empty($allAmounts) && $linesUsed < 4 && ($i + $linesUsed) < $lineCount) {
                $nextText = $lines[$i + $linesUsed]['text'];
                if (preg_match($lineStart, $nextText)) {
                    break; // the next row is itself a fresh transaction start — never merge into it
                }
                $rest .= ' '.$nextText;
                $linesUsed++;
                $wrapped = true;
                preg_match_all($amountAnywhere, $rest, $allAmounts, PREG_SET_ORDER);
            }

            if (empty($allAmounts)) {
                // Still nothing after looking ahead — not a transaction
                // (e.g. "Baki Bawa Ke Hadapan / Balance b/f", the opening-
                // balance carry-forward row). Only the original line is
                // consumed; the lines that were tried as continuations
                // get their own chance to match on the next iterations.
                if ($leadingBalance !== null) { $previousBalance = $leadingBalance; }
                $i++;
                continue;
            }

            $lineNo++;
            $description = trim(preg_replace('/(-?[0-9][0-9,]*\.[0-9]{2})\s*(DR|CR)?\s*$/i', '', $rest));
            $description = trim($description, " \t,");

            $amounts = array_map(fn ($m) => (float) str_replace(',', '', $m[1]), $allAmounts);
            $confidence = 60; // baseline: date + at least one amount found
            $debit = null;
            $credit = null;
            $runningBalance = $leadingBalance;

            if (count($amounts) >= 3) {
                // Common 3-column layout: debit, credit, balance (one of
                // debit/credit is usually blank on the source, but by the
                // time we're at 3 numbers on one line we treat the last as
                // the running balance and the one before it as the amount.
                $runningBalance = array_pop($amounts);
                $amount = array_pop($amounts);
                $marker = $allAmounts[count($allAmounts) - 2][2] ?? '';
                if (strtoupper($marker) === 'CR') { $credit = $amount; } else { $debit = $amount; }
                $confidence = 85;
            } elseif (count($amounts) === 2) {
                $runningBalance = end($amounts);
                $amount = $amounts[0];
                $marker = $allAmounts[0][2] ?? '';
                if (strtoupper($marker) === 'CR') { $credit = $amount; } else { $debit = $amount; }
                $confidence = 75;
            } else {
                $amount = abs($amounts[0]);
                $marker = strtoupper($allAmounts[0][2] ?? '');
                if ($marker === 'CR') { $credit = $amount; $confidence = 85; }
                elseif ($marker === 'DR') { $debit = $amount; $confidence = 85; }
                elseif ($amounts[0] < 0) { $debit = $amount; }
                elseif ($leadingBalance !== null && $previousBalance !== null) {
                    // NEW 16 Sep 2026 — this statement's single trailing
                    // amount never carries a DR/CR marker at all, so the
                    // old code always guessed "debit" here regardless.
                    // The direction can actually be worked out reliably
                    // from this row's own running balance against the
                    // previous row's: balance went down => debit, went
                    // up => credit. Far more trustworthy than a guess, so
                    // this gets ordinary (not lowered) confidence.
                    if ($leadingBalance < $previousBalance) { $debit = $amount; $confidence = 80; }
                    elseif ($leadingBalance > $previousBalance) { $credit = $amount; $confidence = 80; }
                    else { $debit = $amount; $confidence = 55; }
                } else {
                    $debit = $amount; $confidence = 55; // no DR/CR marker, no sign, no balance to compare — genuinely ambiguous, lower confidence
                }
            }

            if ($leadingBalance !== null) { $previousBalance = $leadingBalance; }
            // A row reconstructed from 2-4 wrapped lines is slightly less
            // certain than one that was complete on its own — never
            // raises confidence, only ever lowers it a little.
            if ($wrapped) { $confidence = max(50, $confidence - 10); }

            $out[] = [
                'line_no' => $lineNo,
                'page' => $row['page'],
                'date' => $date,
                'description' => $description !== '' ? mb_substr($description, 0, 255) : null,
                'reference_no' => $this->detectReference($rest),
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance,
                'raw_line' => $wrapped ? $row['text'].' … '.$rest : $row['text'],
                'confidence' => $confidence,
            ];

            $i += $linesUsed;
        }

        return $out;
    }

    private function detectReference(string $line): ?string
    {
        // A run of 6+ alphanumerics that looks like a reference/cheque
        // number, distinct from the date and amounts already consumed.
        if (preg_match('/\b(?=[A-Z0-9]{6,20}\b)(?=[A-Z0-9]*[0-9])[A-Z0-9]{6,20}\b/', mb_strtoupper($line), $m)) {
            return $m[0];
        }
        return null;
    }

    private function normalizeDate(string $raw, ?int $fallbackYear = null): ?string
    {
        $raw = trim($raw);
        $formats = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'd M Y', 'd F Y', 'dmy'];
        foreach ($formats as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $raw);
            if ($d instanceof \DateTime) {
                $errors = \DateTime::getLastErrors();
                if (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $d->format('Y-m-d');
                }
            }
        }

        // UPDATED 10 Sep 2026 (Task #397 follow-up) — "12Jan" style: day +
        // month abbreviation, no year, no separator. Only trusted when a
        // fallback year is available (from the statement's own detected
        // period) — never guessed. A statement whose period spans a
        // year boundary (e.g. 16 Dec–15 Jan) is a known, disclosed edge
        // case not handled here: every line would take the period's
        // start year, which is wrong for the January lines.
        if ($fallbackYear && preg_match('/^([0-9]{1,2})\s*([A-Za-z]{3,9})$/', $raw, $m)) {
            $d = \DateTime::createFromFormat('!d M Y', $m[1].' '.$m[2].' '.$fallbackYear);
            if ($d instanceof \DateTime) {
                $errors = \DateTime::getLastErrors();
                if (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                    return $d->format('Y-m-d');
                }
            }
        }

        return null;
    }
}
