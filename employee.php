<?php
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['employee_code'])) {
    header('Location: index.php');
    exit;
}

$employeeCode = $_SESSION['employee_code'];
$employeeName = $_SESSION['employee_name'] ?? 'موظف';
$companyName = SystemSettings::get('company_name', 'صرح انضباط');
$allowLogout = SystemSettings::get('allow_employee_logout', '0'); // 0 = لا يسمح، 1 = يسمح
$logoPath = SystemSettings::get('company_logo', '');
if (!$logoPath || !file_exists($logoPath)) {
    $logoPath = file_exists('uploads/logo.png') ? 'uploads/logo.png' : (file_exists('logo.png') ? 'logo.png' : '');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f97316">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($logoPath ?: 'logo.png'); ?>">
    <title><?php echo htmlspecialchars($companyName); ?> - <?php echo __('app_name'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .privacy-modal {
            display: none; 
            position: fixed; 
            z-index: 9999; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
        }
        .privacy-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }
        .privacy-header {
            padding: 20px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .privacy-body {
            padding: 25px;
            overflow-y: auto;
            line-height: 1.8;
            font-size: 1.1rem;
            color: #334155;
            flex-grow: 1;
        }
        .privacy-footer {
            padding: 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-radius: 0 0 15px 15px;
            text-align: center;
        }
        .lang-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .lang-tab {
            padding: 8px 16px;
            background: #f1f5f9;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .lang-tab.active {
            background: #f97316;
            color: white;
        }
        #acceptBtn {
            background: #10b981;
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: not-allowed;
            opacity: 0.6;
            transition: all 0.3s;
        }
        #acceptBtn:not(:disabled) {
            cursor: pointer;
            opacity: 1;
        }
        #timer {
            font-weight: bold;
            color: #ef4444;
        }
    </style>
</head>
<body>
    <!-- Privacy Policy Modal -->
    <div id="privacyModal" class="privacy-modal">
        <div class="privacy-content">
            <div class="privacy-header">
                <h2 id="privacyTitle">🔒 سياسة الخصوصية</h2>
                <div class="lang-tabs">
                    <button class="lang-tab active" onclick="switchPrivacyLang('ar')">العربية</button>
                    <button class="lang-tab" onclick="switchPrivacyLang('en')">English</button>
                    <button class="lang-tab" onclick="switchPrivacyLang('ur')">اردو</button>
                    <button class="lang-tab" onclick="switchPrivacyLang('hi')">हिंदी</button>
                    <button class="lang-tab" onclick="switchPrivacyLang('tl')">Tagalog</button>
                    <button class="lang-tab" onclick="switchPrivacyLang('fil')">Filipino</button>
                </div>
            </div>
            <div class="privacy-body" id="privacyText">
                <!-- Content will be loaded here -->
            </div>
            <div class="privacy-footer">
                <div style="margin-bottom: 15px; font-weight: 600; color: #64748b;">
                    يرجى قراءة الوثيقة بعناية. يمكنك الموافقة بعد <span id="timer">60</span> ثانية.
                </div>
                <button id="acceptBtn" disabled onclick="acceptPrivacy()">أوافق على الشروط والأحكام</button>
            </div>
        </div>
    </div>
    <div class="lang-switcher">
        <a href="?lang=<?php echo $_SESSION['lang'] === 'ar' ? 'en' : 'ar'; ?>" class="lang-btn">
            <?php echo $_SESSION['lang'] === 'ar' ? 'English' : 'العربية'; ?>
        </a>
    </div>
    <?php if ($allowLogout === '1'): ?>
    <a href="logout.php" class="logout-btn">🚪 <?php echo __('logout'); ?></a>
    <?php endif; ?>

    <div class="container">
        <div class="install-banner" id="installBanner" data-logo="<?php echo htmlspecialchars($logoPath); ?>">
            <?php if ($logoPath): ?>
            <img src="<?php echo $logoPath; ?>" alt="Logo">
            <?php endif; ?>
            <div>
                <div style="font-weight: 800; font-size: 1rem;"><?php echo $_SESSION['lang'] === 'ar' ? 'ثبّت التطبيق على جهازك' : 'Install app on your device'; ?></div>
                <div style="font-size: 0.9rem; opacity: 0.9;"><?php echo $_SESSION['lang'] === 'ar' ? 'وصول أسرع وتجربة أفضل' : 'Faster access and better experience'; ?></div>
            </div>
            <button id="installButton"><?php echo $_SESSION['lang'] === 'ar' ? 'تثبيت الآن' : 'Install Now'; ?></button>
            <button class="close" id="installClose">×</button>
        </div>
        <!-- Announcement Box -->
        <div class="announcement-box" id="announcementBox" style="display: none;">
            <div class="announcement-content">
                <i class="announcement-icon">📢</i>
                <span id="announcementText"></span>
            </div>
        </div>

        <!-- Top Banner for Best/Worst Employees -->
        <div class="top-banner" id="topBanner" style="display: none;">
            <div class="banner-content" id="bannerContent"></div>
        </div>

        <!-- Header -->
        <div class="header">
            <!-- V2: Branch Badge at Top -->
            <div style="display: inline-block; background: rgba(255,255,255,0.25); padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.3);">
                🏢 <span id="headerBranchName">...</span>
            </div>

            <div style="display: flex; align-items: center; justify-content: center; gap: 20px; margin-bottom: 15px;">
                <?php if ($logoPath): ?>
                <img src="<?php echo $logoPath; ?>" alt="Company Logo" style="max-height: 70px; max-width: 100px; background: white; padding: 8px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <?php endif; ?>
                <div>
                    <h1><?php echo htmlspecialchars($companyName); ?></h1>
                    <p><?php echo __('app_name'); ?></p>
                </div>
            </div>
            <div class="employee-welcome">
        👋 <?php echo __('welcome'); ?> <strong style="cursor: pointer; text-decoration: underline;" onclick="openProfile()"><?php echo htmlspecialchars($employeeName); ?></strong>
        <button onclick="openProfile()" class="btn-profile-icon">👤</button>
    </div>
            <div class="current-time" id="currentTime">--:--:--</div>
            <div id="currentDate"></div>
        </div>

        <!-- System Status -->
        <div id="systemStatus" class="system-status"></div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Attendance Section -->
        <div class="attendance-section">
            <input type="hidden" id="employeeCode" value="<?php echo htmlspecialchars($employeeCode); ?>">
            
            <div style="margin-bottom: 25px; padding: 15px; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 12px; border: 2px solid #86efac;">
                <div style="font-size: 1.1rem; color: #166534; font-weight: 700;">
                    🎫 <?php echo __('employee_code'); ?>: <span style="font-size: 1.3rem; color: #15803d;"><?php echo htmlspecialchars($employeeCode); ?></span>
                </div>
            </div>
            
            <div class="status-hero" id="statusHero">
                <div class="status-hero-main">
                    <div class="status-hero-label"><?php echo __('today_status'); ?></div>
                    <div class="status-hero-value" id="statusHeroText"><?php echo __('not_checked_in'); ?></div>
                    <div class="status-hero-meta" id="statusHeroMeta">...</div>
                    <div class="status-hero-time" id="statusHeroTime">--:--</div>
                </div>
                <div class="status-hero-badge" id="statusHeroBadge" style="background: #ef4444;">--</div>
            </div>

            <div class="attendance-buttons">
                <button id="checkInBtn" class="btn btn-check-in">
                    ✅ <?php echo __('check_in'); ?>
                </button>
                <button id="checkOutBtn" class="btn btn-check-out">
                    🚪 <?php echo __('check_out'); ?>
                </button>
            </div>

            <div id="employeeInfo" style="display: none; margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 8px;">
                <!-- Employee info here -->
            </div>
        </div>

        <!-- General Stats (V2: Split Delay/Reward, Removed Net Balance) -->
        <div class="stats-grid">
            <!-- Delay Card (Red/Orange) -->
            <div class="stat-card glass-card" style="border-right-color: var(--danger); background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);">
                <div class="stat-value" style="color: var(--danger);" id="lateCount">0</div>
                <div class="stat-label"><?php echo __('delay'); ?> (دقيقة)</div>
                <div style="font-size: 0.8rem; color: #ef4444; margin-top: 5px;" id="totalDeductionsText">0 خصم</div>
            </div>
            
            <!-- Reward Card (Green/Gold) -->
            <div class="stat-card glass-card" style="border-right-color: var(--success); background: linear-gradient(135deg, #f0fdf4 0%, #fff 100%);">
                <div class="stat-value" style="color: #059669;" id="totalRewardPoints">0</div>
                <div class="stat-label"><?php echo __('rewards'); ?> (نقطة)</div>
                <div style="font-size: 0.8rem; color: #059669; margin-top: 5px;">🌟 رصيد إيجابي</div>
            </div>
        </div>

        <!-- Branch Statistics -->
        <div style="margin-bottom: 20px;">
            <h2 style="color: var(--primary-dark); font-size: 1.5rem; font-weight: 800; margin-bottom: 20px; text-align: center;">
                📈 إحصائيات الفروع
            </h2>
            <div class="branch-stats" id="branchStats">
                <!-- إحصائيات الفروع ستظهر هنا -->
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>👤 الملف الشخصي</h3>
                <span class="close" onclick="closeModal('profileModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="profile-header">
                    <div class="profile-image-container">
                        <img id="profileImage" src="" alt="Profile">
                        <button class="edit-image-btn" onclick="document.getElementById('imageInput').click()">📷</button>
                        <input type="file" id="imageInput" hidden accept="image/*" onchange="uploadImage(this)">
                    </div>
                    <div class="profile-info">
                        <h2 id="profileName">--</h2>
                        <p id="profilePosition">--</p>
                        <div class="profile-rating" id="profileRating">⭐⭐⭐⭐⭐</div>
                    </div>
                </div>
                
                <div class="profile-stats-grid">
                    <div class="p-stat">
                        <span class="label">الترتيب</span>
                        <span class="value" id="profileRank">#--</span>
                    </div>
                    <div class="p-stat">
                        <span class="label">النقاط</span>
                        <span class="value" id="profilePoints">--</span>
                    </div>
                    <div class="p-stat">
                        <span class="label">أيام الحضور</span>
                        <span class="value" id="profileDays">--</span>
                    </div>
                    <div class="p-stat">
                        <span class="label">تأخير (دقيقة)</span>
                        <span class="value" id="profileDelay">--</span>
                    </div>
                </div>
                
                <div class="password-section">
                    <h4>🔐 تغيير كلمة المرور</h4>
                    <div class="form-group">
                        <input type="password" id="newPassword" placeholder="كلمة المرور الجديدة">
                    </div>
                    <button onclick="changePassword()" class="btn btn-primary" style="width: 100%; background: var(--primary); color: white;">حفظ كلمة المرور</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // متغيرات عامة
        let systemSettings = {};
        let isLoading = false;
        let bannerEmployees = [];
        let currentBannerIndex = 0;
        let bannerInterval;
        let privacyTimerInterval;
        let privacyTimeLeft = 60;

        // Privacy Policy Content (Multi-language)
        const privacyContent = {
            ar: `<h3>1. مقدمة والتزامنا (رؤية 2030)</h3>
<p>في <strong>"صرح انضباط"</strong>، نؤمن بأن البيانات هي وقود الاقتصاد الرقمي، وأن حماية الخصوصية هي حجر الزاوية لبناء الثقة الرقمية، وهو ما يتماشى تماماً مع مستهدفات <strong>رؤية المملكة العربية السعودية 2030</strong> في تعزيز البنية التحتية الرقمية ودعم التحول الرقمي الآمن.</p>
<h3>2. المرجعيات النظامية والامتثال (الدليل القانوني)</h3>
<p>تستند هذه السياسة وتلتزم بشكل صارم بالأنظمة واللوائح التالية المعمول بها في المملكة العربية السعودية:</p>
<ul>
<li><strong>نظام حماية البيانات الشخصية (PDPL):</strong> الصادر بالمرسوم الملكي رقم (م/19).</li>
<li><strong>نظام مكافحة جرائم المعلوماتية:</strong> الصادر بالمرسوم الملكي رقم (م/17).</li>
<li><strong>الضوابط الأساسية للأمن السيبراني (ECC):</strong> الصادرة عن الهيئة الوطنية للأمن السيبراني (NCA).</li>
</ul>
<h3>3. حقوقك</h3>
<p>لديك الحق في الوصول إلى بياناتك، وتصحيحها، وحذفها، وسحب موافقتك في أي وقت.</p>`,
            
            en: `<h3>1. Introduction & Commitment (Vision 2030)</h3>
<p>At <strong>"Sarh Discipline"</strong>, we believe data fuels the digital economy. Protecting privacy is the cornerstone of digital trust, aligning perfectly with <strong>Saudi Vision 2030</strong> goals.</p>
<h3>2. Legal Compliance</h3>
<p>This policy strictly adheres to Saudi laws including:</p>
<ul>
<li><strong>Personal Data Protection Law (PDPL):</strong> Royal Decree No. (M/19).</li>
<li><strong>Anti-Cyber Crime Law:</strong> Royal Decree No. (M/17).</li>
<li><strong>Essential Cybersecurity Controls (ECC):</strong> By NCA.</li>
</ul>
<h3>3. Your Rights</h3>
<p>You have the right to access, correct, delete your data, and withdraw consent at any time.</p>`,

            ur: `<h3>1. تعارف اور عزم (وژن 2030)</h3>
<p><strong>"صرح انضباط"</strong> میں، ہم یقین رکھتے ہیں کہ ڈیٹا ڈیجیٹل معیشت کا ایندھن ہے۔ پرائیویسی کا تحفظ ڈیجیٹل اعتماد کا سنگ بنیاد ہے، جو <strong>سعودی وژن 2030</strong> کے اہداف کے عین مطابق ہے۔</p>
<h3>2. قانونی تعمیل</h3>
<p>یہ پالیسی سعودی قوانین کی سختی سے پابندی کرتی ہے جن میں شامل ہیں:</p>
<ul>
<li><strong>ذاتی ڈیٹا پروٹیکشن قانون (PDPL):</strong> شاہی فرمان نمبر (M/19)۔</li>
<li><strong>سائبر کرائم کی روک تھام کا قانون:</strong> شاہی فرمان نمبر (M/17)۔</li>
</ul>`,

            hi: `<h3>1. परिचय और प्रतिबद्धता (विज़न 2030)</h3>
<p><strong>"सरह अनुशासन"</strong> में, हम मानते हैं कि डेटा डिजिटल अर्थव्यवस्था का ईंधन है। गोपनीयता की रक्षा करना डिजिटल विश्वास की आधारशिला है, जो <strong>सऊदी विज़न 2030</strong> के लक्ष्यों के साथ पूरी तरह से मेल खाता है।</p>
<h3>2. कानूनी अनुपालन</h3>
<p>यह नीति सऊदी कानूनों का सख्ती से पालन करती है:</p>
<ul>
<li><strong>व्यक्तिगत डेटा संरक्षण कानून (PDPL):</strong> शाही फरमान संख्या (M/19)।</li>
</ul>`,

            tl: `<h3>1. Panimula at Pangako (Vision 2030)</h3>
<p>Sa <strong>"Sarh Discipline"</strong>, naniniwala kami na ang data ay ang gasolina ng digital na ekonomiya. Ang pagprotekta sa privacy ay ang pundasyon ng digital trust, na umaayon sa <strong>Saudi Vision 2030</strong>.</p>
<h3>2. Pagsunod sa Batas</h3>
<p>Ang patakarang ito ay mahigpit na sumusunod sa mga batas ng Saudi kabilang ang:</p>
<ul>
<li><strong>Personal Data Protection Law (PDPL):</strong> Royal Decree No. (M/19).</li>
</ul>`,
            
            fil: `<h3>1. Panimula at Komitment (Vision 2030)</h3>
<p>Sa <strong>"Sarh Discipline"</strong>, naniniwala kami na ang data ay mahalaga. Ang proteksyon ng privacy ay susi sa tiwala, ayon sa <strong>Saudi Vision 2030</strong>.</p>
<h3>2. Legal na Pagsunod</h3>
<p>Sumusunod kami sa mga batas ng Saudi tulad ng:</p>
<ul>
<li><strong>Personal Data Protection Law (PDPL):</strong> Royal Decree No. (M/19).</li>
</ul>`
        };

        function checkPrivacyAcceptance() {
            // Check if user has already accepted privacy policy
            const hasAccepted = localStorage.getItem('privacy_accepted_v1');
            if (!hasAccepted) {
                showPrivacyModal();
            }
        }

        function showPrivacyModal() {
            const modal = document.getElementById('privacyModal');
            modal.style.display = 'block';
            
            // Set default content
            switchPrivacyLang('ar');
            
            // Start Timer
            const timerEl = document.getElementById('timer');
            const btn = document.getElementById('acceptBtn');
            
            privacyTimeLeft = 60;
            timerEl.textContent = privacyTimeLeft;
            btn.disabled = true;
            btn.style.opacity = '0.6';
            btn.style.cursor = 'not-allowed';

            if (privacyTimerInterval) clearInterval(privacyTimerInterval);
            
            privacyTimerInterval = setInterval(() => {
                privacyTimeLeft--;
                timerEl.textContent = privacyTimeLeft;
                
                if (privacyTimeLeft <= 0) {
                    clearInterval(privacyTimerInterval);
                    timerEl.parentElement.innerHTML = '✅ شكراً لقراءتك. يمكنك الآن الموافقة.';
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                }
            }, 1000);
        }

        function switchPrivacyLang(lang) {
            const content = privacyContent[lang] || privacyContent['en'];
            document.getElementById('privacyText').innerHTML = content;
            document.getElementById('privacyText').dir = (lang === 'ar' || lang === 'ur') ? 'rtl' : 'ltr';
            
            // Update Active Tab
            document.querySelectorAll('.lang-tab').forEach(btn => btn.classList.remove('active'));
            const activeBtn = Array.from(document.querySelectorAll('.lang-tab')).find(b => b.getAttribute('onclick').includes(lang));
            if (activeBtn) activeBtn.classList.add('active');
        }

        function acceptPrivacy() {
            localStorage.setItem('privacy_accepted_v1', 'true');
            document.getElementById('privacyModal').style.display = 'none';
            showAlert('شكراً لك، تم حفظ موافقتك بنجاح ✅', 'success');
        }

        // فتح الملف الشخصي
        async function openProfile() {
            const employeeCode = document.getElementById('employeeCode').value;
            if (!employeeCode) return;
            
            try {
                const result = await postApi('get_profile_data', { employee_code: employeeCode });
                if (result.success) {
                    const data = result.data;
                    const emp = data.employee;
                    const stats = data.stats;
                    
                    document.getElementById('profileName').textContent = emp.name;
                    document.getElementById('profilePosition').textContent = `${emp.position || 'موظف'} - ${emp.branch_name}`;
                    document.getElementById('profilePoints').textContent = emp.points_balance;
                    
                    // Image
                    const defaultImg = `https://ui-avatars.com/api/?name=${encodeURIComponent(emp.name)}&background=random&size=200`;
                    document.getElementById('profileImage').src = emp.profile_image || defaultImg;
                    
                    // Stats
                    document.getElementById('profileRank').textContent = '#' + (data.rank || '-');
                    document.getElementById('profileDays').textContent = stats.total_days || 0;
                    document.getElementById('profileDelay').textContent = stats.total_delay_minutes || 0;
                    
                    // Rating
                    const rating = data.rating || 0;
                    const stars = '⭐'.repeat(rating) + '☆'.repeat(5 - rating);
                    document.getElementById('profileRating').textContent = stars;
                    
                    document.getElementById('profileModal').style.display = 'block';
                }
            } catch (error) {
                console.error(error);
                showAlert('حدث خطأ في تحميل الملف الشخصي', 'error');
            }
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        // رفع صورة
        async function uploadImage(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const formData = new FormData();
                formData.append('action', 'upload_profile_image');
                formData.append('employee_code', document.getElementById('employeeCode').value);
                formData.append('image', file);
                
                try {
                    const response = await fetch('attendance_api.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        document.getElementById('profileImage').src = result.image_url;
                        showAlert('تم تحديث الصورة الشخصية', 'success');
                    } else {
                        showAlert(result.message, 'error');
                    }
                } catch (error) {
                    showAlert('حدث خطأ في رفع الصورة', 'error');
                }
            }
        }
        
        // تغيير كلمة المرور
        async function changePassword() {
            const newPass = document.getElementById('newPassword').value;
            if (!newPass) {
                showAlert('يرجى إدخال كلمة المرور الجديدة', 'error');
                return;
            }
            
            try {
                const result = await postApi('update_password', {
                    employee_code: document.getElementById('employeeCode').value,
                    password: newPass
                });
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    document.getElementById('newPassword').value = '';
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في تحديث كلمة المرور', 'error');
            }
        }
        
        // إغلاق النافذة عند النقر خارجها
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // تحديث الوقت والتاريخ
        function updateDateTime() {
            const now = new Date();
            const lang = '<?php echo $_SESSION['lang'] === 'ar' ? 'ar-SA' : 'en-US'; ?>';
            
            // الوقت
            const timeString = now.toLocaleTimeString(lang, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            document.getElementById('currentTime').textContent = timeString;
            
            // التاريخ
            const dateString = now.toLocaleDateString(lang, {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('currentDate').textContent = dateString;
        }

        // عرض رسالة تنبيه
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'error' ? 'alert-error' : 'alert-warning';
            
            alertContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;
            
            // إخفاء التنبيه بعد 5 ثواني
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        async function postApi(action, payload = {}) {
            const response = await fetch('attendance_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action, ...payload })
            });

            const contentType = response.headers.get('content-type') || '';
            const text = await response.text();

            if (!contentType.includes('application/json')) {
                throw new Error('استجابة غير صالحة من الخادم');
            }

            let result;
            try {
                result = JSON.parse(text);
            } catch {
                throw new Error('استجابة غير صالحة من الخادم');
            }

            return result;
        }

        function getStatusMeta(data) {
            if (!data || !data.check_in_time) {
                return { text: '<?php echo __('not_checked_in'); ?>', color: '#ef4444' };
            }
            
            const earlyMinutes = Number(data.early_minutes || 0);
            const delayMinutes = Number(data.delay_minutes || 0);
            
            if (earlyMinutes >= 60) return { text: '<?php echo __('status_very_early'); ?>', color: '#10b981' };
            if (earlyMinutes >= 30) return { text: '<?php echo __('status_super_active'); ?>', color: '#10b981' };
            if (earlyMinutes > 0) return { text: '<?php echo __('status_active'); ?>', color: '#3b82f6' };
            
            if (delayMinutes > 30) return { text: '<?php echo __('status_very_late'); ?>', color: '#dc2626' };
            if (delayMinutes > 0) return { text: '<?php echo __('status_late'); ?>', color: '#f59e0b' };
            
            return { text: '<?php echo __('status_present'); ?>', color: '#10b981' };
        }

        function updateStatusHero(meta, subtitle) {
            const heroText = document.getElementById('statusHeroText');
            const heroMeta = document.getElementById('statusHeroMeta');
            const heroBadge = document.getElementById('statusHeroBadge');
            const heroTime = document.getElementById('statusHeroTime');
            if (!heroText || !heroMeta || !heroBadge || !heroTime) return;

            heroText.textContent = meta.text;
            heroMeta.textContent = subtitle || '<?php echo __('not_checked_in'); ?>';
            heroBadge.textContent = meta.text;
            heroBadge.style.background = meta.color;
            const now = new Date();
            const lang = '<?php echo $_SESSION['lang'] === 'ar' ? 'ar-SA' : 'en-US'; ?>';
            heroTime.textContent = '<?php echo $_SESSION['lang'] === 'ar' ? 'آخر تحديث' : 'Last update'; ?>: ' + now.toLocaleTimeString(lang, { hour: '2-digit', minute: '2-digit' });
        }

        // تحديث حالة الأزرار
        function updateButtonStates() {
            const checkInBtn = document.getElementById('checkInBtn');
            const checkOutBtn = document.getElementById('checkOutBtn');
            
            if (!systemSettings.attendance_enabled || systemSettings.attendance_enabled === '0') {
                checkInBtn.disabled = true;
                checkOutBtn.disabled = true;
                return;
            }
            
            if (systemSettings.attendance_mode === 'check_in') {
                checkInBtn.disabled = false;
                checkOutBtn.disabled = true;
            } else if (systemSettings.attendance_mode === 'check_out') {
                checkInBtn.disabled = true;
                checkOutBtn.disabled = false;
            } else {
                checkInBtn.disabled = false;
                checkOutBtn.disabled = false;
            }
        }

        // تحديث حالة النظام
        function updateSystemStatus() {
            const statusDiv = document.getElementById('systemStatus');
            
            if (!systemSettings.attendance_enabled || systemSettings.attendance_enabled === '0') {
                statusDiv.className = 'system-status status-disabled';
                statusDiv.textContent = '⚠️ نظام الحضور غير مفعل حالياً';
            } else {
                statusDiv.className = 'system-status status-enabled';
                const mode = systemSettings.attendance_mode === 'check_out' ? 'تسجيل الانصراف' : 'تسجيل الحضور';
                statusDiv.textContent = `✅ النظام مفعل - الوضع الحالي: ${mode}`;
            }
        }

        // جلب حالة النظام
        async function fetchSystemStatus() {
            try {
                const employeeCode = document.getElementById('employeeCode').value;
                const result = await postApi('get_system_status', { employee_code: employeeCode });
                if (result.success) {
                    systemSettings = result.data;
                    updateSystemStatus();
                    updateButtonStates();
                    updateAnnouncement();
                }
            } catch (error) {
                console.error('خطأ في جلب حالة النظام:', error);
            }
        }

        // تحديث مربع الإعلانات
        function updateAnnouncement() {
            const box = document.getElementById('announcementBox');
            const textEl = document.getElementById('announcementText');
            
            if (systemSettings.announcement_visible === '1' && systemSettings.announcement_text) {
                box.style.display = 'block';
                textEl.textContent = systemSettings.announcement_text;
            } else {
                box.style.display = 'none';
            }
        }

        // جلب الإحصائيات
        async function fetchStats() {
            try {
                const result = await postApi('get_stats');
                if (result.success) {
                    updateGeneralStats(result.data.general);
                    updateBranchStats(result.data.branches);
                    await fetchBannerEmployees();
                }
            } catch (error) {
                console.error('خطأ في جلب الإحصائيات:', error);
            }
        }

        // جلب بيانات الموظفين للشريط العلوي
        async function fetchBannerEmployees() {
            try {
                const result = await postApi('get_banner_employees');
                if (result.success) {
                    bannerEmployees = result.data;
                    startBannerRotation();
                }
            } catch (error) {
                console.error('خطأ في جلب بيانات الشريط:', error);
            }
        }

        // بدء دوران الشريط العلوي
        function startBannerRotation() {
            const banner = document.getElementById('topBanner');
            const content = document.getElementById('bannerContent');
            
            if (bannerEmployees.length === 0) {
                banner.style.display = 'none';
                return;
            }
            banner.style.display = 'block';
            
            // إيقاف الدوران السابق إن وجد
            if (bannerInterval) {
                clearInterval(bannerInterval);
            }
            
            // عرض أول موظف
            showBannerEmployee(0);
            
            // بدء الدوران كل 3 ثواني
            bannerInterval = setInterval(() => {
                currentBannerIndex = (currentBannerIndex + 1) % bannerEmployees.length;
                showBannerEmployee(currentBannerIndex);
            }, 3000);
        }

        // عرض موظف في الشريط العلوي
        function showBannerEmployee(index) {
            if (!bannerEmployees[index]) return;
            
            const employee = bannerEmployees[index];
            const banner = document.getElementById('topBanner');
            const content = document.getElementById('bannerContent');
            
            // تحديد اللون حسب نوع الموظف
            if (employee.type === 'best') {
                banner.className = 'top-banner best';
                const status = employee.status || '<?php echo __('status_present'); ?>';
                const points = employee.reward_points || 0;
                const msg = '<?php echo $_SESSION['lang'] === 'ar' ? 'متميز' : 'Excellent'; ?>';
                content.innerHTML = `🏆 ${msg}: ${employee.name} - ${employee.branch_name} <br> <span style="font-size:0.9em; opacity:0.9">(${status} +${points} <?php echo __('points'); ?>)</span>`;
            } else {
                banner.className = 'top-banner worst';
                const msg = '<?php echo $_SESSION['lang'] === 'ar' ? 'سلحفاة' : 'Slow'; ?>';
                content.innerHTML = `🐢 ${msg}: ${employee.name} - ${employee.branch_name} <br> <span style="font-size:0.9em; opacity:0.9">(${employee.deduction_points || 0} <?php echo __('points'); ?>)</span>`;
            }
            
            // إعادة تشغيل الأنيميشن
            content.style.animation = 'none';
            setTimeout(() => {
                content.style.animation = 'slideIn 0.5s ease-in-out';
            }, 10);
        }

        // تحديث الإحصائيات العامة
        function updateGeneralStats(stats) {
            // V2: Updated IDs and Logic
            // Late Count is now total delay minutes
            document.getElementById('lateCount').textContent = stats.total_delay_minutes || 0;
            document.getElementById('totalDeductionsText').textContent = (stats.total_deductions || 0) + ' نقطة خصم';
            
            document.getElementById('totalRewardPoints').textContent = stats.total_reward_points || 0;
            // Removed attendanceRate (Net Balance) update
        }

        // تحديث إحصائيات الفروع
        function updateBranchStats(branches) {
            const container = document.getElementById('branchStats');
            container.innerHTML = '';

            if (!Array.isArray(branches) || branches.length === 0) {
                container.innerHTML = '<div class="alert alert-warning">لا توجد بيانات للفروع حالياً</div>';
                return;
            }

            const sarhColors = ['#f97316', '#ea580c', '#c2410c', '#9a3412'];
            const otherColors = ['#dc2626', '#b91c1c', '#991b1b', '#7f1d1d'];

            let sarhIdx = 0;
            let otherIdx = 0;

            const normalized = branches.map(branch => {
                const totalEmployees = Number(branch.total_employees || 0);
                const presentCount = Number(branch.present_count || 0);
                const lateCount = Number(branch.late_count || 0);
                const onTimeCount = Math.max(presentCount - lateCount, 0);
                const attendanceRate = totalEmployees > 0 ? Math.round((onTimeCount / totalEmployees) * 100) : 0;
                return {
                    ...branch,
                    attendance_rate: attendanceRate,
                    total_employees: totalEmployees,
                    present_count: presentCount,
                    late_count: lateCount,
                    total_delay_minutes: Number(branch.total_delay_minutes || 0)
                };
            });

            const maxRate = Math.max(...normalized.map(branch => branch.attendance_rate), 0);
            normalized.forEach(branch => {
                let stars = 0;
                if (maxRate > 0 && branch.total_employees > 0) {
                    stars = Math.max(1, Math.round((branch.attendance_rate / maxRate) * 5));
                }
                branch.star_rating = Math.min(5, stars);
            });

            normalized.sort((a, b) => {
                if (b.star_rating !== a.star_rating) return b.star_rating - a.star_rating;
                if (b.attendance_rate !== a.attendance_rate) return b.attendance_rate - a.attendance_rate;
                return (a.name || '').localeCompare(b.name || '', 'ar');
            });

            const leaderboard = document.createElement('div');
            leaderboard.className = 'leaderboard';

            const header = document.createElement('div');
            header.className = 'leaderboard-header';
            header.innerHTML = `
                <div>الترتيب</div>
                <div>الفرع</div>
                <div>النجوم</div>
            `;
            leaderboard.appendChild(header);

            normalized.forEach((branch, index) => {
                let borderColor;
                let icon = '🏢';

                if ((branch.name || '').includes('صرح')) {
                    borderColor = sarhColors[sarhIdx % sarhColors.length];
                    sarhIdx += 1;
                    icon = '🏛️';
                } else if ((branch.name || '').includes('فضاء') || (branch.name || '').includes('المحركات')) {
                    borderColor = otherColors[otherIdx % otherColors.length];
                    otherIdx += 1;
                    icon = '🚀';
                } else {
                    borderColor = otherColors[otherIdx % otherColors.length];
                    otherIdx += 1;
                }

                const starCount = Math.max(0, Math.min(5, branch.star_rating || 0));
                const starText = starCount > 0 ? '★'.repeat(starCount) + '☆'.repeat(5 - starCount) : '—';
                const rankClass = index === 0 ? 'top-1' : index === 1 ? 'top-2' : index === 2 ? 'top-3' : '';
                const rankBadge = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '•';

                const row = document.createElement('div');
                row.className = `leaderboard-row ${rankClass}`;
                row.innerHTML = `
                    <div class="leaderboard-rank">
                        <span>${rankBadge}</span>
                        <span>${index + 1}</span>
                    </div>
                    <div class="branch-stats">
                        <div class="leaderboard-name" style="color: ${borderColor};">${icon} ${branch.name}</div>
                        <div class="leaderboard-meta">
                            <span>المتأخرون: ${branch.late_count || 0}</span>
                            <span>دقائق التأخير: ${branch.total_delay_minutes || 0}</span>
                            <span>نسبة الحضور: ${branch.attendance_rate || 0}%</span>
                        </div>
                    </div>
                    <div class="leaderboard-stars">
                        <span class="stars">${starText}</span>
                        <span class="attendance-rate">${branch.attendance_rate || 0}%</span>
                    </div>
                `;
                leaderboard.appendChild(row);
            });

            container.appendChild(leaderboard);
        }

        // تسجيل الحضور
        async function checkIn() {
            const employeeCode = document.getElementById('employeeCode').value.trim();
            
            if (!employeeCode) {
                showAlert('يرجى إدخال رقم الموظف', 'error');
                return;
            }
            
            if (isLoading) return;
            isLoading = true;
            
            const btn = document.getElementById('checkInBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span> جاري التسجيل...';
            btn.disabled = true;
            
            try {
                const result = await postApi('check_in', { employee_code: employeeCode });
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    document.getElementById('employeeCode').value = '';
                    fetchStats(); // تحديث الإحصائيات
                    
                    // عرض معلومات الموظف
                    if (result.data) {
                        showCheckInInfo(result.data);
                    }
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في النظام', 'error');
                console.error('خطأ:', error);
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
                isLoading = false;
            }
        }

        // تسجيل الانصراف
        async function checkOut() {
            const employeeCode = document.getElementById('employeeCode').value.trim();
            
            if (!employeeCode) {
                showAlert('يرجى إدخال رقم الموظف', 'error');
                return;
            }
            
            if (isLoading) return;
            isLoading = true;
            
            const btn = document.getElementById('checkOutBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="loading"></span> جاري التسجيل...';
            btn.disabled = true;
            
            try {
                const result = await postApi('check_out', { employee_code: employeeCode });
                
                if (result.success) {
                    showAlert(result.message, 'success');
                    document.getElementById('employeeCode').value = '';
                    fetchStats(); // تحديث الإحصائيات
                    
                    // عرض معلومات الموظف
                    if (result.data) {
                        showCheckOutInfo(result.data);
                    }
                } else {
                    showAlert(result.message, 'error');
                }
            } catch (error) {
                showAlert('حدث خطأ في النظام', 'error');
                console.error('خطأ:', error);
            } finally {
                btn.innerHTML = originalText;
                updateButtonStates(); // إعادة تفعيل الأزرار حسب الإعدادات
                isLoading = false;
            }
        }

        // عرض معلومات الموظف
        function showCheckInInfo(data) {
            const infoDiv = document.getElementById('employeeInfo');
            
            const statusMeta = getStatusMeta(data);
            infoDiv.innerHTML = `
                <h3 style="color: var(--success); margin-bottom: 10px;">✅ تم تسجيل الحضور</h3>
                <p><strong>الموظف:</strong> ${data.employee_name}</p>
                <p><strong>الفرع:</strong> ${data.branch_name}</p>
                <p><strong>وقت الحضور:</strong> ${data.check_in_time}</p>
                <p><strong>دقائق التأخير:</strong> ${data.delay_minutes}</p>
                <p><strong>النقاط:</strong> ${data.deduction_points}</p>
                <p><strong>الحالة:</strong> <span style="color: ${statusMeta.color}; font-weight: 700;">${statusMeta.text}</span></p>
            `;
            updateStatusHero(statusMeta, `وقت الحضور ${data.check_in_time}`);
            
            infoDiv.style.display = 'block';
            
            // إخفاء المعلومات بعد 10 ثواني
            setTimeout(() => {
                infoDiv.style.display = 'none';
            }, 10000);
        }

        function showCheckOutInfo(data) {
            const infoDiv = document.getElementById('employeeInfo');
            infoDiv.innerHTML = `
                <h3 style="color: var(--warning); margin-bottom: 10px;">✅ تم تسجيل الانصراف</h3>
                <p><strong>الموظف:</strong> ${data.employee_name}</p>
                <p><strong>الفرع:</strong> ${data.branch_name}</p>
                <p><strong>وقت الانصراف:</strong> ${data.check_out_time}</p>
                <p><strong>ساعات العمل:</strong> ${data.work_hours}</p>
            `;
            updateStatusHero({ text: 'تم تسجيل الانصراف', color: '#64748b' }, `وقت الانصراف ${data.check_out_time}`);
            
            infoDiv.style.display = 'block';
            
            setTimeout(() => {
                infoDiv.style.display = 'none';
            }, 10000);
        }

        // ربط الأحداث
        document.getElementById('checkInBtn').addEventListener('click', checkIn);
        document.getElementById('checkOutBtn').addEventListener('click', checkOut);

        // السماح بالتسجيل بالضغط على Enter
        document.getElementById('employeeCode').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                if (systemSettings.attendance_mode === 'check_out') {
                    checkOut();
                } else {
                    checkIn();
                }
            }
        });

        // التشغيل الأولي
        document.addEventListener('DOMContentLoaded', function() {
            checkPrivacyAcceptance(); // Check Privacy Policy First
            updateDateTime();
            setInterval(updateDateTime, 1000);
            updateStatusHero(getStatusMeta(null));
            
            fetchSystemStatus();
            fetchStats();
            fetchEmployeeStatus(); // جلب حالة الموظف الحالي
            
            // تحديث الإحصائيات كل 30 ثانية
            setInterval(fetchStats, 30000);
            
            // تحديث حالة النظام كل دقيقة
            setInterval(fetchSystemStatus, 60000);
        });
        
        // جلب حالة الموظف الحالي
        async function fetchEmployeeStatus() {
            const employeeCode = document.getElementById('employeeCode').value;
            if (!employeeCode) return;
            
            try {
                const result = await postApi('get_employee_status', { employee_code: employeeCode });
                if (result.success) {
                    showEmployeeStatus(result.data);
                }
            } catch (error) {
                console.error('خطأ في جلب حالة الموظف:', error);
            }
        }
        
        // عرض معلومات الموظف
        function showEmployeeStatus(data) {
            const infoDiv = document.getElementById('employeeInfo');
            if (!data || !infoDiv) return;
            const statusMeta = getStatusMeta(data);
            
            // V2: Update Branch Name in Header
            const headerBranch = document.getElementById('headerBranchName');
            if (headerBranch && data.branch_name) {
                headerBranch.textContent = data.branch_name;
            }
            
            infoDiv.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; text-align: center;">
                    <div>
                        <div style="font-size: 0.9rem; color: #64748b;">الفرع</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #0f172a;">${data.branch_name}</div>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; color: #64748b;">وقت الحضور</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #10b981;">${data.check_in_time || '--:--'}</div>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; color: #64748b;">الحالة</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: ${statusMeta.color};">${statusMeta.text}</div>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; color: #64748b;">النقاط المخصومة</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: ${data.deduction_points > 0 ? '#ef4444' : '#10b981'};">${data.deduction_points || 0}</div>
                    </div>
                </div>
            `;
            infoDiv.style.display = 'block';
            updateStatusHero(statusMeta, data.check_in_time ? `وقت الحضور ${data.check_in_time}` : 'بانتظار تسجيل الحضور');
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => {
                        reg.update();
                        console.log('Service Worker registered:', reg.scope);
                    })
                    .catch(err => console.log('Service Worker registration failed:', err));
            });
        }

        let deferredPrompt;
        const installBanner = document.getElementById('installBanner');
        const installButton = document.getElementById('installButton');
        const installClose = document.getElementById('installClose');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (installBanner) {
                installBanner.style.display = 'flex';
            }
        });

        if (installButton) {
            installButton.addEventListener('click', async () => {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                await deferredPrompt.userChoice;
                deferredPrompt = null;
                if (installBanner) {
                    installBanner.style.display = 'none';
                }
            });
        }

        if (installClose) {
            installClose.addEventListener('click', () => {
                if (installBanner) {
                    installBanner.style.display = 'none';
                }
            });
        }

        window.addEventListener('appinstalled', () => {
            deferredPrompt = null;
            if (installBanner) {
                installBanner.style.display = 'none';
            }
        });

        const allowLogout = <?php echo $allowLogout; ?>;
        if (allowLogout === 0) {
            history.pushState(null, '', window.location.href);
            window.addEventListener('popstate', () => {
                history.pushState(null, '', window.location.href);
            });
            document.addEventListener('click', (event) => {
                const link = event.target.closest('a');
                if (link && link.getAttribute('href') && (link.getAttribute('href').includes('logout.php') || link.getAttribute('href').includes('index.php'))) {
                    event.preventDefault();
                    showAlert('⚠️ تم تعطيل الخروج من قبل الإدارة', 'error');
                }
            });
        }
    </script>
</body>
</html>