/**
 * PWA bootstrap for Taman Teras Cafe.
 *
 * Responsibilities:
 *   - register the service worker
 *   - expose an install prompt for the owner screens
 *   - subscribe/unsubscribe this device for web push
 *   - keep checkout disabled while offline (payments need the server)
 *
 * Exposes a small global API used by the Blade views:
 *   window.PWA.registerInstallPrompt(el)
 *   window.PWA.subscribePush()
 *   window.PWA.unsubscribePush()
 *   window.PWA.sendTestPush()
 *   window.PWA.onConnectivityChange(cb)
 */
(function () {
    const state = {
        registration: null,
        deferredPrompt: null,
        vapidKey: null,
    };

    const base64ToUint8Array = (base64String) => {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        const output = new Uint8Array(raw.length);

        for (let i = 0; i < raw.length; i++) {
            output[i] = raw.charCodeAt(i);
        }

        return output;
    };

    const post = async (url, body) => {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body || {}),
        });

        return res.json();
    };

    /* ---------------------------------------------------------------- */
    /* Service worker                                                    */
    /* ---------------------------------------------------------------- */

    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return null;
        }

        try {
            state.registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
            return state.registration;
        } catch (error) {
            console.warn('Service worker registration failed:', error);
            return null;
        }
    }

    /* ---------------------------------------------------------------- */
    /* Install prompt                                                    */
    /* ---------------------------------------------------------------- */

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        state.deferredPrompt = event;
        document.dispatchEvent(new CustomEvent('pwa:installable'));
    });

    window.addEventListener('appinstalled', () => {
        state.deferredPrompt = null;
        document.dispatchEvent(new CustomEvent('pwa:installed'));
    });

    async function promptInstall() {
        if (!state.deferredPrompt) {
            return false;
        }

        state.deferredPrompt.prompt();
        const choice = await state.deferredPrompt.userChoice;
        state.deferredPrompt = null;

        return choice.outcome === 'accepted';
    }

    /**
     * Shows a small install bar on the given element when the browser allows it.
     */
    function registerInstallPrompt(element) {
        if (!element) {
            return;
        }

        const render = () => {
            element.hidden = !state.deferredPrompt;
        };

        render();
        document.addEventListener('pwa:installable', render);
        document.addEventListener('pwa:installed', render);

        element.addEventListener('click', async () => {
            if (await promptInstall()) {
                element.hidden = true;
            }
        });
    }

    const isStandalone = () =>
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true;

    /* ---------------------------------------------------------------- */
    /* Push subscription                                                 */
    /* ---------------------------------------------------------------- */

    async function currentSubscription() {
        if (!state.registration) {
            return null;
        }

        return state.registration.pushManager.getSubscription();
    }

    function unsupportedReason() {
        if (!('serviceWorker' in navigator)) {
            return 'Browser Anda tidak mendukung service worker.';
        }

        if (!('PushManager' in window)) {
            return 'Browser Anda tidak mendukung web push. Chrome/Edge di Android atau Safari 16.4+ (iOS) diperlukan.';
        }

        if (!window.isSecureContext) {
            return 'Notifikasi hanya bisa diaktifkan lewat HTTPS atau localhost.';
        }

        return null;
    }

    async function subscribePush(vapidKey) {
        const unsupported = unsupportedReason();

        if (unsupported) {
            return { success: false, message: unsupported };
        }

        if (!vapidKey) {
            return { success: false, message: 'VAPID key belum dikonfigurasi di server.' };
        }

        const registration = state.registration || (await registerServiceWorker());

        if (!registration) {
            return { success: false, message: 'Service worker gagal didaftarkan.' };
        }

        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            return { success: false, message: 'Izin notifikasi ditolak di pengaturan browser.' };
        }

        const subscription =
            (await currentSubscription()) ||
            (await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64ToUint8Array(vapidKey),
            }));

        const response = await post('/api/owner/push/subscribe', subscription.toJSON());

        if (!response.success) {
            return { success: false, message: response.message };
        }

        return { success: true, message: response.message };
    }

    async function unsubscribePush() {
        const subscription = await currentSubscription();

        if (subscription) {
            await post('/api/owner/push/unsubscribe', subscription.toJSON());
            await subscription.unsubscribe();
        }

        return { success: true, message: 'Notifikasi push dimatikan untuk perangkat ini' };
    }

    async function sendTestPush() {
        const response = await post('/api/owner/push/test', {});

        return response;
    }

    /* ---------------------------------------------------------------- */
    /* Connectivity                                                      */
    /* ---------------------------------------------------------------- */

    function onConnectivityChange(callback) {
        const emit = () => callback(navigator.onLine);

        emit();
        window.addEventListener('online', emit);
        window.addEventListener('offline', emit);
    }

    document.addEventListener('DOMContentLoaded', () => {
        registerServiceWorker();
    });

    window.PWA = {
        state,
        promptInstall,
        registerInstallPrompt,
        subscribePush,
        unsubscribePush,
        sendTestPush,
        currentSubscription,
        isStandalone,
        unsupportedReason,
        onConnectivityChange,
    };
})();
