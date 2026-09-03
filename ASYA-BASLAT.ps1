param(
    [switch] $TarayiciAcma
)

$ErrorActionPreference = 'Stop'

$projectPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$runtimePath = Join-Path $projectPath 'storage\app\asya-local'
$watchdogPath = Join-Path $projectPath 'ASYA-YEREL-SISTEM.ps1'
$backupPath = Join-Path $projectPath 'ASYA-YEDEKLE.ps1'
$watchdogPidFile = Join-Path $runtimePath 'asya-watchdog.pid'
$powershellPath = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'

New-Item -ItemType Directory -Force -Path $runtimePath | Out-Null

$watchdogIsRunning = $false

if (Test-Path -LiteralPath $watchdogPidFile) {
    $watchdogProcessId = [int] (Get-Content -LiteralPath $watchdogPidFile -Raw)
    $watchdogProcess = Get-CimInstance Win32_Process -Filter "ProcessId = $watchdogProcessId" -ErrorAction SilentlyContinue
    $watchdogIsRunning = $null -ne $watchdogProcess -and $watchdogProcess.CommandLine -like "*$watchdogPath*"
}

if (-not $watchdogIsRunning) {
    $watchdogProcess = Start-Process -FilePath $powershellPath `
        -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$watchdogPath`"" `
        -WorkingDirectory $projectPath `
        -WindowStyle Hidden `
        -PassThru

    [IO.File]::WriteAllText($watchdogPidFile, [string] $watchdogProcess.Id)
}

$applicationBaseUrl = $env:ASYA_APP_URL
if ([string]::IsNullOrWhiteSpace($applicationBaseUrl)) {
    $applicationBaseUrl = 'http://127.0.0.1:8000'
}
$applicationUrl = '{0}/giris' -f $applicationBaseUrl
$applicationIsReady = $false

for ($attempt = 0; $attempt -lt 30; $attempt++) {
    try {
        $response = Invoke-WebRequest -Uri $applicationUrl -UseBasicParsing -TimeoutSec 2
        $applicationIsReady = $response.StatusCode -ge 200 -and $response.StatusCode -lt 500
    } catch {
        $applicationIsReady = $false
    }

    if ($applicationIsReady) {
        break
    }

    Start-Sleep -Seconds 1
}

if (-not $TarayiciAcma) {
    Start-Process $applicationUrl
}

if ($applicationIsReady) {
    try {
        & $powershellPath -NoProfile -ExecutionPolicy Bypass -File $backupPath -CommitMessage ('ASYA yenileme yedeği '.(Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
    } catch {
        Write-Host ('ASYA GitHub yedeği başarısız: '+$_.Exception.Message) -ForegroundColor Yellow
    }

    Write-Host 'ASYA hazir: http://127.0.0.1:8000/giris' -ForegroundColor Green
} else {
    Write-Host 'ASYA baslatildi ancak henuz yanit vermiyor. storage\logs icindeki asya-*-error.log dosyalarini kontrol edin.' -ForegroundColor Yellow
}