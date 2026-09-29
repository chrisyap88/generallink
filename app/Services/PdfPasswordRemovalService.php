<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

// NEW 16 Sep 2026 — per Chris: some real bank statements are
// password-protected PDFs. Neither of the AI Accounting module's two
// readers (the local text parser, or the AI vision fallback) can open
// an encrypted PDF at all — encryption has to be removed FIRST, before
// either one ever sees the file.
//
// Uses qpdf (a free, open-source command-line tool — NOT a PHP
// library, since no reliable pure-PHP option exists for real
// bank-grade AES-256 PDF encryption) to make one unlocked temporary
// copy, which is then handed to whichever reader would have been used
// anyway. The original uploaded file is never modified. Chris installs
// qpdf once on his server; this is checked and reported clearly if
// it's missing, rather than failing silently.
class PdfPasswordRemovalService
{
    // NEW 16 Sep 2026 — reads from config/services.php ('qpdf.binary',
    // QPDF_BINARY in .env). Chris's Windows PATH setup for qpdf turned
    // out unreliable in practice (new Command Prompt windows not
    // picking it up even after a restart) — pointing straight at the
    // installed qpdf.exe sidesteps PATH entirely, so this never depends
    // on it being configured correctly.
    private function binary(): string
    {
        return config('services.qpdf.binary', 'qpdf');
    }

    /**
     * @return array{ok:bool, path?:string, reason?:string, message?:string}
     */
    public function removePassword(string $inputPath, string $password): array
    {
        if (! $this->qpdfAvailable()) {
            return [
                'ok' => false,
                'reason' => 'qpdf_missing',
                'message' => 'This statement is password-protected, but the qpdf tool needed to unlock it is not installed (or not found) on this server yet. Ask your Admin to install qpdf, then try uploading again.',
            ];
        }

        $outputPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'glk-unlocked-'.Str::random(20).'.pdf';

        try {
            $process = new Process([$this->binary(), '--password='.$password, '--decrypt', $inputPath, $outputPath]);
            $process->setTimeout(30);
            $process->run();
        } catch (ProcessException $e) {
            Log::warning('PdfPasswordRemovalService: qpdf process failed to start: '.$e->getMessage());

            return ['ok' => false, 'reason' => 'process_error', 'message' => 'Could not run the PDF unlock tool. Please try again, or contact your Admin.'];
        }

        if (! $process->isSuccessful() || ! is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);
            $stderr = trim($process->getErrorOutput());
            Log::info('PdfPasswordRemovalService: qpdf could not unlock file (likely wrong password): '.$stderr);

            return [
                'ok' => false,
                'reason' => 'wrong_password_or_unreadable',
                'message' => 'Could not unlock this PDF with the password given — please check the password is correct (it applies to every file in this batch) and try again.',
            ];
        }

        return ['ok' => true, 'path' => $outputPath];
    }

    // NEW 16 Sep 2026 — per Chris: try the password(s) already saved
    // against a bank account automatically, instead of asking him to
    // retype the same password on every upload. Tries each candidate in
    // turn (qpdf harmlessly rejects a wrong one) and stops at the first
    // one that actually opens the file. Returns ok=false — quietly, no
    // error message — if none of them work, or there are no saved
    // passwords to try at all; the caller then just carries on with the
    // original file, exactly as if this feature didn't exist, so a file
    // that was never encrypted in the first place is never affected.
    public function tryCandidatePasswords(string $inputPath, array $candidatePasswords): array
    {
        if (empty($candidatePasswords) || ! $this->qpdfAvailable()) {
            return ['ok' => false];
        }

        foreach (array_unique($candidatePasswords) as $password) {
            if ($password === '' || $password === null) {
                continue;
            }

            $outputPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'glk-unlocked-'.Str::random(20).'.pdf';

            try {
                $process = new Process([$this->binary(), '--password='.$password, '--decrypt', $inputPath, $outputPath]);
                $process->setTimeout(30);
                $process->run();
            } catch (ProcessException $e) {
                continue;
            }

            if ($process->isSuccessful() && is_file($outputPath) && filesize($outputPath) > 0) {
                return ['ok' => true, 'path' => $outputPath];
            }

            @unlink($outputPath);
        }

        return ['ok' => false];
    }

    /** Deletes the temporary unlocked copy once the caller is done reading it — never leaves a decrypted copy sitting on disk. */
    public function cleanup(?string $unlockedPath): void
    {
        if ($unlockedPath && is_file($unlockedPath)) {
            @unlink($unlockedPath);
        }
    }

    private function qpdfAvailable(): bool
    {
        try {
            $process = new Process([$this->binary(), '--version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } catch (ProcessException $e) {
            return false;
        }
    }
}
