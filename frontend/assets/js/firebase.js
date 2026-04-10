/**
 * Production Firebase FCM Handler
 * مرتبط مع user_devices + anonymous_token
 */
(function () {
    'use strict';

    // ==============================
    // CONFIG
    // ==============================
    const FIREBASE_CONFIG = {
        apiKey:            window.APP_CONFIG?.FCM_API_KEY || '',
        authDomain:        window.APP_CONFIG?.FCM_AUTH_DOMAIN || '',
        projectId:         window.APP_CONFIG?.FCM_PROJECT_ID || '',
        messagingSenderId: window.APP_CONFIG?.FCM_MESSAGING_SENDER_ID || '',
        appId:             window.APP_CONFIG?.FCM_APP_ID || '',
    };

    const VAPID_KEY   = window.APP_CONFIG?.FCM_VAPID_KEY || '';
    const API_DEVICES = (window.APP_CONFIG?.API_BASE || '/api') + '/user_devices';

    const STORAGE = {
        FCM_TOKEN: 'qz_fcm_token',
        TIME:      'qz_fcm_time',
        ANON:      'qz_anon_token'
    };

    const DAY_MS = 86400000;

    // ==============================
    // 🔑 Generate/Get anonymous_token
    // ==============================
    function getAnonToken() {
        let token = localStorage.getItem(STORAGE.ANON);
        if (!token) {
            token = crypto.randomUUID();
            localStorage.setItem(STORAGE.ANON, token);
        }
        return token;
    }

    // ==============================
    // INIT
    // ==============================
    async function init() {
        if (!('serviceWorker' in navigator) || !('Notification' in window)) return;
        if (Notification.permission === 'denied') return;
        if (!FIREBASE_CONFIG.projectId || !VAPID_KEY) return;
        if (typeof firebase === 'undefined') return;

        try {
            if (!firebase.apps.length) {
                firebase.initializeApp(FIREBASE_CONFIG);
            }

            const messaging = firebase.messaging();

            const sw = await navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' });

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;

            const token = await messaging.getToken({
                vapidKey: VAPID_KEY,
                serviceWorkerRegistration: sw
            });

            if (!token) return;

            const lastToken = localStorage.getItem(STORAGE.FCM_TOKEN);
            const lastTime  = parseInt(localStorage.getItem(STORAGE.TIME), 10) || 0;

            // 🔥 إرسال فقط عند التغيير أو بعد 24 ساعة
            if (lastToken !== token || (Date.now() - lastTime) > DAY_MS) {
                await registerDevice(token);
                localStorage.setItem(STORAGE.FCM_TOKEN, token);
                localStorage.setItem(STORAGE.TIME, Date.now());
            }

            // استقبال الرسائل
            messaging.onMessage(handleForegroundMessage);

        } catch (err) {
            console.warn('[FCM]', err);
        }
    }

    // ==============================
    // 📡 Register Device (IMPORTANT)
    // ==============================
    async function registerDevice(fcmToken) {
        try {
            const body = {
                anonymous_token: getAnonToken(), // 🔥 أهم إضافة
                fcm_token: fcmToken,
                device_type: detectDeviceType(),
                device_name: parseDeviceName()
            };

            const res = await fetch(API_DEVICES, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(body)
            });

            if (!res.ok) {
                console.warn('[FCM] register failed');
            }

        } catch (err) {
            console.warn('[FCM]', err);
        }
    }

    // ==============================
    // 🔔 Foreground Notifications
    // ==============================
    function handleForegroundMessage(payload) {
        const title = payload?.notification?.title || '';
        const body  = payload?.notification?.body  || '';
        const icon  = payload?.notification?.icon  || '/admin/assets/img/default-image.png';

        if (Notification.permission === 'granted' && title) {
            new Notification(title, { body, icon });
        }

        window.dispatchEvent(new CustomEvent('fcm:message', { detail: payload }));
    }

    // ==============================
    // 📱 Device Detection
    // ==============================
    function detectDeviceType() {
        const ua = navigator.userAgent.toLowerCase();
        if (/android/.test(ua)) return 'android';
        if (/iphone|ipad|ipod/.test(ua)) return 'ios';
        if (/mobile|tablet/.test(ua)) return 'other';
        return 'web';
    }

    function parseDeviceName() {
        const ua = navigator.userAgent;

        let browser = 'Browser';
        let os = 'Unknown';

        if (/Chrome/.test(ua) && !/Edg/.test(ua)) browser = 'Chrome';
        else if (/Edg/.test(ua)) browser = 'Edge';
        else if (/Firefox/.test(ua)) browser = 'Firefox';
        else if (/Safari/.test(ua) && !/Chrome/.test(ua)) browser = 'Safari';

        if (/Windows/.test(ua)) os = 'Windows';
        else if (/Mac/.test(ua)) os = 'macOS';
        else if (/Android/.test(ua)) os = 'Android';
        else if (/iPhone|iPad/.test(ua)) os = 'iOS';

        return browser + ' on ' + os;
    }

    // ==============================
    // 🚫 Logout Device
    // ==============================
    window.fcmDeregister = async function () {
        const token = localStorage.getItem(STORAGE.FCM_TOKEN);
        const anon  = localStorage.getItem(STORAGE.ANON);

        if (!token && !anon) return;

        try {
            await fetch(API_DEVICES, {
                method: 'DELETE',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    fcm_token: token,
                    anonymous_token: anon
                })
            });

            localStorage.removeItem(STORAGE.FCM_TOKEN);
            localStorage.removeItem(STORAGE.TIME);

        } catch {}
    };

    // ==============================
    // START
    // ==============================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(init, 1500));
    } else {
        setTimeout(init, 1500);
    }

})();