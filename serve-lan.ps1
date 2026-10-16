<#
.SYNOPSIS
    Jalankan SuperBie (Lapor Pak Wali) agar bisa diakses tim lewat LAN - "Opsi A".

.DESCRIPTION
    Otomatis melakukan:
      1. Menghentikan Vite dev server + supervisor `composer dev` (concurrently),
         yang merupakan PENYEBAB utama `public/hot` muncul terus dan merusak aset.
      2. Menghapus public/hot supaya aset dilayani dari public/build (bukan Vite).
      3. Mendeteksi IP LAN yang benar (mengabaikan loopback / APIPA / VirtualBox).
      4. Menyetel APP_URL di .env ke http://<IP-LAN>:<port> dan membersihkan cache config.
      5. Menjalankan `npm run build` (aset produksi).
      6. Membuat firewall rule inbound (opsional, butuh Administrator).
      7. Menjalankan `php artisan serve --host=0.0.0.0 --port=<port>`.

.PARAMETER Port
    Port server. Default 8000.

.PARAMETER Ip
    Paksa IP LAN tertentu. Default: deteksi otomatis.

.PARAMETER NoBuild
    Lewati `npm run build` (pakai aset yang sudah ada di public/build).

.PARAMETER NoServe
    Siapkan semuanya tetapi JANGAN jalankan server (berguna untuk uji/verifikasi).

.PARAMETER SkipFirewall
    Jangan menyentuh firewall.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\serve-lan.ps1

.EXAMPLE
    .\serve-lan.ps1 -Port 8080

.EXAMPLE
    .\serve-lan.ps1 -Ip 192.168.1.50
#>
[CmdletBinding()]
param(
    [int]$Port = 8000,
    [string]$Ip,
    [switch]$NoBuild,
    [switch]$NoServe,
    [switch]$SkipFirewall
)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

function Write-Step([string]$msg) { Write-Host "==> $msg" -ForegroundColor Cyan }
function Write-Ok([string]$msg)   { Write-Host "    $msg" -ForegroundColor Green }
function Write-Warn([string]$msg) { Write-Host "    $msg" -ForegroundColor Yellow }

# ---------------------------------------------------------------------------
# 1. Deteksi IP LAN (lewati loopback, APIPA, VirtualBox host-only, virtual NIC)
# ---------------------------------------------------------------------------
function Get-LanIp {
    param([string]$Override)

    if ($Override) { return $Override }

    $candidates = @()

    foreach ($ip in (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue)) {
        if ($ip.IPAddress -match '^127\.')       { continue }  # loopback
        if ($ip.IPAddress -match '^169\.254\.')  { continue }  # APIPA
        if ($ip.IPAddress -match '^192\.168\.56\.') { continue } # VirtualBox host-only

        $adapter = Get-NetAdapter -InterfaceIndex $ip.InterfaceIndex -ErrorAction SilentlyContinue
        if (-not $adapter) { continue }
        if ($adapter.Status -ne 'Up') { continue }
        if ($adapter.InterfaceDescription -match 'VirtualBox|Hyper-V|VMware|Loopback|vEthernet|WSL') { continue }

        $cfg   = Get-NetIPConfiguration -InterfaceIndex $ip.InterfaceIndex -ErrorAction SilentlyContinue
        $hasGw = [bool]($cfg.IPv4DefaultGateway)

        $candidates += [pscustomobject]@{
            IP      = $ip.IPAddress
            HasGw   = $hasGw
            IfIndex = $ip.InterfaceIndex
            IfName  = $adapter.Name
        }
    }

    if ($candidates.Count -eq 0) {
        throw "Tidak menemukan IP LAN yang cocok. Tentukan manual, contoh: .\serve-lan.ps1 -Ip 10.0.0.5"
    }

    # Utamakan adapter yang punya default gateway (adapter LAN asli).
    $best = $candidates |
        Sort-Object @{ Expression = 'HasGw';   Descending = $true },
                    @{ Expression = 'IfIndex'; Descending = $false } |
        Select-Object -First 1

    Write-Ok "IP LAN terpilih: $($best.IP)  [$($best.IfName)]"
    return $best.IP
}

# ---------------------------------------------------------------------------
# 2. Hentikan Vite + supervisor composer dev (spesifik project ini saja)
# ---------------------------------------------------------------------------
function Stop-DevProcesses {
    $pattern = 'vite|concurrently|artisan serve|artisan queue|resources[/\\]server\.php'

    # Beberapa iterasi: supervisor bisa sempat menghidupkan ulang anak proses.
    for ($i = 0; $i -lt 3; $i++) {
        $procs = Get-CimInstance Win32_Process -Filter "Name='node.exe' OR Name='composer.exe' OR Name='php.exe'" -ErrorAction SilentlyContinue |
            Where-Object { $_.CommandLine -and $_.CommandLine -match $pattern }

        if (-not $procs) { break }

        foreach ($p in $procs) {
            try { Stop-Process -Id $p.ProcessId -Force -ErrorAction Stop } catch { }
        }
        Start-Sleep -Milliseconds 600
    }
}

# ---------------------------------------------------------------------------
# 3. Set APP_URL di .env (UTF-8 tanpa BOM; .env tidak masuk git)
# ---------------------------------------------------------------------------
function Set-EnvAppUrl {
    param([string]$EnvPath, [string]$Url)

    if (-not (Test-Path -LiteralPath $EnvPath)) { throw ".env tidak ditemukan di: $EnvPath" }

    $lines = [System.IO.File]::ReadAllLines($EnvPath)
    $found = $false
    $out   = New-Object System.Collections.Generic.List[string]

    foreach ($line in $lines) {
        if ($line -match '^\s*APP_URL\s*=') {
            $out.Add("APP_URL=$Url")
            $found = $true
        } else {
            $out.Add($line)
        }
    }
    if (-not $found) { $out.Add("APP_URL=$Url") }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllLines($EnvPath, $out, $utf8NoBom)
}

# ===========================================================================
# RUN
# ===========================================================================

Write-Step "1/6  Hentikan Vite dev server & supervisor (composer dev)"
Stop-DevProcesses
Write-Ok "Selesai."

Write-Step "2/6  Hapus public/hot (agar aset dilayani dari public/build)"
Remove-Item -LiteralPath (Join-Path $PSScriptRoot 'public\hot') -Force -ErrorAction SilentlyContinue
Write-Ok "public/hot tidak ada."

Write-Step "3/6  Deteksi IP LAN"
$lanIp = Get-LanIp -Override $Ip

Write-Step "4/6  Set APP_URL di .env + bersihkan cache config"
Set-EnvAppUrl -EnvPath (Join-Path $PSScriptRoot '.env') -Url "http://${lanIp}:$Port"
& php artisan config:clear | Out-Null
Write-Ok "APP_URL = http://${lanIp}:$Port"

Write-Step "5/6  Build aset (npm run build)"
if ($NoBuild) {
    Write-Warn "Dilewati karena -NoBuild."
} else {
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw "npm run build GAGAL (exit code $LASTEXITCODE)." }
    Write-Ok "Build selesai."
}

if (-not $SkipFirewall) {
    $ruleName = "SuperBie LAN $Port"
    if (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue) {
        Write-Ok "Firewall rule '$ruleName' sudah ada."
    } else {
        try {
            New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP `
                -LocalPort $Port -Action Allow -Profile Any -ErrorAction Stop | Out-Null
            Write-Ok "Firewall rule '$ruleName' dibuat."
        } catch {
            Write-Warn "Tidak bisa membuat firewall rule (butuh Administrator). Jalankan PowerShell as Admin:"
            Write-Warn "  New-NetFirewallRule -DisplayName '$ruleName' -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Any"
        }
    }
}

if ($NoServe) {
    Write-Step "6/6  Selesai (-NoServe) - server TIDAK dijalankan."
    Write-Host ""
    Write-Host "  Akses tim nanti di: http://${lanIp}:$Port" -ForegroundColor White
    return
}

Write-Step "6/6  Jalankan server (host 0.0.0.0)"
Write-Host ""
Write-Host "  ============================================================" -ForegroundColor DarkGray
Write-Host "   Tim akses di: http://${lanIp}:$Port" -ForegroundColor White
Write-Host "   Biarkan jendela ini terbuka. Tekan Ctrl+C untuk berhenti." -ForegroundColor DarkGray
Write-Host "  ============================================================" -ForegroundColor DarkGray
Write-Host ""

& php artisan serve --host=0.0.0.0 --port=$Port
