$ErrorActionPreference = 'Stop'

$projectPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$runtimePath = Join-Path $projectPath 'storage\app\asya-local'
$processNames = @('asya-watchdog', 'asya-server', 'asya-queue', 'asya-queue-ingestion', 'asya-queue-content', 'asya-queue-publishing', 'asya-queue-operations', 'asya-schedule')

foreach ($processName in $processNames) {
    $pidFile = Join-Path $runtimePath "$processName.pid"

    if (-not (Test-Path -LiteralPath $pidFile)) {
        continue
    }

    $processId = [int] (Get-Content -LiteralPath $pidFile -Raw)
    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $processId" -ErrorAction SilentlyContinue

    if ($null -ne $process -and $process.CommandLine -like "*$projectPath*") {
        Stop-Process -Id $processId -Force
    }

    Remove-Item -LiteralPath $pidFile -Force -ErrorAction SilentlyContinue
}

Write-Host 'ASYA yerel servisleri durduruldu.' -ForegroundColor Green
