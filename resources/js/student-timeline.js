import {
    Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip,
    BarController, BarElement,
} from 'chart.js';

Chart.register(
    LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip,
    BarController, BarElement,
);

/**
 * منحنيات التقدّم (S8، وسِّعت في S16) — مُجمَّع كملف Vite مستقل عن app.js،
 * فلا تحمّل صفحات الدخول والتسجيل مكتبة رسم بياني لن تستخدمها أبدًا.
 *
 * منذ S16 الملف يبني عدّة منحنيات لا منحنى واحدًا (حفظ، وواحد لكل متن
 * يتتبّعه الطالب)، وكلّها تشترك تبديل تجميع واحدًا (يومي/أسبوعي/شهري) عبر
 * buildChart()/aggregate(). منحنى المراجعة مستثنى من هذا التشارك منذ تحويله
 * إلى أعمدة شهرية للمقارنة (راجع buildReviewMonthlyChart أسفله) — شهري
 * بطبيعته أصلًا، فلا معنى لتبديل يومي/أسبوعي عليه.
 *
 * S19.5 — ألوان كل منحنى كانت قيمًا سداسية عشرية ثابتة داخل هذا الملف (مثل
 * #1d3557 لعمود المراجعة) لا صلة لها بنظام التصميم في app.css، وبلا أي رابط
 * بالوضع الليلي إطلاقًا — التبديل بين الوضعين كان يُعيد تلوين كل مكوّنات
 * الواجهة فورًا إلا هذه المنحنيات (Chart.js يرسم على Canvas مرّة واحدة ولا
 * يتأثّر بتغيّر متغيّرات CSS من تلقاء نفسه). الحل هنا بجزأين:
 *   1) الألوان تُقرأ من متغيّرات CSS نفسها عند كل بناء (cssVar أدناه)،
 *      ومُحاذاة للمعنى الدلالي المستخدَم أصلًا في باقي الواجهة: المراجعة
 *      "أزرق معلوماتي" مثل .type-chip.type-مراجعة تمامًا (قسم 19 في
 *      app.css)، الحفظ "أخضر أساسي" (نفس --color-primary المستخدَم في
 *      .progress-ring-value)، والمتون "بنّي مائل للتمييز" مثل .btn-accent.
 *   2) عند إطلاق app.js لحدث keshf:theme-change (S19.5) تُهدَم كل المنحنيات
 *      وتُعاد بناؤها بالألوان الجديدة فورًا، بنفس تبويب التجميع الحالي.
 *
 * فُحصت الألوان الثلاثة عبر scripts/validate_palette.js (مهارة dataviz) في
 * الوضعين الفاتح والداكن معًا: فحوص الفصل اللوني بين الفئات (CVD/chroma
 * floor) لا تنطبق هنا لأن كل لون يظهر منحنى وحيدًا في بطاقته الخاصة ولا
 * يُقارَن بجانب الآخرَين في نفس الرسم كسلسلة ألوان فئوية — الفحص الوحيد ذو
 * الصلة (التباين أمام خلفية البطاقة) نجح للألوان الثلاثة في كلا الوضعين.
 */

/**
 * يقرأ قيمة متغيّر CSS حيّة من :root — لا نسخة مخبَّأة وقت تحميل الصفحة،
 * فتُقرأ القيمة الصحيحة سواء كان الوضع فاتحًا أو داكنًا في لحظة الاستدعاء.
 */
function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

/** يحوّل #rrggbb إلى rgba(...) بشفافية معطاة — لخلفيات تعبئة المنحنى. */
function hexToRgba(hex, alpha) {
    const clean = hex.replace('#', '');
    const bigint = parseInt(clean.length === 3
        ? clean.split('').map((c) => c + c).join('')
        : clean, 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * لوحة الألوان الحيّة لكل منحنيات هذه الصفحة — نفس التوكِنات الدلالية
 * المستخدَمة في باقي الواجهة (راجع تعليق الملف أعلاه)، بدل قيم سداسية عشرية
 * منفصلة كانت تنحرف عن نظام التصميم وعن الوضع الليلي معًا.
 */
function chartPalette() {
    return {
        memorization: cssVar('--color-primary-500'),
        review: cssVar('--color-info-500'),
        poem: cssVar('--color-accent-500'),
        grid: cssVar('--color-border'),
        muted: cssVar('--color-text-muted'),
        text: cssVar('--color-text'),
        surface: cssVar('--color-surface'),
    };
}

/**
 * مفتاح بداية الأسبوع (الأحد) لتاريخ معيّن — تجميع أسبوعي يعني "آخر نقطة
 * في كل أسبوع تقويمي يبدأ أحدًا"، لا نافذة 7 أيام متحرّكة.
 */
function weekKey(dateStr) {
    const d = new Date(`${dateStr}T00:00:00`);
    d.setDate(d.getDate() - d.getDay());
    return d.toISOString().slice(0, 10);
}

function monthKey(dateStr) {
    return dateStr.slice(0, 7);
}

/**
 * يُبقي آخر نقطة في كل حاوية زمنية لا مجموعها ولا متوسطها — السلسلة نسبة
 * تراكمية (كم أنجز الطالب حتى هذا التاريخ)، فمجموع نقطتين في نفس الأسبوع
 * لا معنى له، وآخر نقطة هي حالة الطالب الفعلية في نهاية تلك الحاوية.
 *
 * النقاط مرتَّبة زمنيًا أصلًا (timeline() في الخلفية تُعيد تشغيل السجلّات
 * بترتيب logged_at ثم id)، فالإدراج المتتالي في Map يجعل آخر نقطة لكل
 * مفتاح تطغى تلقائيًا على ما قبلها بلا فرز إضافي هنا.
 *
 * @param {{date: string, percent: number}[]} points
 * @param {'daily'|'weekly'|'monthly'} granularity
 */
function aggregate(points, granularity) {
    if (granularity === 'daily' || points.length === 0) {
        return points;
    }

    const keyFn = granularity === 'monthly' ? monthKey : weekKey;
    const buckets = new Map();

    points.forEach((point) => {
        buckets.set(keyFn(point.date), point);
    });

    return Array.from(buckets.values());
}

/**
 * ينشئ منحنى Chart.js واحدًا فوق عنصر canvas معطى، ويُعيد واجهة صغيرة
 * (setGranularity, destroy) تُحدِّث بيانات نفس الرسم دون إعادة بنائه بالكامل،
 * أو تهدمه عند إعادة البناء الكامل بألوان جديدة (تبديل الوضع الليلي).
 *
 * يُعيد null إن كان العنصر غير موجود أو بلا نقاط — حتى يسهل بناء عدّة
 * منحنيات معًا (بعضها قد يكون فارغًا فعليًا لهذا الطالب) بلا تحقّق مكرَّر
 * في كل موضع استدعاء.
 *
 * @param {HTMLCanvasElement|null} canvas
 * @param {{date: string, percent: number}[]} rawPoints
 * @param {{label: string, color: string, bg: string, tooltipSuffix: string}} opts
 */
function buildChart(canvas, rawPoints, opts) {
    if (!canvas || !rawPoints || rawPoints.length === 0) {
        return null;
    }

    const { label, color, bg, tooltipSuffix } = opts;
    const palette = chartPalette();

    // (S25 — بطلب صريح من يحيى: "2026-04-20 صعّبت علي معرفة اليوم والشهر
    // بسرعة") — تُعرض label العربية المقروءة ("20 أبريل 2026") التي أضافتها
    // الخلفية (MemorizationProgress/ReviewProgress/PoemProgress::timeline())
    // على محور السينات والتلميح، بدل date الخام (Y-m-d) الذي يبقى مستعملاً
    // داخليًا فقط في aggregate() أدناه للتجميع الأسبوعي/الشهري. p.date احتياط
    // لبيانات قديمة مخزَّنة بلا label (لن يحدث فعليًا، لكن أسلم من انهيار).
    const chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: rawPoints.map((p) => p.label || p.date),
            datasets: [{
                label,
                data: rawPoints.map((p) => p.percent),
                borderColor: color,
                backgroundColor: bg,
                fill: true,
                tension: 0.25,
                // أطراف بيانات مدوَّرة وخط رفيع (2px) بدل الخط السميك الافتراضي
                // لبيانات نسبة تراكمية بسيطة — توصية dataviz لمنحنى خطّي واحد.
                borderWidth: 2,
                borderCapStyle: 'round',
                borderJoinStyle: 'round',
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: color,
                pointBorderColor: palette.surface,
                pointBorderWidth: 1.5,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.parsed.y}%${tooltipSuffix}`,
                    },
                },
            },
            scales: {
                // نفس منطق مقياس المحور y الأصلي: يبدأ من الصفر دائمًا، وسقفه
                // أوسع من أعلى نقطة بمقدار النصف تقريبًا مقرَّبًا لأقرب عشرة
                // (حد أدنى 10%، أقصى 100%) بدل مقياس تلقائي محض يوهم بالاكتمال
                // عند نسب صغيرة أو مقياس ثابت 0–100 يسحق منحنى طالب مبتدئ.
                y: {
                    beginAtZero: true,
                    suggestedMax: Math.min(100, Math.max(10, Math.ceil(Math.max(...rawPoints.map((p) => p.percent)) * 1.5 / 10) * 10)),
                    title: { display: true, text: `${label} %`, color: palette.muted },
                    ticks: { callback: (value) => `${value}%`, color: palette.muted },
                    // شبكة خافتة (recessive) — تُقرأ كخلفية إرشادية لا كخطوط
                    // منافسة للمنحنى نفسه في الأهمية البصرية.
                    grid: { color: palette.grid },
                },
                x: {
                    title: { display: true, text: 'التاريخ', color: palette.muted },
                    ticks: { color: palette.muted },
                    grid: { color: palette.grid },
                },
            },
        },
    });

    return {
        chart,
        rawPoints,
        setGranularity(granularity) {
            const points = aggregate(rawPoints, granularity);
            // نفس تبديل buildChart أعلاه (S25): label العربية المقروءة لا date الخام.
            this.chart.data.labels = points.map((p) => p.label || p.date);
            this.chart.data.datasets[0].data = points.map((p) => p.percent);
            this.chart.update();
        },
        destroy() {
            this.chart.destroy();
        },
    };
}

/**
 * عمود شهري لعدد الأرباع التي مسّتها أي مراجعة خلال كل شهر (S16، حلّ محلّ
 * منحنى المراجعة التراكمي السابق بطلب صاحب المنظومة بعد ملاحظته أن التراكم
 * لا يعكس طبيعة المراجعة الحقيقية — المراجعة نشاط متكرّر، فمقارنة "كم عمل
 * فعليًا هذا الشهر" شهرًا بشهر أوضح تربويًا من نسبة تراكمية تخلط الكلّي
 * بالشهري). مستقلّ تمامًا عن buildChart()/aggregate() ولا يُضاف إلى مصفوفة
 * controllers أدناه لأنه لا يشارك تبديل التجميع أصلًا.
 *
 * @param {HTMLCanvasElement|null} canvas
 * @param {{month: string, label: string, quarters: number}[]} rawPoints
 */
function buildReviewMonthlyChart(canvas, rawPoints) {
    if (!canvas || !rawPoints || rawPoints.length === 0) {
        return null;
    }

    const palette = chartPalette();
    // أزرق معلوماتي — نفس دلالة .type-chip.type-مراجعة في السجلّ الزمني
    // (قسم 19 من app.css)، بدل اللون الكحلي المنفصل السابق (#1d3557) الذي
    // لا صلة له بهذا الاصطلاح القائم أصلًا في الواجهة.
    const color = palette.review;
    const maxQuarters = Math.max(...rawPoints.map((p) => p.quarters));

    const chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rawPoints.map((p) => p.label),
            datasets: [{
                label: 'أرباع رُوجعت',
                data: rawPoints.map((p) => p.quarters),
                backgroundColor: color,
                borderRadius: 4,
                maxBarThickness: 48,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        // مكافئ الأحزاب (كل 4 أرباع = حزب) معلومة عرضية تفيد
                        // المعلّم بجانب رقم الأرباع الخام وحده.
                        label: (ctx) => {
                            const quarters = ctx.parsed.y;
                            const hizb = (quarters / 4).toFixed(quarters % 4 === 0 ? 0 : 2);

                            return `${quarters} ربعًا (≈ ${hizb} حزب) رُوجعت هذا الشهر`;
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    // سقف أوسع من أعلى عمود بمقدار الربع تقريبًا (حد أدنى 4 —
                    // حزب واحد) بدل مقياس تلقائي محض قد يسحق الفروق بين شهر
                    // نشط وآخر شبه خامل.
                    suggestedMax: Math.max(4, Math.ceil(maxQuarters * 1.25)),
                    ticks: { precision: 0, color: palette.muted },
                    title: { display: true, text: 'عدد الأرباع الممسوسة', color: palette.muted },
                    grid: { color: palette.grid },
                },
                x: {
                    title: { display: true, text: 'الشهر', color: palette.muted },
                    ticks: { color: palette.muted },
                    grid: { display: false },
                },
            },
        },
    });

    return { chart, rawPoints, destroy() { this.chart.destroy(); } };
}

document.addEventListener('DOMContentLoaded', () => {
    let controllers = [];
    let reviewChart = null;
    let currentGranularity = 'daily';

    const memorizationCanvas = document.getElementById('progressChart');
    const reviewCanvas = document.getElementById('reviewMonthlyChart');
    // منحنى تقدّم المراجعة (S23.5) — تراكمي مثل منحنى الحفظ تمامًا، بخلاف
    // عمود reviewCanvas أعلاه (شهري، مستقلّ عن تبديل التجميع). عنصر Canvas
    // مختلف عمدًا (reviewProgressChart لا reviewMonthlyChart) لأن الاثنين
    // يظهران معًا الآن جنبًا لجنب في شبكة "التحليلات والإحصائيات".
    const reviewProgressCanvas = document.getElementById('reviewProgressChart');
    const poemChartData = window.KeshfPoemChartData || {};
    // منحنيات مراجعة المتون (S25 — بطلب صريح من يحيى: "أبغى للمتن منحيين...
    // للحفظ منحنى وللمراجعة منحنى") — نظير poemChartData أعلاه تمامًا، بنوع
    // "مراجعة" بدل "حفظ"؛ قماش كل متن هنا poemReviewChart-{id} لا poemChart-{id}.
    const poemReviewChartData = window.KeshfPoemReviewChartData || {};

    /**
     * يبني كل منحنيات الصفحة من الصفر (أو يعيد بناءها بعد هدم النسخة
     * السابقة عند تبديل الوضع الليلي — راجع تعليق الملف أعلاه). أزرار تبديل
     * التجميع نفسها عناصر DOM عادية لا تُهدَم مع المنحنيات، فحالتها النشطة
     * (is-active) تبقى كما هي عبر إعادة البناء؛ الجديد فقط تطبيق نفس تجميع
     * currentGranularity الحالي على كل منحنى فور بنائه.
     */
    function renderCharts() {
        controllers = [];

        const memorizationChart = buildChart(
            memorizationCanvas,
            window.KeshfChartData,
            { label: 'نسبة الحفظ', color: chartPalette().memorization, bg: hexToRgba(chartPalette().memorization, 0.15), tooltipSuffix: ' من المصحف حتى هذا التاريخ' },
        );
        if (memorizationChart) controllers.push(memorizationChart);

        // منحنى تقدّم المراجعة التراكمي (S23.5) — نفس بناء منحنى الحفظ تمامًا
        // ونفس اللون الدلالي المستعمَل أصلًا لكل ما يخصّ المراجعة في الواجهة
        // (palette.review، أزرق معلوماتي)، فيُضاف لمصفوفة controllers ليشارك
        // تبديل التجميع يومي/أسبوعي/شهري مع منحنى الحفظ.
        const reviewProgressChart = buildChart(
            reviewProgressCanvas,
            window.KeshfReviewProgressData,
            { label: 'نسبة المراجعة', color: chartPalette().review, bg: hexToRgba(chartPalette().review, 0.15), tooltipSuffix: ' من المصحف حتى هذا التاريخ' },
        );
        if (reviewProgressChart) controllers.push(reviewProgressChart);

        // عمود المراجعة الشهري (S16) — مستقلّ عن مصفوفة controllers لأنه لا
        // يشارك تبديل التجميع أعلاه (شهري بطبيعته أصلًا).
        reviewChart = buildReviewMonthlyChart(reviewCanvas, window.KeshfReviewMonthlyData);

        // منحنى حفظ لكل متن يملك نقاطًا فعلية — الخريطة (poem_id ⇐ نقاط) مبنيّة
        // في الخلفية بنفس ترتيب لا معنى له هنا، فقماش كل متن يُقرَن بمعرّفه
        // مباشرة (poemChart-{id}) لا بترتيب ظهوره.
        Object.keys(poemChartData).forEach((poemId) => {
            const poemColor = chartPalette().poem;
            const poemChart = buildChart(
                document.getElementById(`poemChart-${poemId}`),
                poemChartData[poemId],
                { label: 'نسبة الحفظ', color: poemColor, bg: hexToRgba(poemColor, 0.15), tooltipSuffix: ' من المتن حتى هذا التاريخ' },
            );
            if (poemChart) controllers.push(poemChart);
        });

        // منحنى مراجعة لكل متن (S25) — نظير الحفظ أعلاه تمامًا، بلون المراجعة
        // الدلالي نفسه (palette.review، أزرق معلوماتي، نفس منحنى "تقدّم
        // المراجعة" للقرآن) وقماش poemReviewChart-{id}. يُضاف لنفس مصفوفة
        // controllers فيشارك مبدّل التجميع يومي/أسبوعي/شهري المشترك — لا زرّ
        // إضافي مطلوب هنا.
        Object.keys(poemReviewChartData).forEach((poemId) => {
            const reviewColor = chartPalette().review;
            const poemReviewChart = buildChart(
                document.getElementById(`poemReviewChart-${poemId}`),
                poemReviewChartData[poemId],
                { label: 'نسبة المراجعة', color: reviewColor, bg: hexToRgba(reviewColor, 0.15), tooltipSuffix: ' من المتن حتى هذا التاريخ' },
            );
            if (poemReviewChart) controllers.push(poemReviewChart);
        });

        if (currentGranularity !== 'daily') {
            controllers.forEach((controller) => controller.setGranularity(currentGranularity));
        }
    }

    renderCharts();

    if (controllers.length === 0 && !reviewChart) {
        return;
    }

    const granularityButtons = document.querySelectorAll('[data-granularity]');
    granularityButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const { granularity } = button.dataset;
            currentGranularity = granularity;
            controllers.forEach((controller) => controller.setGranularity(granularity));

            granularityButtons.forEach((b) => {
                b.classList.toggle('is-active', b === button);
            });
        });
    });

    // إعادة بناء المنحنيات بألوان الوضع الجديد فور تبديله (S19.5) — الحدث
    // مُطلَق من app.js عند كل نقرة على مبدّل الوضع الليلي/النهاري. الهدم ثم
    // البناء الكامل أبسط وأضمن من محاولة تحديث كل لون داخل كل مخطّط قائم،
    // ولا يفقد شيئًا لأن رسم Chart.js رخيص لهذا الحجم من البيانات.
    window.addEventListener('keshf:theme-change', () => {
        controllers.forEach((controller) => controller.destroy());
        if (reviewChart) reviewChart.destroy();
        renderCharts();
    });
});
