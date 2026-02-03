# Automated deployment with password
# This script uses plink (PuTTY) or expects manual password entry

$User = "u850419603_sarh"           
$HostIP = "145.223.119.139"    
$Port = "65002"                
$RemotePath = "/home/u850419603/domains/sarh.online/public_html"
$Password = "Goolbx512!!"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "   Deploying to sarh.online" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# First, copy files to dist
Write-Host "Step 1: Copying files to dist..." -ForegroundColor Yellow
& ".\copy_to_dist.ps1"
Write-Host ""

# Check if plink (PuTTY) is available
$plinkPath = Get-Command plink -ErrorAction SilentlyContinue

if ($plinkPath) {
    Write-Host "Step 2: Using PuTTY (plink) for upload..." -ForegroundColor Yellow
    
    # Create a temporary script file for plink
    $plinkScript = @"
cd $RemotePath
put -r dist/*
exit
"@
    
    $plinkScript | Out-File -FilePath "temp_plink.txt" -Encoding ASCII
    
    $plinkCommand = "echo y | plink -ssh -P $Port -pw `"$Password`" $User@$HostIP -m temp_plink.txt"
    Write-Host "Uploading files..." -ForegroundColor Gray
    Invoke-Expression $plinkCommand
    
    Remove-Item "temp_plink.txt" -ErrorAction SilentlyContinue
} else {
    Write-Host "Step 2: Using SCP (you'll need to enter password manually)..." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Password: $Password" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Run this command manually:" -ForegroundColor Yellow
    Write-Host "scp -P $Port -r ./dist/* ${User}@${HostIP}:${RemotePath}" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Or use WinSCP, FileZilla, or any FTP client:" -ForegroundColor Yellow
    Write-Host "  Host: $HostIP" -ForegroundColor Gray
    Write-Host "  Port: $Port" -ForegroundColor Gray
    Write-Host "  User: $User" -ForegroundColor Gray
    Write-Host "  Password: $Password" -ForegroundColor Gray
    Write-Host "  Path: $RemotePath" -ForegroundColor Gray
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "   Files ready in ./dist folder" -ForegroundColor Green
Write-Host "   Website: https://sarh.online" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Green
