#Requires -Version 5.0
<#
  SUT ChemBot - SDS Bulk Downloader
  Interactive launcher for sds_downloader.php
  Double-click sds_finder.bat to run.
#>

# â”€â”€ Fix console encoding â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class SdsConsole {
    [DllImport("kernel32.dll")] public static extern bool SetConsoleOutputCP(uint cp);
    [DllImport("kernel32.dll")] public static extern bool SetConsoleCP(uint cp);
    [DllImport("kernel32.dll")] public static extern IntPtr GetStdHandle(int h);
    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Unicode)]
    public struct FONT_INFO {
        public uint cbSize; public uint nFont; public short dwX, dwY;
        public uint FontFamily; public uint FontWeight;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst=32)] public string FaceName;
    }
    [DllImport("kernel32.dll", CharSet=CharSet.Unicode)]
    public static extern bool SetCurrentConsoleFontEx(IntPtr h, bool max, ref FONT_INFO f);
    public static void Init() {
        SetConsoleOutputCP(65001); SetConsoleCP(65001);
        IntPtr hOut = GetStdHandle(-11);
        var f = new FONT_INFO();
        f.cbSize = (uint)System.Runtime.InteropServices.Marshal.SizeOf(f);
        f.dwY = 16; f.FontFamily = 54; f.FontWeight = 400; f.FaceName = "Courier New";
        SetCurrentConsoleFontEx(hOut, false, ref f);
    }
}
"@ -ErrorAction SilentlyContinue
try { [SdsConsole]::Init() } catch {}

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
[Console]::InputEncoding  = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$SCRIPT_DIR = Split-Path -Parent $MyInvocation.MyCommand.Path
$PHP_SCRIPT = Join-Path $SCRIPT_DIR "sds_downloader.php"

# â”€â”€ Locate PHP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$PHP_EXE = $null
foreach ($p in @("C:\xampp\php\php.exe","C:\php\php.exe","C:\wamp64\bin\php\php8.0.30\php.exe")) {
    if (Test-Path $p) { $PHP_EXE = $p; break }
}
if (-not $PHP_EXE) {
    try { $PHP_EXE = (Get-Command php -ErrorAction Stop).Source } catch {}
}

# â”€â”€ Color helper â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function cc([string]$code, [string]$text) {
    $e = [char]27
    return "${e}[${code}m${text}${e}[0m"
}

# â”€â”€ Banner â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function Write-Banner {
    Write-Host ""
    Write-Host "  $(cc '35;1' 'o========================================================o')"
    Write-Host "  $(cc '35;1' '|')$(cc '96;1' '     SUT ChemBot - SDS Bulk Downloader v2.0           ')$(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' '|')$(cc '2'   '   Sources: ChemBlink  DDG-Search  PubChem             ')$(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' 'o========================================================o')"
    Write-Host ""
}

# â”€â”€ Run PHP worker â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function Invoke-Download([string[]]$phpArgs) {
    Write-Host ""
    Write-Host "  $(cc '96' 'Starting PHP worker...')"
    Write-Host "  $(cc '2'  'Press Ctrl+C to stop  (already-saved files are kept)')"
    Write-Host "  $(cc '35' '--------------------------------------------------------')"
    Write-Host ""

    $allArgs = @("-d","max_execution_time=0", $PHP_SCRIPT) + $phpArgs
    & $PHP_EXE @allArgs
    $code = $LASTEXITCODE

    Write-Host ""
    Write-Host "  $(cc '35' '--------------------------------------------------------')"
    if ($code -eq 0) {
        Write-Host "  $(cc '92;1' ' [OK] Completed with no errors')"
    } else {
        Write-Host "  $(cc '93;1' ' [!]  Completed with some failures -- check log file')"
    }
    Write-Host ""
    Read-Host "  Press Enter to return to menu" | Out-Null
}

# â”€â”€ Validate setup â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (-not $PHP_EXE) {
    Write-Host "$(cc '91;1' '  [ERROR]') PHP not found -- check XAMPP installation"
    Read-Host | Out-Null; exit 1
}
if (-not (Test-Path $PHP_SCRIPT)) {
    Write-Host "$(cc '91;1' '  [ERROR]') sds_downloader.php not found in $SCRIPT_DIR"
    Read-Host | Out-Null; exit 1
}

# â”€â”€ Main loop â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
while ($true) {
    Clear-Host
    Write-Banner
    Write-Host "  $(cc '35;1' 'o========================================================o')"
    Write-Host "  $(cc '35;1' '|')$(cc '93;1' '  Select operation:                                    ')$(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' '|')                                                        $(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' '|')  $(cc '96;1' '[1]')  Download SDS for missing chemicals $(cc '2' '(recommended)')  $(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' '|')  $(cc '96;1' '[2]')  Download SDS by CAS number or name             $(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' '|')  $(cc '96;1' '[3]')  Download SDS for ALL chemicals $(cc '2' '(fill to max)')   $(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' '|')  $(cc '96;1' '[4]')  Dry-run test $(cc '2' '(no downloads, no DB)')              $(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' '|')  $(cc '96;1' '[5]')  Show SDS statistics                             $(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' '|')  $(cc '96;1' '[6]')  View latest log file                            $(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' '|')  $(cc '91;1' '[Q]')  Quit                                            $(cc '35;1' '|')"
    Write-Host "  $(cc '35;1' 'o========================================================o')"
    Write-Host ""

    $choice = (Read-Host "  $(cc '93' 'Choose [1-6/Q]')").Trim().ToUpper()

    switch ($choice) {

        '1' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '96;1' ' >> Download SDS for chemicals missing SDS files')"
            Write-Host "  $(cc '2'   '    Chemicals that already have SDS files will be skipped')"
            Write-Host ""
            $lim = (Read-Host "  Limit number of chemicals (Enter = unlimited)").Trim()
            $dly = (Read-Host "  Delay between chemicals in seconds (Enter = 2.0)").Trim()
            $mxf = (Read-Host "  Max PDFs per chemical 1-5 (Enter = 3)").Trim()
            $vrb = (Read-Host "  Verbose output? [Y/N]").Trim()
            $a   = @("--mode=missing")
            if ($lim) { $a += "--limit=$lim" }
            if ($dly) { $a += "--delay=$dly" }
            if ($mxf) { $a += "--max-files=$mxf" }
            if ($vrb -match '^[Yy]') { $a += "--verbose" }
            Invoke-Download $a
        }

        '2' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '96;1' ' >> Download SDS by CAS number or chemical name')"
            Write-Host "  $(cc '2'   '    Examples: 7647-01-0   or   hydrochloric acid')"
            Write-Host ""
            $cas = (Read-Host "  CAS number or name").Trim()
            if (-not $cas) { continue }
            $mxf = (Read-Host "  Max PDFs to download 1-5 (Enter = 3)").Trim()
            $a   = @("--mode=cas=$cas", "--verbose")
            if ($mxf) { $a += "--max-files=$mxf" }
            Invoke-Download $a
        }

        '3' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '93;1' ' [!] Will process all chemicals -- skips those already at limit')"
            Write-Host ""
            $confirm = (Read-Host "  Type YES to confirm").Trim()
            if ($confirm -ne 'YES') { continue }
            $lim = (Read-Host "  Limit number of chemicals (Enter = unlimited)").Trim()
            $dly = (Read-Host "  Delay between chemicals in seconds (Enter = 2.0)").Trim()
            $mxf = (Read-Host "  Max PDFs per chemical 1-5 (Enter = 3)").Trim()
            $a   = @("--mode=all")
            if ($lim) { $a += "--limit=$lim" }
            if ($dly) { $a += "--delay=$dly" }
            if ($mxf) { $a += "--max-files=$mxf" }
            Invoke-Download $a
        }

        '4' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '92;1' ' >> Dry-run -- no files saved, no DB writes')"
            Write-Host ""
            $cas = (Read-Host "  CAS/name to test (Enter = first 5 missing)").Trim()
            $a   = @("--dry-run","--verbose")
            if ($cas) {
                $a += "--mode=cas=$cas"
            } else {
                $a += "--mode=missing"
                $a += "--limit=5"
            }
            Invoke-Download $a
        }

        '5' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '96;1' ' >> SDS Statistics')"
            Write-Host ""
            Invoke-Download @("--stats")
        }

        '6' {
            Clear-Host; Write-Banner
            Write-Host "  $(cc '96;1' ' >> Latest log file')"
            Write-Host ""
            $logs = Get-ChildItem "$SCRIPT_DIR\sds_download_*.log" -ErrorAction SilentlyContinue |
                    Sort-Object LastWriteTime -Descending
            if (-not $logs) {
                Write-Host "  $(cc '2' 'No log files found -- run a download first')"
            } else {
                $latest = $logs[0].FullName
                Write-Host "  $(cc '93' $latest)"
                Write-Host ""
                Get-Content $latest -Tail 80 | ForEach-Object {
                    if      ($_ -match '^\[OK\]')       { Write-Host $_ -ForegroundColor Green }
                    elseif  ($_ -match '^\[ERR\]')      { Write-Host $_ -ForegroundColor Red }
                    elseif  ($_ -match '^\[NOTFOUND\]') { Write-Host $_ -ForegroundColor DarkGray }
                    elseif  ($_ -match '^\[SKIP\]')     { Write-Host $_ -ForegroundColor Yellow }
                    elseif  ($_ -match '^=== DONE')     { Write-Host $_ -ForegroundColor Cyan }
                    else                                 { Write-Host $_ }
                }
            }
            Write-Host ""
            Read-Host "  Press Enter to return to menu" | Out-Null
        }

        'Q' { break }
    }

    if ($choice -eq 'Q') { break }
}

Clear-Host
Write-Host ""
Write-Host "  $(cc '96' '  Thank you for using SUT ChemBot SDS Downloader')"
Write-Host ""

