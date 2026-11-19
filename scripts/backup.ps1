# Backs up the ITMS One database and private files.
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File scripts\backup.ps1
#   powershell -ExecutionPolicy Bypass -File scripts\backup.ps1 -Destination "D:\ITMS-Backup"
#
# Schedule with Windows Task Scheduler, see project_docs\ITMS_Administrative_App_Deployment_Guide.md.

param(
    [string]$Destination = $(if ($env:ITMS_BACKUP_PATH) { $env:ITMS_BACKUP_PATH } else { "C:\ITMS-Backup" }),
    [int]$RetentionDays = 30
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$target = Join-Path $Destination $stamp

function Read-EnvValue([string]$name) {
    $line = Select-String -Path (Join-Path $root ".env") -Pattern "^$name=(.*)$" | Select-Object -First 1
    if ($null -eq $line) { throw "Missing $name in .env" }
    return $line.Matches[0].Groups[1].Value
}

New-Item -ItemType Directory -Force -Path $target | Out-Null

$dbName = Read-EnvValue "DB_DATABASE"
$dbUser = Read-EnvValue "DB_USERNAME"
$dbPass = Read-EnvValue "DB_PASSWORD"
$dbHost = Read-EnvValue "DB_HOST"
$dbPort = Read-EnvValue "DB_PORT"

$dump = Join-Path $target "database.sql"
$mysqldump = if (Get-Command mysqldump -ErrorAction SilentlyContinue) {
    (Get-Command mysqldump).Source
} elseif (Test-Path "C:\mysql84\bin\mysqldump.exe") {
    "C:\mysql84\bin\mysqldump.exe"
} else {
    throw "mysqldump not found. Install MySQL client tools or add mysqldump to PATH."
}

Write-Host "Dumping database $dbName..."
& $mysqldump --host=$dbHost --port=$dbPort --user=$dbUser --password="$dbPass" `
    --single-transaction --routines --triggers --result-file="$dump" $dbName
if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE" }

Write-Host "Archiving private files..."
$files = Join-Path $target "private-files.zip"
if (Test-Path $files) { Remove-Item $files -Force }
Compress-Archive -Path (Join-Path $root "storage\app") -DestinationPath $files

Write-Host "Backup written to $target"

Get-ChildItem $Destination -Directory |
    Where-Object { $_.Name -match '^\d{8}-\d{6}$' } |
    Sort-Object Name -Descending |
    Select-Object -Skip $RetentionDays |
    ForEach-Object { Remove-Item $_.FullName -Recurse -Force; Write-Host "Removed old backup $($_.Name)" }
