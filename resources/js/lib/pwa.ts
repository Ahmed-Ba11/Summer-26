/** تسجيل خدمة العامل — تخزين الأصول والخطوط وشاشة عدم الاتصال. */
export function registerServiceWorker(): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js');
    });
}
