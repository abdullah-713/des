/**
 * نظام صرح انضباط - PWA Core Module
 * Professional PWA functionality and install handling
 */

class SarhPWA {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.isOnline = navigator.onLine;
        this.swRegistration = null;
        
        this.init();
    }
    
    async init() {
        // Check if running as installed PWA
        this.isInstalled = window.matchMedia('(display-mode: standalone)').matches ||
                          window.navigator.standalone === true;
        
        // Register service worker
        await this.registerServiceWorker();
        
        // Setup install prompt
        this.setupInstallPrompt();
        
        // Setup online/offline handling
        this.setupNetworkHandling();
        
        // Setup theme handling
        this.setupThemeHandling();
        
        // Setup haptic feedback
        this.setupHapticFeedback();
        
        // Log PWA status
        console.log('📱 PWA Status:', {
            installed: this.isInstalled,
            online: this.isOnline,
            standalone: window.matchMedia('(display-mode: standalone)').matches
        });
    }
    
    async registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.log('❌ Service Workers not supported');
            return;
        }
        
        try {
            this.swRegistration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            });
            
            console.log('✅ Service Worker registered:', this.swRegistration.scope);
            
            // Check for updates
            this.swRegistration.addEventListener('updatefound', () => {
                const newWorker = this.swRegistration.installing;
                
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        this.showUpdateNotification();
                    }
                });
            });
            
            // Handle controller change
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                console.log('🔄 Service Worker controller changed');
            });
            
        } catch (error) {
            console.error('❌ Service Worker registration failed:', error);
        }
    }
    
    setupInstallPrompt() {
        // Listen for install prompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            
            console.log('📲 Install prompt available');
            this.showInstallBanner();
        });
        
        // Listen for successful install
        window.addEventListener('appinstalled', () => {
            console.log('✅ App installed successfully');
            this.deferredPrompt = null;
            this.isInstalled = true;
            this.hideInstallBanner();
            this.showToast('تم تثبيت التطبيق بنجاح! 🎉', 'success');
        });
    }
    
    async promptInstall() {
        if (!this.deferredPrompt) {
            console.log('❌ No install prompt available');
            return false;
        }
        
        try {
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            
            console.log('📲 Install prompt outcome:', outcome);
            
            if (outcome === 'accepted') {
                this.deferredPrompt = null;
                return true;
            }
            
            return false;
        } catch (error) {
            console.error('❌ Install prompt error:', error);
            return false;
        }
    }
    
    showInstallBanner() {
        const banner = document.getElementById('installBanner');
        if (banner && !this.isInstalled) {
            banner.style.display = 'flex';
            banner.classList.add('show');
        }
    }
    
    hideInstallBanner() {
        const banner = document.getElementById('installBanner');
        if (banner) {
            banner.style.display = 'none';
            banner.classList.remove('show');
        }
    }
    
    setupNetworkHandling() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            console.log('🌐 Back online');
            this.showToast('تم استعادة الاتصال بالإنترنت', 'success');
            document.body.classList.remove('offline');
            
            // Trigger sync if supported
            if (this.swRegistration && 'sync' in this.swRegistration) {
                this.swRegistration.sync.register('sync-attendance')
                    .catch(err => console.log('Sync registration failed:', err));
            }
        });
        
        window.addEventListener('offline', () => {
            this.isOnline = false;
            console.log('📵 Offline');
            this.showToast('لا يوجد اتصال بالإنترنت - الوضع غير متصل', 'warning');
            document.body.classList.add('offline');
        });
        
        // Set initial state
        if (!this.isOnline) {
            document.body.classList.add('offline');
        }
    }
    
    setupThemeHandling() {
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme) {
            document.body.dataset.theme = savedTheme;
        } else if (prefersDark) {
            document.body.dataset.theme = 'dark';
        }
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                document.body.dataset.theme = e.matches ? 'dark' : 'light';
            }
        });
    }
    
    toggleTheme() {
        const currentTheme = document.body.dataset.theme;
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.body.dataset.theme = newTheme;
        localStorage.setItem('theme', newTheme);
        
        // Update theme toggle button if exists
        const toggleBtn = document.getElementById('themeToggle') || document.getElementById('empThemeToggle');
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('.material-icons, .material-icons-round');
            if (icon) {
                icon.textContent = newTheme === 'dark' ? 'light_mode' : 'dark_mode';
            }
        }
        
        // Haptic feedback
        this.vibrate(10);
    }
    
    setupHapticFeedback() {
        // Add haptic feedback to buttons
        document.addEventListener('click', (e) => {
            const target = e.target.closest('button, .btn, .mobile-nav-item');
            if (target) {
                this.vibrate(10);
            }
        });
    }
    
    vibrate(duration = 10) {
        if ('vibrate' in navigator) {
            navigator.vibrate(duration);
        }
    }
    
    showToast(message, type = 'info', duration = 3000) {
        // Remove existing toast
        const existingToast = document.querySelector('.pwa-toast');
        if (existingToast) {
            existingToast.remove();
        }
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `pwa-toast pwa-toast-${type}`;
        toast.innerHTML = `
            <span class="pwa-toast-icon">${this.getToastIcon(type)}</span>
            <span class="pwa-toast-message">${message}</span>
        `;
        
        // Add styles if not already present
        if (!document.querySelector('#pwa-toast-styles')) {
            const styles = document.createElement('style');
            styles.id = 'pwa-toast-styles';
            styles.textContent = `
                .pwa-toast {
                    position: fixed;
                    bottom: calc(100px + env(safe-area-inset-bottom, 0px));
                    left: 50%;
                    transform: translateX(-50%) translateY(100px);
                    background: var(--bg-card, #fff);
                    color: var(--text-primary, #0f172a);
                    padding: 12px 24px;
                    border-radius: 12px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    font-weight: 600;
                    font-size: 14px;
                    z-index: 10000;
                    animation: toast-slide-up 0.3s ease forwards;
                    max-width: 90vw;
                    border: 1px solid var(--border-light, #e2e8f0);
                }
                
                .pwa-toast-success { border-left: 4px solid #10b981; }
                .pwa-toast-error { border-left: 4px solid #ef4444; }
                .pwa-toast-warning { border-left: 4px solid #f59e0b; }
                .pwa-toast-info { border-left: 4px solid #3b82f6; }
                
                .pwa-toast-icon { font-size: 20px; }
                
                @keyframes toast-slide-up {
                    to { transform: translateX(-50%) translateY(0); }
                }
                
                @keyframes toast-slide-down {
                    to { transform: translateX(-50%) translateY(100px); opacity: 0; }
                }
                
                [data-theme="dark"] .pwa-toast {
                    background: var(--bg-card, #1e293b);
                    color: var(--text-primary, #f8fafc);
                }
            `;
            document.head.appendChild(styles);
        }
        
        document.body.appendChild(toast);
        
        // Remove after duration
        setTimeout(() => {
            toast.style.animation = 'toast-slide-down 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    getToastIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        return icons[type] || icons.info;
    }
    
    showUpdateNotification() {
        const update = confirm('يتوفر تحديث جديد للتطبيق. هل تريد التحديث الآن؟');
        if (update) {
            this.swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
            window.location.reload();
        }
    }
    
    async clearCache() {
        if (this.swRegistration) {
            const messageChannel = new MessageChannel();
            
            return new Promise((resolve) => {
                messageChannel.port1.onmessage = (event) => {
                    resolve(event.data.success);
                };
                
                navigator.serviceWorker.controller.postMessage(
                    { type: 'CLEAR_CACHE' },
                    [messageChannel.port2]
                );
            });
        }
        return false;
    }
    
    // Share API
    async share(data) {
        if (navigator.share) {
            try {
                await navigator.share(data);
                return true;
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Share failed:', err);
                }
            }
        }
        return false;
    }
    
    // Clipboard API
    async copyToClipboard(text) {
        if (navigator.clipboard) {
            try {
                await navigator.clipboard.writeText(text);
                this.showToast('تم النسخ إلى الحافظة', 'success');
                return true;
            } catch (err) {
                console.error('Copy failed:', err);
            }
        }
        return false;
    }
    
    // Fullscreen API
    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log('Fullscreen error:', err);
            });
        } else {
            document.exitFullscreen();
        }
    }
    
    // Screen Wake Lock
    async requestWakeLock() {
        if ('wakeLock' in navigator) {
            try {
                const wakeLock = await navigator.wakeLock.request('screen');
                console.log('🔆 Wake lock acquired');
                return wakeLock;
            } catch (err) {
                console.log('Wake lock error:', err);
            }
        }
        return null;
    }
}

// Initialize PWA
const sarhPWA = new SarhPWA();

// Export for global access
window.sarhPWA = sarhPWA;

// Quick access functions
window.installApp = () => sarhPWA.promptInstall();
window.toggleTheme = () => sarhPWA.toggleTheme();
window.showToast = (msg, type, dur) => sarhPWA.showToast(msg, type, dur);
