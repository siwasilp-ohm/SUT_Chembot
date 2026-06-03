#Requires -Version 5.0
<#
  SUT ChemBot · GHS Bulk Import Tool
  Interactive launcher for ghs_bulk_import.php
  Double-click ghs_import.bat to run.
#>

# ── Fix console encoding ──────────────────────────────────────────────────────
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class ConsoleUnicode {
    [DllImport("kernel32.dll")] public static extern bool SetConsoleOutputCP(uint cp);
    [DllImport("kernel32.dll")] public static extern bool SetConsoleCP(uint cp);
    [DllImport("kernel32.dll")] public static extern IntPtr GetStdHandle(int h);
    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Unicode)]
    public struct CONSOLE_FONT_INFOEX {
        public uint   cbSize;
        public uint   nFont;
        public short  dwX, dwY;
        public uint   FontFamily;
        public uint   FontWeight;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst=32)] public string FaceName;
    }
    [DllImport("kernel32.dll", CharSet=CharSet.Unicode)]
    public static extern bool SetCurrentConsoleFontEx(IntPtr h, bool max, ref CONSOLE_FONT_INFOEX f);
    public static void Init() {
        SetConsoleOutputCP(65001);
        SetConsoleCP(65001);
        IntPtr hOut = GetStdHandle(-11);
        var f = new CONSOLE_FONT_INFOEX();
        f.cbSize     = (uint)System.Runtime.InteropServices.Marshal.SizeOf(f);
        f.dwY        = 16;
        f.FontFamily = 54;
        f.FontWeight = 400;
        f.FaceName   = "Courier New";
        SetCurrentConsoleFontEx(hOut, false, ref f);
    }
}
"@ -ErrorAction SilentlyContinue
try { [ConsoleUnicode]::Init() } catch {}

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Console]::InputEncoding  = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$ESC         = [char]27
$SCRIPT_DIR  = Split-Path -Parent $MyInvocation.MyCommand.Path
$PHP_SCRIPT  = Join-Path $SCRIPT_DIR "ghs_bulk_import.php"

# ── Locate PHP ────────────────────────────────────────────────────────────────
$PHP_EXE = $null
foreach ($p in @("C:\xampp\php\php.exe","C:\php\php.exe","C:\wamp64\bin\php\php8.0.30\php.exe")) {
    if (Test-Path $p) { $PHP_EXE = $p; break }
}
if (-not $PHP_EXE) {
    try { $PHP_EXE = (Get-Command php -ErrorAction Stop).Source } catch {}
}

# ── Color helper ──────────────────────────────────────────────────────────────
function cc([string]$code, [string]$text) {
    $e = [char]27
    return "${e}[${code}m${text}${e}[0m"
}

# ── Banner ────────────────────────────────────────────────────────────────────
function Write-Banner {
    Write-Host ""
    Write-Host "  $(cc '95;1' 'o======================================================o')"
    Write-Host "  $(cc '95;1' '|')$(cc '96;1' '      SUT ChemBot - GHS Bulk Import Tool v2.0       ')$(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' '|')$(cc '37'   '   Powered by PubChem REST API  Suranaree Univ.     ')$(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' 'o======================================================o')"
    Write-Host ""
}

# ── Run PHP worker ────────────────────────────────────────────────────────────
function Invoke-Import([string[]]$phpArgs) {
    Write-Host ""
    Write-Host "  $(cc '96' 'Starting PHP worker...')"
    Write-Host "  $(cc '2'  'Press Ctrl+C to stop  (already-saved data is kept)')"
    Write-Host "  $(cc '95' '------------------------------------------------------')"
    Write-Host ""

    $allArgs = @("-d","max_execution_time=0", $PHP_SCRIPT) + $phpArgs
    & $PHP_EXE @allArgs
    $code = $LASTEXITCODE

    Write-Host ""
    Write-Host "  $(cc '95' '------------------------------------------------------')"
    if ($code -eq 0) {
        Write-Host "  $(cc '92;1' ' [OK] Completed with no errors')"
    } else {
        Write-Host "  $(cc '93;1' ' [!]  Completed with some failures -- check log file')"
    }
    Write-Host ""
    Read-Host "  Press Enter to return to menu" | Out-Null
}

# ── Validate setup ────────────────────────────────────────────────────────────
if (-not $PHP_EXE) {
    Write-Host "$(cc '91;1' '  [ERROR]') PHP not found -- please check your XAMPP installation"
    Read-Host | Out-Null; exit 1
}
if (-not (Test-Path $PHP_SCRIPT)) {
    Write-Host "$(cc '91;1' '  [ERROR]') ghs_bulk_import.php not found in $SCRIPT_DIR"
    Read-Host | Out-Null; exit 1
}

# ── Main loop ─────────────────────────────────────────────────────────────────
while ($true) {
    Clear-Host
    Write-Banner
    Write-Host "  $(cc '95;1' 'o======================================================o')"
    Write-Host "  $(cc '95;1' '|')$(cc '93;1' '  Select import mode:                                ')$(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' '|')                                                      $(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' '|')  $(cc '96;1' '[1]')  Import missing GHS only $(cc '2' '(recommended)')             $(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' '|')  $(cc '96;1' '[2]')  Import ALL chemicals $(cc '2' '(overwrite existing data)')    $(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' '|')  $(cc '96;1' '[3]')  Lookup by CAS Number                              $(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' '|')  $(cc '96;1' '[4]')  Dry-run test $(cc '2' '(no DB write)')                       $(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' '|')  $(cc '96;1' '[5]')  View latest log file                              $(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' '|')  $(cc '91;1' '[Q]')  Quit                                              $(cc '95;1' '|')"
    Write-Host "  $(cc '95;1' 'o======================================================o')"
    Write-Host ""

    $choice = (Read-Host "  $(cc '93' 'Choose [1-5/Q]')").Trim().ToUpper()

    switch ($choice) {

        '1' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '96;1' ' >> Import chemicals missing GHS data')"
            Write-Host "  $(cc '2'   '    Chemicals that already have GHS data will be skipped')"
            Write-Host ""
            $lim  = (Read-Host "  Limit number of chemicals (Enter = unlimited)").Trim()
            $dly  = (Read-Host "  Delay per chemical in seconds (Enter = 0.8)").Trim()
            $vrb  = (Read-Host "  Verbose output? [Y/N]").Trim()
            $rsm  = (Read-Host "  Resume from last run? [Y/N]").Trim()
            $a    = @("--mode=missing")
            if ($lim) { $a += "--limit=$lim" }
            if ($dly) { $a += "--delay=$dly" }
            if ($vrb -match '^[Yy]') { $a += "--verbose" }
            if ($rsm -match '^[Yy]') { $a += "--resume" }
            Invoke-Import $a
        }

        '2' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '93;1' ' [!] WARNING: This will overwrite ALL existing GHS data')"
            Write-Host ""
            $confirm = (Read-Host "  Type YES to confirm (anything else = cancel)").Trim()
            if ($confirm -ne 'YES') { continue }
            $lim = (Read-Host "  Limit number of chemicals (Enter = unlimited)").Trim()
            $dly = (Read-Host "  Delay per chemical in seconds (Enter = 0.8)").Trim()
            $a   = @("--mode=all")
            if ($lim) { $a += "--limit=$lim" }
            if ($dly) { $a += "--delay=$dly" }
            Invoke-Import $a
        }

        '3' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '96;1' ' >> Lookup chemical by CAS Number')"
            Write-Host "  $(cc '2'   '    Examples: 7647-01-0 (HCl), 7664-93-9 (H2SO4)')"
            Write-Host ""
            $cas = (Read-Host "  CAS Number").Trim()
            if (-not $cas) { continue }
            Invoke-Import @("--mode=cas=$cas","--verbose")
        }

        '4' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '92;1' ' >> Dry-run Mode -- simulate only, no DB writes')"
            Write-Host ""
            $lim = (Read-Host "  Number of chemicals to test (Enter = 10)").Trim()
            if (-not $lim) { $lim = "10" }
            Invoke-Import @("--mode=missing","--dry-run","--verbose","--limit=$lim")
        }

        '5' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '96;1' ' >> Latest log file')"
            Write-Host ""
            $logs = Get-ChildItem "$SCRIPT_DIR\ghs_import_*.log" -ErrorAction SilentlyContinue |
                    Sort-Object LastWriteTime -Descending
            if (-not $logs) {
                Write-Host "  $(cc '2' 'No log files found -- run an import first')"
            } else {
                $latest = $logs[0].FullName
                Write-Host "  $(cc '93' $latest)"
                Write-Host ""
                Get-Content $latest -Tail 80 | ForEach-Object {
                    if      ($_ -match '^\[OK\]')                  { Write-Host $_ -ForegroundColor Green }
                    elseif  ($_ -match '^\[ERR\]')                 { Write-Host $_ -ForegroundColor Red }
                    elseif  ($_ -match '^\[NOTFOUND\]|^\[EMPTY\]') { Write-Host $_ -ForegroundColor DarkGray }
                    elseif  ($_ -match '^\[SKIP\]')                { Write-Host $_ -ForegroundColor Yellow }
                    elseif  ($_ -match '^=== DONE')                { Write-Host $_ -ForegroundColor Cyan }
                    else                                            { Write-Host $_ }
                }
            }
            Write-Host ""
            Read-Host "  Press Enter to return to menu" | Out-Null
        }

        'Q' {
            break
        }
    }

    if ($choice -eq 'Q') { break }
}

Clear-Host
Write-Host ""
Write-Host "  $(cc '96' '  Thank you for using SUT ChemBot GHS Import Tool')"
Write-Host ""
