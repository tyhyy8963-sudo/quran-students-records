import {
    Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip,
} from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip);

/**
 * منحنى التقدّم (S8) — مُجمَّع كملف Vite مستقل عن app.js، فلا تحمّل صفحات
 * الدخول والتسجيل مكتبة رسم بياني لن تستخدمها أبدًا.
 */
document.addEventListener('DOMContentLoaded', () => {
    const canvas = document.getElementById('progressChart');
    if (!canvas || !window.KeshfChartData) return;

    const points = window.KeshfChartData;
    if (!points.length) return;

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map((p) => p.date),
            datasets: [{
                label: 'نسبة الحفظ',
                data: points.map((p) => p.percent),
                borderColor: '#606c38',
                backgroundColor: 'rgba(96, 108, 56, 0.15)',
                fill: true,
                tension: 0.25,
                pointRadius: 3,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.parsed.y}% من المصحف حتى هذا التاريخ`,
                    },
                },
            },
            scales: {
                // المحور يبدأ من الصفر دائمًا (لا محور مبتور يضخّم فرقًا صغيرًا)،
                // وسقفه أوسع من أعلى نقطة بمقدار النصف تقريبًا مقرَّبًا لأقرب
                // عشرة، بحد أدنى 10% وأقصى 100%: مقياس 0–100 ثابت يسحق منحنى
                // طالب عند 5% في شريط ملتصق بالقاع فلا يُقرأ منه شيء، والمقياس
                // التلقائي المحض يملأ الشاشة بتقدّم 2% فيوهم بالاكتمال.
                y: {
                    beginAtZero: true,
                    suggestedMax: Math.min(100, Math.max(10, Math.ceil(Math.max(...points.map((p) => p.percent)) * 1.5 / 10) * 10)),
                    title: { display: true, text: 'نسبة الحفظ %' },
                    ticks: { callback: (value) => `${value}%` },
                },
                x: { title: { display: true, text: 'التاريخ' } },
            },
        },
    });
});
