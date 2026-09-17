/**
 * عامل خدمة (Service Worker) — كشف المتابعة (S12).
 *
 * نطاق محدود وصريح: تخزين غلاف الواجهة (أصول Vite المبنية + الأيقونات)
 * للعمل دون اتصال وللتثبيت كتطبيق، وتخزين آخر نسخة ناجحة من صفحات القراءة
 * (GET) لعرضها عند انقطاع الشبكة بدل صفحة خطأ فارغة. لا يلمس أبدًا أي طلب
 * غير GET — الكتابة (تسجيل حضور/سجلّ/تعديل/حذف) تصل الخادم مباشرة أو تفشل
 * بوضوح؛ طابور "دون اتصال" الفعلي لتلك الطلبات مسؤولية resources/js/app.js
 * (flushOfflineQueue)، لا هذا الملف.
 */

const CACHE_NAME = 'keshf-cache-v1';
const STATIC_ASSETS = [
    '/manifest.json',
    '/favicon.ico',
    '/images/icon-192.png',
    '/images/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .catch(() => { /* تجاهُل — تحسين إضافي لا شرط تثبيت */ })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // أصول Vite المبنية (public/build/**) أسماؤها تتضمّن hash المحتوى نفسه
    // — محتواها لا يتغيّر أبدًا لنفس الاسم، فآمن تمامًا تفضيل الذاكرة المؤقّتة
    // على الشبكة (cache-first) بدل التحقّق من الخادم في كل مرة.
    if (url.pathname.startsWith('/build/') || STATIC_ASSETS.includes(url.pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                return response;
            }))
        );
        return;
    }

    // صفحات القراءة (اللوحة، الحضور، التقارير، سجلّ الطالب...): الشبكة أولًا
    // لأنها بيانات حيّة قد تكون تغيّرت، لكن آخر نسخة ناجحة تُخزَّن مؤقتًا
    // لعرضها عند الانقطاع — تصفّح بلا اتصال يعرض آخر ما شاهده المعلّم، لا
    // صفحة خطأ فارغة.
    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                }
                return response;
            })
            .catch(() => caches.match(request))
    );
});
