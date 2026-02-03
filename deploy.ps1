# Deploy Sarh - upload files only (database: import manually via phpMyAdmin)

$User = "u307296675"
$HostIP = "194.164.74.250"
$Port = "65002"
$RemotePath = "/home/u307296675/domains/sarh.io/public_html"

if (-not (Get-Command scp -ErrorAction SilentlyContinue)) {
    Write-Host "Error: SCP not found. Enable OpenSSH Client in Windows settings." -ForegroundColor Red
    exit 1
}

Write-Host "Uploading files to server..." -ForegroundColor Cyan
scp -P $Port -r ./* "$User@$HostIP`:$RemotePath" 2>&1

if (-not $?) {
    Write-Host "Upload failed." -ForegroundColor Red
    exit 1
}

Write-Host "Upload done. Database: import manually from phpMyAdmin if needed." -ForegroundColor Green
