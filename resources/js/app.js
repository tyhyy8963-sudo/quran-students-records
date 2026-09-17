/**
 * الطبقة الأمامية الموحّدة (S3 · #21 وS5 · #27).
 *
 * قبل هذا كان كل ملف Blade يكتب fetch() الخاص به، برؤوس مختلفة قليلًا،
 * وبلا فحص res.ok — فأي خطأ 4xx/5xx كان يُعامَل كنجاح ما دام رد الخادم
 * جسمًا صالح JSON. هذا الملف هو المصدر الوحيد لثلاثة أشياء تستعملها كل
 * صفحة: apiFetch (طبقة نداء موحّدة تفهم عقد {data,message} و{message,errors}
 * وتتعامل مع 419)، toast (إشعار عابر)، وconfirmDialog (تأكيد قبل أي إجراء
 * هدّام).
 */

import './bootstrap';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/* ---------------------------------------------------------------------
 * 1) الإشعار العابر (Toast)
 * ------------------------------------------------------------------- */
function ensureToastHost() {
    let host = document.getElementById('toast');
    if (!host) {
        host = document.createElement('div');
        host.id = 'toast';
        document.body.appendChild(host);
    }
    return host;
}

/**
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {{action?: {label: string, onClick: () => void}, duration?: number}} [opts]
 */
export function toast(message, type = 'info', opts = {}) {
    const host = ensureToastHost();
    const item = document.createElement('div');
    item.className = `toast-item toast-${type}`;
    item.setAttribute('role', 'status');

    const text = document.createElement('span');
    text.textContent = message;
    item.appendChild(text);

    if (opts.action) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'toast-action';
        btn.textContent = opts.action.label;
        btn.addEventListener('click', () => {
            opts.action.onClick();
            remove();
        });
        item.appendChild(btn);
    }

    host.appendChild(item);

    const duration = opts.duration ?? (opts.action ? 6000 : 3200);
    const timer = setTimeout(remove, duration);

    function remove() {
        clearTimeout(timer);
        item.classList.add('toast-leaving');
        item.addEventListener('animationend', () => item.remove(), { once: true });
    }
}

/* ---------------------------------------------------------------------
 * 2) شريط التأكيد
 * ------------------------------------------------------------------- */
let confirmResolve = null;

function confirmElements() {
    return {
        overlay: document.getElementById('confirmOverlay'),
        message: document.getElementById('confirmMessage'),
        yes: document.getElementById('confirmYesBtn'),
        no: document.getElementById('confirmNoBtn'),
    };
}

/**
 * يعيد Promise<boolean> — true إن ضغط "نعم".
 * @param {string} message
 */
export function confirmDialog(message) {
    const { overlay, message: messageEl, yes, no } = confirmElements();
    if (!overlay) {
        // شريط التأكيد غير موجود في هذه الصفحة — نعود إلى confirm الأصلي كحل بديل.
        return Promise.resolve(window.confirm(message));
    }

    messageEl.textContent = message;
    overlay.classList.add('is-visible');

    return new Promise((resolve) => {
        confirmResolve = resolve;

        const finish = (result) => {
            overlay.classList.remove('is-visible');
            yes.removeEventListener('click', onYes);
            no.removeEventListener('click', onNo);
            confirmResolve = null;
            resolve(result);
        };

        const onYes = () => finish(true);
        const onNo = () => finish(false);

        yes.addEventListener('click', onYes);
        no.addEventListener('click', onNo);
    });
}

/* ---------------------------------------------------------------------
 * 3) طبقة نداء API موحّدة
 *
 * العقد المتوقّع من الخادم (S5 · B-06):
 *  - نجاح إنشاء/تعديل/استرجاع: {data, message}
 *  - نجاح حذف:                 {data: {id}, message}
 *  - خطأ تحقّق (422):           {message, errors: {field: [..]}}
 *  - خطأ عام:                   {message}
 *  - جلسة منتهية (419):         صفحة/جسم من Laravel، لا شكل JSON مضمون
 * ------------------------------------------------------------------- */
export class ApiError extends Error {
    constructor(message, status, errors = null) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

/**
 * @param {string} url
 * @param {{method?: string, body?: any}} [options]
 */
export async function apiFetch(url, options = {}) {
    const { method = 'GET', body } = options;

    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    let payload;
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' });
    } catch (networkError) {
        throw new ApiError('تعذّر الاتصال بالخادم. تحقّق من اتصالك بالإنترنت.', 0);
    }

    // 419: انتهت صلاحية الجلسة/رمز CSRF. لا حل سوى إعادة تحميل الصفحة
    // لتجديد الرمز — لا معنى لعرض النتيجة الفاشلة كخطأ عادي هنا.
    if (response.status === 419) {
        toast('انتهت صلاحية الجلسة، يتم تحديث الصفحة…', 'warning', { duration: 2500 });
        setTimeout(() => window.location.reload(), 1500);
        throw new ApiError('انتهت صلاحية الجلسة.', 419);
    }

    let json = null;
    try {
        json = await response.json();
    } catch (parseError) {
        // ردّ بلا جسم JSON (نادر لهذه الواجهات، لكن نتعامل معه بأمان).
    }

    if (!response.ok) {
        const message = json?.message || 'حدث خطأ غير متوقع. حاول مرة أخرى.';
        throw new ApiError(message, response.status, json?.errors ?? null);
    }

    return json ?? {};
}

/**
 * يُشغِّل حالة تحميل مرئية على زر أثناء تنفيذ وعد.
 * @param {HTMLButtonElement} button
 * @param {() => Promise<any>} action
 */
export async function withButtonLoading(button, action) {
    if (!button) return action();
    button.classList.add('is-loading');
    button.disabled = true;
    try {
        return await action();
    } finally {
        button.classList.remove('is-loading');
        button.disabled = false;
    }
}

/**
 * يعرض أخطاء تحقّق {field: [messages]} أسفل حقول <input data-field="...">
 * ضمن نموذج معيّن، ويعيد أول رسالة كنص عام عند عدم وجود عنصر مطابق.
 */
export function applyFieldErrors(form, errors = {}) {
    form.querySelectorAll('.field-error[data-for]').forEach((el) => {
        el.textContent = '';
    });
    form.querySelectorAll('.input.has-error').forEach((el) => el.classList.remove('has-error'));

    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.querySelector(`[name="${field}"]`);
        input?.classList.add('has-error');
        const slot = form.querySelector(`.field-error[data-for="${field}"]`);
        if (slot) slot.textContent = messages[0];
    });
}

/* ---------------------------------------------------------------------
 * 4) الوضع الليلي (S12)
 *
 * السكربت المتزامن في <head> يضبط data-theme قبل الرسم (لا وميض)؛ هذا
 * الجزء فقط يربط الزر ويحفظ الاختيار. بلا اختيار محفوظ، نتبع تفضيل النظام
 * (prefers-color-scheme) دون كتابته في localStorage — فتغيير وضع النظام
 * لاحقًا يبقى ينعكس تلقائيًا ما لم يضغط المستخدم الزر صراحةً.
 * ------------------------------------------------------------------- */
function currentTheme() {
    return document.documentElement.getAttribute('data-theme')
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('keshf-theme', theme); } catch (e) { /* تخزين محلي غير متاح — لا يمنع تبديل الوضع لهذه الجلسة */ }
    const btn = document.getElementById('themeToggle');
    if (btn) btn.textContent = theme === 'dark' ? '☀️' : '🌙';
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('themeToggle');
    if (!btn) return;
    btn.textContent = currentTheme() === 'dark' ? '☀️' : '🌙';
    btn.addEventListener('click', () => applyTheme(currentTheme() === 'dark' ? 'light' : 'dark'));
});

/* ---------------------------------------------------------------------
 * 5) طابور دون اتصال (S12)
 *
 * تبسيط معلَن ومحدود النطاق عمدًا: يغطّي فقط تسجيل الحضور والسجلّ الزمني
 * (نفس ما ذكرته خطة السبرنتات حرفيًا: "queue للتحضير/السجلّ")، لا كل نداء
 * كتابة في التطبيق — تأجيل تعديل/حذف طالب مثلًا يفتح احتمال تعارض ترتيب
 * لا يستحق التعقيد هنا. الطابور نفسه في localStorage: خاص بهذا المتصفّح/
 * الجهاز فقط، لا يتزامن بين أجهزة المعلّم، ويُفقَد إن مسح بيانات الموقع.
 * ------------------------------------------------------------------- */
const OFFLINE_QUEUE_KEY = 'keshf-offline-queue';

function readQueue() {
    try {
        return JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
    } catch (e) {
        return [];
    }
}

function writeQueue(queue) {
    try { localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue)); } catch (e) { /* تجاهُل — لا مساحة تخزين متاحة */ }
}

export function queueLength() {
    return readQueue().length;
}

/**
 * يحفظ نداءً فشل بسبب انقطاع الاتصال ليُعاد إرساله لاحقًا.
 */
function enqueue(url, method, body, label) {
    const queue = readQueue();
    queue.push({ url, method, body, label, queuedAt: Date.now() });
    writeQueue(queue);
    toast(`لا يوجد اتصال — سيُحفَظ "${label}" تلقائيًا عند عودته.`, 'warning', { duration: 5000 });
}

/**
 * يعيد محاولة كل عناصر الطابور بالترتيب؛ يوقف عند أول فشل شبكة (الاتصال
 * ما زال غائبًا) ويُبقي الباقي بانتظار محاولة لاحقة، لكنه يُسقط من الطابور
 * أي عنصر يرفضه الخادم برفض حقيقي (422 مثلًا) بدل إعادة محاولته للأبد.
 */
export async function flushOfflineQueue() {
    const queue = readQueue();
    if (queue.length === 0) return;

    let flushedCount = 0;
    let stoppedAt = queue.length; // لم نتوقف مبكرًا يعني نجاح كل العناصر

    for (let i = 0; i < queue.length; i++) {
        try {
            await apiFetch(queue[i].url, { method: queue[i].method, body: queue[i].body });
            flushedCount++;
        } catch (error) {
            if (error.status === 0) {
                // ما زال غير متصل — أبقِ هذا العنصر وكل ما بعده لمحاولة لاحقة.
                stoppedAt = i;
                break;
            }
            // رفض حقيقي من الخادم (تحقّق فاشل مثلًا) — يُسقَط، لا يُعاد لأبد.
        }
    }

    writeQueue(queue.slice(stoppedAt));
    if (flushedCount > 0) {
        toast(`تمت مزامنة ${flushedCount} عنصرًا كان بانتظار الاتصال.`, 'success', { duration: 4500 });
    }
}

/**
 * نداء يُحفَظ في طابور دون اتصال بدل رمي خطأ عند غياب الشبكة تحديدًا
 * (status 0 من apiFetch)، ويُترك كل خطأ آخر (422/419/500) كما هو.
 */
export async function apiFetchQueueable(url, options = {}, label = 'العملية') {
    try {
        return await apiFetch(url, options);
    } catch (error) {
        if (error.status === 0) {
            enqueue(url, options.method ?? 'GET', options.body, label);
            return { queued: true };
        }
        throw error;
    }
}

window.addEventListener('online', () => { flushOfflineQueue(); });
document.addEventListener('DOMContentLoaded', () => {
    if (navigator.onLine) flushOfflineQueue();
});

/* ---------------------------------------------------------------------
 * 6) تطبيق ويب تقدّمي (PWA) (S12)
 *
 * تسجيل عامل الخدمة (service worker) — يخزّن غلاف الواجهة (CSS/JS) للعمل
 * دون اتصال وللتثبيت على الشاشة الرئيسية. فشل التسجيل (متصفّح لا يدعمه،
 * أو الصفحة مفتوحة عبر ملف محلي) لا يوقف بقية التطبيق — تحسين إضافي لا
 * شرط تشغيل.
 * ------------------------------------------------------------------- */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => { /* بيئة لا تدعم SW — تجاهُل صامت */ });
    });
}

window.KeshfApp = {
    toast, confirmDialog, apiFetch, apiFetchQueueable, ApiError, withButtonLoading, applyFieldErrors,
    flushOfflineQueue, queueLength,
};
