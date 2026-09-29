@echo off
REM ============================================================
REM  Convert a labeled sample (PDF or photo) into a clean PNG,
REM  ready to upload to the Document Template wizard.
REM
REM  HOW TO USE:
REM    1. Double-click this file.
REM    2. A "Browse" window opens - find and select your file,
REM       then click Open.
REM    3. Look in the SAME FOLDER as your original file for a
REM       new .png file - upload THAT to the wizard.
REM
REM  Why this exists: some phones/scanners save a photo "as a
REM  PDF" with no real text inside it, just a picture. This
REM  script converts that into a plain PNG image first, so you
REM  don't have to rely on the website figuring that out itself.
REM ============================================================

set "PS_CMD=Add-Type -AssemblyName System.Windows.Forms; $f = New-Object System.Windows.Forms.OpenFileDialog; $f.Title = 'Select your labeled PDF or photo'; $f.Filter = 'PDF and images (*.pdf;*.jpg;*.jpeg;*.png)|*.pdf;*.jpg;*.jpeg;*.png'; if ($f.ShowDialog() -eq 'OK') { Write-Output $f.FileName }"

for /f "usebackq delims=" %%F in (`powershell -NoProfile -Command "%PS_CMD%"`) do set "INPUT=%%F"

if "%INPUT%"=="" (
    echo.
    echo No file was selected. Nothing to do.
    echo.
    pause
    exit /b
)

for %%A in ("%INPUT%") do (
    set "OUTDIR=%%~dpA"
    set "NAME=%%~nA"
    set "EXT=%%~xA"
)

echo.
echo File:   %INPUT%
echo Type:   %EXT%
echo.

if /I "%EXT%"==".pdf" (
    echo Converting PDF page 1 to a PNG image...
    pdftoppm -png -r 200 -f 1 -l 1 "%INPUT%" "%OUTDIR%%NAME%"

    if exist "%OUTDIR%%NAME%-1.png" (
        echo.
        echo DONE. Created: %NAME%-1.png
        echo Upload that file to the wizard.
    ) else if exist "%OUTDIR%%NAME%-01.png" (
        echo.
        echo DONE. Created: %NAME%-01.png
        echo Upload that file to the wizard.
    ) else (
        echo.
        echo Something went wrong - no PNG was created.
        echo Check that poppler is installed correctly ^(type "pdftotext -v" in Command Prompt to test^).
    )
) else (
    echo This is already a photo file - no conversion needed.
    echo Upload it directly to the wizard as-is.
)

echo.
pause
