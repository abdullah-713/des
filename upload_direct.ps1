# Direct upload script with password
$User = "u850419603_sarh"           
$HostIP = "145.223.119.139"    
$Port = "65002"                
$RemotePath = "/home/u850419603/domains/sarh.online/public_html"
$Password = "Goolbx512!!"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "   Uploading Files to sarh.online" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if dist folder exists
if (-not (Test-Path "dist")) {
    Write-Host "Error: dist folder not found!" -ForegroundColor Red
    Write-Host "Running copy_to_dist.ps1 first..." -ForegroundColor Yellow
    & ".\copy_to_dist.ps1"
}

Write-Host "Checking files in dist folder..." -ForegroundColor Yellow
$fileCount = (Get-ChildItem -Path "dist" -Recurse -File).Count
Write-Host "Found $fileCount files to upload" -ForegroundColor Green
Write-Host ""

# List main files
Write-Host "Main files to upload:" -ForegroundColor Yellow
Get-ChildItem -Path "dist" -File | ForEach-Object {
    Write-Host "  - $($_.Name)" -ForegroundColor Gray
}
Write-Host ""

# Try using plink (PuTTY) if available
$plinkPath = Get-Command plink -ErrorAction SilentlyContinue

if ($plinkPath) {
    Write-Host "Using PuTTY (plink) for upload..." -ForegroundColor Green
    
    # Create pscp command (PuTTY's SCP)
    $pscpPath = Join-Path (Split-Path $plinkPath.Path) "pscp.exe"
    
    if (Test-Path $pscpPath) {
        Write-Host "Uploading files..." -ForegroundColor Yellow
        $pscpCommand = "& `"$pscpPath`" -P $Port -pw `"$Password`" -r ./dist/* ${User}@${HostIP}:${RemotePath}"
        Invoke-Expression $pscpCommand
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host ""
            Write-Host "========================================" -ForegroundColor Green
            Write-Host "   Upload completed successfully!" -ForegroundColor Green
            Write-Host "========================================" -ForegroundColor Green
            Write-Host ""
            Write-Host "Website: https://sarh.online" -ForegroundColor Cyan
            exit 0
        }
    }
}

# Fallback: Manual SCP command
Write-Host "Using SCP (you need to enter password manually)..." -ForegroundColor Yellow
Write-Host ""
Write-Host "Password: $Password" -ForegroundColor Gray
Write-Host ""
Write-Host "Executing command:" -ForegroundColor Yellow
Write-Host "scp -P $Port -r ./dist/* ${User}@${HostIP}:${RemotePath}" -ForegroundColor Cyan
Write-Host ""

# Execute SCP
$scpArgs = @("-P", $Port, "-r", "./dist/*", "${User}@${HostIP}:${RemotePath}")
Start-Process -FilePath "scp" -ArgumentList $scpArgs -NoNewWindow -Wait

Write-Host ""
Write-Host "Upload process completed." -ForegroundColor Green
Write-Host "Check https://sarh.online to verify" -ForegroundColor Cyan
