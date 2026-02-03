# Script to copy updated files to dist folder
Write-Host "Copying updated files to dist folder..." -ForegroundColor Cyan

# Create dist folder if it doesn't exist
if (-not (Test-Path "dist")) {
    New-Item -ItemType Directory -Path "dist" | Out-Null
    Write-Host "Created dist folder" -ForegroundColor Green
}

# قائمة الملفات والمجلدات المطلوبة للنشر
$filesToCopy = @(
    "index.php",
    "employee.php",
    "profile.php",
    "upgrade_users.php",
    "admin.php",
    "database_production.sql",
    "admin_api.php",
    "attendance_api.php",
    "check.php",
    "config.php",
    "logout.php",
    "manifest.json",
    "sw.js",
    "logo.png",
    "assets",
    "includes",
    "uploads"
)

# حذف محتويات dist القديمة (باستثناء uploads)
Write-Host "Cleaning dist folder..." -ForegroundColor Yellow
Get-ChildItem -Path "dist" -Exclude "uploads" | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue

# Copy files
foreach ($item in $filesToCopy) {
    if (Test-Path $item) {
        $destPath = Join-Path "dist" $item
        
        if (Test-Path $item -PathType Container) {
            # Copy folders
            Write-Host "  Copying folder: $item" -ForegroundColor Gray
            Copy-Item -Path $item -Destination $destPath -Recurse -Force -ErrorAction SilentlyContinue
        } else {
            # Copy files
            Write-Host "  Copying file: $item" -ForegroundColor Gray
            Copy-Item -Path $item -Destination $destPath -Force -ErrorAction SilentlyContinue
        }
    } else {
        Write-Host "  Warning: File not found: $item" -ForegroundColor Yellow
    }
}

# Copy .htaccess if exists
if (Test-Path ".htaccess") {
    Copy-Item -Path ".htaccess" -Destination "dist\.htaccess" -Force
    Write-Host "  Copying file: .htaccess" -ForegroundColor Gray
}

Write-Host "All files copied successfully to dist folder!" -ForegroundColor Green
Write-Host ""
