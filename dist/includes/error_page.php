<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطأ في النظام</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; text-align: center; }
        .error-container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px; width: 100%; border: 1px solid #fee2e2; }
        h1 { color: #dc2626; margin-bottom: 20px; }
        p { color: #64748b; line-height: 1.6; margin-bottom: 25px; }
        .btn { background: #f97316; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 700; transition: background 0.3s; }
        .btn:hover { background: #c2410c; }
        .debug-info { margin-top: 20px; padding: 10px; background: #f1f5f9; border-radius: 8px; font-size: 0.8rem; color: #475569; text-align: left; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>⚠️ عطل فني</h1>
        <p><?php echo $errorMsg ?? 'حدث خطأ غير متوقع في النظام.'; ?></p>
        <a href="index.php" class="btn">تحديث الصفحة</a>
        <?php if (defined('ENABLE_ERROR_DISPLAY') && ENABLE_ERROR_DISPLAY && isset($e)): ?>
        <div class="debug-info">
            <strong>معلومات تقنية:</strong><br>
            <?php echo htmlspecialchars($e->getMessage()); ?><br>
            File: <?php echo basename($e->getFile()); ?>:<?php echo $e->getLine(); ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
