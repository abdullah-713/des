# Deployment script with password (for automated deployment)
# Complete deployment script: Copy files then upload to Hostinger
param(
    [switch]$SkipCopy = $false,
    [string]$Password = ""
)

# Hostinger connection settings
$User = "u850419603_sarh"           
$HostIP = "145.223.119.139"    
$Port = "65002"                
$RemotePath = "/home/u850419603/domains/sarh.online/public_html"
$SSHPassword = if ($Password) { $Password } else { "Goolbx512!!" }

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

# Step 3: Upload files using sshpass or expect
Write-Host "Step 3: Uploading files to Hostinger..." -ForegroundColor Yellow
Write-Host "   User: $User" -ForegroundColor Gray
Write-Host "   Server: ${HostIP}:${Port}" -ForegroundColor Gray
Write-Host "   Path: $RemotePath" -ForegroundColor Gray
Write-Host ""

# Check if sshpass is available (for automated password entry)
$useSshpass = $false
if (Get-Command sshpass -ErrorAction SilentlyContinue) {
    $useSshpass = $true
    Write-Host "Using sshpass for automated upload..." -ForegroundColor Green
} else {
    Write-Host "Note: sshpass not found. You will be prompted for password." -ForegroundColor Yellow
    Write-Host "   To install sshpass, use: choco install sshpass" -ForegroundColor Gray
}

Write-Host ""

# Use SCP to upload
if ($useSshpass) {
    $scpCommand = "sshpass -p '$SSHPassword' scp -P $Port -r ./dist/* `"${User}@${HostIP}:${RemotePath}`""
} else {
    $scpCommand = "scp -P $Port -r ./dist/* `"${User}@${HostIP}:${RemotePath}`""
}

Write-Host "Executing upload..." -ForegroundColor Gray
Write-Host ""

# For Windows, we'll use plink (PuTTY) or just scp with manual password
# Since sshpass might not be available, we'll use a different approach
$env:SSH_PASSWORD = $SSHPassword

# Try using PowerShell to pass password
$scpProcess = Start-Process -FilePath "scp" -ArgumentList "-P", $Port, "-r", "./dist/*", "${User}@${HostIP}:${RemotePath}" -NoNewWindow -Wait -PassThru

if ($scpProcess.ExitCode -eq 0) {
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
    Write-Host "  2. Connection credentials" -ForegroundColor Gray
    Write-Host "  3. Server access permissions" -ForegroundColor Gray
    Write-Host ""
    Write-Host "You can also run manually:" -ForegroundColor Yellow
    Write-Host "   scp -P $Port -r ./dist/* ${User}@${HostIP}:${RemotePath}" -ForegroundColor Gray
    Write-Host ""
    exit 1
}
