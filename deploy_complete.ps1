# Complete deployment script: Copy files then upload to Hostinger
param(
    [switch]$SkipCopy = $false
)

# Hostinger connection settings
$User = "u850419603_sarh"           
$HostIP = "145.223.119.139"    
$Port = "65002"                
$RemotePath = "/home/u850419603/domains/sarh.online/public_html"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "   Deploying Sarh System to Hostinger" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Copy files to dist
if (-not $SkipCopy) {
    Write-Host "Step 1: Copying updated files..." -ForegroundColor Yellow
    & ".\copy_to_dist.ps1"
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Failed to copy files!" -ForegroundColor Red
        exit 1
    }
    Write-Host ""
}

# Step 2: Check for SCP tool
Write-Host "Step 2: Checking connection tools..." -ForegroundColor Yellow
if (-not (Get-Command scp -ErrorAction SilentlyContinue)) {
    Write-Host "Error: SCP tool not found." -ForegroundColor Red
    Write-Host "   Please enable OpenSSH Client from:" -ForegroundColor Yellow
    Write-Host "   Settings > Apps > Optional Features > OpenSSH Client" -ForegroundColor Yellow
    exit 1
}
Write-Host "SCP tool available" -ForegroundColor Green
Write-Host ""

# Step 3: Upload files
Write-Host "Step 3: Uploading files to Hostinger..." -ForegroundColor Yellow
Write-Host "   User: $User" -ForegroundColor Gray
Write-Host "   Server: ${HostIP}:${Port}" -ForegroundColor Gray
Write-Host "   Path: $RemotePath" -ForegroundColor Gray
Write-Host ""
Write-Host "You will be prompted for password..." -ForegroundColor Yellow
Write-Host ""

# Use SCP to upload
$scpCommand = "scp -P $Port -r ./dist/* `"${User}@${HostIP}:${RemotePath}`""
Write-Host "Executing: $scpCommand" -ForegroundColor Gray
Write-Host ""

Invoke-Expression $scpCommand

if ($?) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "   Upload completed successfully!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Website: https://sarh.online" -ForegroundColor Cyan
    Write-Host ""
} else {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Red
    Write-Host "   Error occurred during upload" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please check:" -ForegroundColor Yellow
    Write-Host "  1. Internet connection" -ForegroundColor Gray
    Write-Host "  2. Connection credentials (username and password)" -ForegroundColor Gray
    Write-Host "  3. Server access permissions" -ForegroundColor Gray
    Write-Host ""
    exit 1
}
