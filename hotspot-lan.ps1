<#
.SYNOPSIS
    Jalankan SuperBie (Lapor Pak Wali) lewat MOBILE HOTSPOT (PC jadi WiFi sendiri).

.DESCRIPTION
    Dipakai kalau WiFi kantor mengaktifkan client isolation (antar-perangkat tidak
    bisa saling akses). PC memancarkan hotspot sendiri, tim connect ke hotspot itu,
    lalu membuka aplikasi via IP hotspot.

    Langkah otomatis:
      1. Nyalakan Windows Mobile Hotspot (SSID/password bisa diatur).
      2. Deteksi IP adapter hotspot (biasanya 192.168.137.1).
      3. Set APP_URL di .env ke http://<IP-hotspot>:<port>.
      4. Buat firewall rule inbound (bila belum ada).
      5. (Opsional) npm run build.
      6. Jalankan php artisan serve --host=0.0.0.0 --port=<port>.

.PARAMETER Port
    Port server. Default 8000.

.PARAMETER Ssid
    Nama hotspot. Default: SuperBie-Demo

.PARAMETER Passphrase
    Password hotspot (minimal 8 karakter). WAJIB: tidak ada default literal —
    bila kosong, skrip meminta interaktif (audit PROMPT_28: jangan menyimpan
    kredensial di repo).

.PARAMETER NoBuild
    Lewati `npm run build`.

.PARAMETER NoServe
    Siapkan hotspot + APP_URL saja, jangan jalankan server (untuk uji).

.PARAMETER Stop
    Matikan hotspot dan keluar.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\hotspot-lan.ps1
.EXAMPLE
    .\hotspot-lan.ps1 -Stop
#>
[CmdletBinding()]
param(
    [int]$Port = 8000,
    [string]$Ssid = 'SuperBie-Demo',
    [string]$Passphrase = '',
    [switch]$NoBuild,
    [switch]$NoServe,
    [switch]$Stop
)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

function Write-Step([string]$m) { Write-Host "==> $m" -ForegroundColor Cyan }
function Write-Ok([string]$m)   { Write-Host "    $m" -ForegroundColor Green }
function Write-Warn2([string]$m){ Write-Host "    $m" -ForegroundColor Yellow }

# --- Muat WinRT -----------------------------------------------------------
Add-Type -AssemblyName System.Runtime.WindowsRuntime | Out-Null
$null = [Windows.Networking.NetworkOperators.NetworkOperatorTetheringManager,Windows.Networking.NetworkOperators,ContentType=WindowsRuntime]
$null = [Windows.Networking.Connectivity.NetworkInformation,Windows.Networking.Connectivity,ContentType=WindowsRuntime]

function Get-TetheringManager {
    $profile = [Windows.Networking.Connectivity.NetworkInformation]::GetInternetConnectionProfile()
    if (-not $profile) { throw "Tidak ada koneksi internet aktif. Sambungkan PC ke internet dulu." }
    return [Windows.Networking.NetworkOperators.NetworkOperatorTetheringManager]::CreateFromConnectionProfile($profile)
}

# --- Helper: set APP_URL di .env -----------------------------------------
function Set-EnvAppUrl {
    param([string]$EnvPath, [string]$Url)
    if (-not (Test-Path -LiteralPath $EnvPath)) { throw ".env tidak ditemukan di: $EnvPath" }
    $lines = [System.IO.File]::ReadAllLines($EnvPath)
    $found = $false
    $out   = New-Object System.Collections.Generic.List[string]
    foreach ($line in $lines) {
        if ($line -match '^\s*APP_URL\s*=') { $out.Add("APP_URL=$Url"); $found = $true }
        else { $out.Add($line) }
    }
    if (-not $found) { $out.Add("APP_URL=$Url") }
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllLines($EnvPath, $out, $utf8NoBom)
}

# --- Helper: hentikan Vite dev + server lama (agar tidak bentrok port) ----
function Stop-DevProcesses {
    $pattern = 'vite|concurrently|artisan serve|artisan queue|resources[/\\]server\.php'
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

# --- Helper: deteksi IP hotspot ------------------------------------------
function Get-HotspotIp {
    for ($i = 0; $i -lt 20; $i++) {
        $ips = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
            Where-Object { $_.IPAddress -like '192.168.137.*' }
        if ($ips) { return ($ips | Select-Object -First 1).IPAddress }
        Start-Sleep -Milliseconds 500
    }
    $ad = Get-NetAdapter -ErrorAction SilentlyContinue | Where-Object { $_.InterfaceDescription -match 'Wi-Fi Direct' -and $_.Status -eq 'Up' }
    if ($ad) {
        $ip = Get-NetIPAddress -InterfaceIndex $ad.ifIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($ip) { return $ip.IPAddress }
    }
    return $null
}

# =========================== STOP MODE ===================================
if ($Stop) {
    Write-Step "Matikan Mobile Hotspot"
    try {
        $mgr = Get-TetheringManager
        $null = $mgr.StopTetheringAsync()
        for ($i = 0; $i -lt 20; $i++) { if ($mgr.TetheringOperationalState -ne 'On') { break }; Start-Sleep -Milliseconds 500 }
        Write-Ok "Hotspot dimatikan (state: $($mgr.TetheringOperationalState))."
    } catch { Write-Warn2 "Gagal: $($_.Exception.Message)" }
    return
}

# Audit PROMPT_28 (higiene): no literal credential in the repo. The passphrase
# must be supplied explicitly (or typed interactively); Windows requires 8+ chars.
if ([string]::IsNullOrWhiteSpace($Passphrase)) {
    $Passphrase = Read-Host -Prompt 'Password hotspot (minimal 8 karakter)'
}
if ($Passphrase.Length -lt 8) {
    throw 'Password hotspot minimal 8 karakter.'
}

# =========================== MAIN ========================================
Write-Step "1/6  Konfigurasi & nyalakan Mobile Hotspot"
$mgr = Get-TetheringManager
$srcProfile = [Windows.Networking.Connectivity.NetworkInformation]::GetInternetConnectionProfile().ProfileName
Write-Ok "Sumber internet: $srcProfile"

$curCfg = $mgr.GetCurrentAccessPointConfiguration()
$needRestart = ($mgr.TetheringOperationalState -eq 'On') -and ($curCfg.Ssid -ne $Ssid)

if ($needRestart) {
    Write-Warn2 "Hotspot aktif dgn SSID lama - restart untuk menerapkan SSID/password baru..."
    $null = $mgr.StopTetheringAsync()
    for ($i = 0; $i -lt 20; $i++) { if ($mgr.TetheringOperationalState -ne 'On') { break }; Start-Sleep -Milliseconds 500 }
}

# Terapkan SSID/password (polling, tanpa await)
$applied = ($mgr.GetCurrentAccessPointConfiguration().Ssid -eq $Ssid)
if (-not $applied) {
    try {
        $cfg = $mgr.GetCurrentAccessPointConfiguration()
        $cfg.Ssid       = $Ssid
        $cfg.Passphrase = $Passphrase
        $op = $mgr.ConfigureAccessPointAsync($cfg)   # simpan referensi agar op tidak dibatalkan
        for ($i = 0; $i -lt 25; $i++) {
            Start-Sleep -Milliseconds 400
            if ($mgr.GetCurrentAccessPointConfiguration().Ssid -eq $Ssid) { $applied = $true; break }
        }
    } catch {
        Write-Warn2 "Gagal set SSID/password: $($_.Exception.Message)"
    }
}

if ($applied) {
    Write-Ok "SSID = $Ssid | Password = $Passphrase"
} else {
    $curCfg = $mgr.GetCurrentAccessPointConfiguration()
    $Ssid = $curCfg.Ssid; $Passphrase = $curCfg.Passphrase
    Write-Warn2 "Pakai konfigurasi yang ada: SSID=$Ssid Password=$Passphrase"
}

# Nyalakan
if ($mgr.TetheringOperationalState -ne 'On') {
    $op2 = $mgr.StartTetheringAsync()
    $on = $false
    for ($i = 0; $i -lt 30; $i++) {
        if ($mgr.TetheringOperationalState -eq 'On') { $on = $true; break }
        Start-Sleep -Milliseconds 500
    }
    if (-not $on) { throw "Hotspot gagal dinyalakan (state: $($mgr.TetheringOperationalState))." }
}
Write-Ok "Hotspot AKTIF (state: $($mgr.TetheringOperationalState))."

Write-Step "2/6  Deteksi IP hotspot"
$hsIp = Get-HotspotIp
if (-not $hsIp) { throw "IP hotspot tidak terdeteksi. Cek Settings > Network > Mobile hotspot." }
Write-Ok "IP hotspot: $hsIp"

Write-Step "3/6  Set APP_URL di .env + bersihkan cache config"
Set-EnvAppUrl -EnvPath (Join-Path $PSScriptRoot '.env') -Url "http://${hsIp}:$Port"
& php artisan config:clear | Out-Null
Write-Ok "APP_URL = http://${hsIp}:$Port"

Write-Step "4/6  Firewall rule"
$ruleName = "SuperBie LAN $Port"
if (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue) {
    Write-Ok "Rule '$ruleName' sudah ada."
} else {
    try {
        New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP `
            -LocalPort $Port -Action Allow -Profile Any -ErrorAction Stop | Out-Null
        Write-Ok "Rule '$ruleName' dibuat."
    } catch {
        Write-Warn2 "Butuh Administrator. Jalankan sebagai Admin:"
        Write-Warn2 "  New-NetFirewallRule -DisplayName '$ruleName' -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Any"
    }
}

Write-Step "5/6  Build aset"
Stop-DevProcesses
Remove-Item -LiteralPath (Join-Path $PSScriptRoot 'public\hot') -Force -ErrorAction SilentlyContinue
if ($NoBuild) { Write-Warn2 "Dilewati (-NoBuild)." }
else {
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) { throw "npm run build GAGAL (exit $LASTEXITCODE)." }
    Write-Ok "Build selesai."
}

if ($NoServe) {
    Write-Step "6/6  Selesai (-NoServe). Server TIDAK dijalankan."
    Write-Host ""
    Write-Host "  Hotspot : $Ssid  (password: $Passphrase)" -ForegroundColor White
    Write-Host "  URL     : http://${hsIp}:$Port" -ForegroundColor White
    return
}

Write-Step "6/6  Jalankan server (host 0.0.0.0)"
Write-Host ""
Write-Host "  ===============================================================" -ForegroundColor DarkGray
Write-Host "   LANGKAH UNTUK TIM:" -ForegroundColor White
Write-Host "   1) Sambungkan WiFi ke : $Ssid" -ForegroundColor White
Write-Host "      Password           : $Passphrase" -ForegroundColor White
Write-Host "   2) Buka browser       : http://${hsIp}:$Port" -ForegroundColor White
Write-Host "   (Biarkan jendela ini terbuka. Ctrl+C untuk berhenti.)" -ForegroundColor DarkGray
Write-Host "  ===============================================================" -ForegroundColor DarkGray
Write-Host ""

& php artisan serve --host=0.0.0.0 --port=$Port
