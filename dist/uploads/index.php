<?php
// منع الوصول المباشر لمجلد الرفع
header('HTTP/1.0 403 Forbidden');
exit('Access Denied');
?>