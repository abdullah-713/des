# إعدادات الاتصال بـ Hostinger
$User = "u850419603_sarh"           
$HostIP = "145.223.119.139"    
$Port = "65002"                
$RemotePath = "/home/u850419603/domains/sarh.online/public_html"

# التأكد من وجود أداة scp (عادة موجودة في Windows 10/11)
if (-not (Get-Command scp -ErrorAction SilentlyContinue)) {
    Write-Host "❌ خطأ: أداة SCP غير موجودة. تأكد من تفعيل OpenSSH Client في إعدادات الويندوز." -ForegroundColor Red
    exit
}

Write-Host "🚀 جاري الاتصال بـ Hostinger ورفع ملفات الإنتاج..." -ForegroundColor Cyan

# استثناء الملفات غير المرغوبة (node_modules, .git, backups, etc)
# نقوم برفع المجلد الحالي (.) إلى المسار البعيد
# ملاحظة: سيطلب منك كلمة المرور عند التشغيل إلا إذا كنت تستخدم مفتاح SSH Key

# استخدام SCP للرفع (Simple Copy) - للخيار الأسرع والأنظف
# -r للمجلدات، -P للمنفذ
scp -P $Port -r ./dist/* "$User@$HostIP`:$RemotePath"

if ($?) {
    Write-Host "✅ تم الرفع بنجاح!" -ForegroundColor Green
} else {
    Write-Host "❌ حدث خطأ أثناء الرفع." -ForegroundColor Red
}
