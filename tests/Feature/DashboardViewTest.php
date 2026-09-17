<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * العطل U-01 — كان في لوحة الطلاب مستمع document.click يعيد أي صفّ في وضع
 * التعديل إلى قيمه الأصلية بمجرّد وقوع نقرة خارجه، بلا تحذير وبلا تراجع.
 *
 * لا يمكن اختبار سلوك JavaScript بـ PHPUnit، فهذا اختبار حراسة على المصدر:
 * يفشل إن عاد النمط المدمّر إلى الواجهة.
 */
class DashboardViewTest extends TestCase
{
    /** @test */
    public function the_dashboard_view_has_no_global_click_listener_that_reverts_rows(): void
    {
        $source = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertStringNotContainsString(
            "document.addEventListener('click'",
            $source,
            'عاد مستمع النقر العام إلى لوحة الطلاب — العطل U-01.'
        );

        $this->assertStringNotContainsString(
            '.student-row.editing',
            $source,
            'عاد منطق إلغاء التعديل بالنقر خارج الصفّ — العطل U-01.'
        );
    }

    /** @test */
    public function the_repository_no_longer_carries_an_application_key(): void
    {
        // يُبنى النص المطلوب البحث عنه بالتركيب حتى لا يطابق ملف الاختبار نفسه.
        $needle = 'APP_KEY'.'=base64:';
        $leaked = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator(base_path(), \FilesystemIterator::SKIP_DOTS),
                fn ($file) => ! in_array($file->getFilename(), ['vendor', 'node_modules', '.git', 'storage'], true)
            )
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getFilename() === '.env') {
                continue;
            }
            $contents = @file_get_contents($file->getPathname());
            if ($contents !== false && str_contains($contents, $needle)) {
                $leaked[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $leaked, 'مفتاح تطبيق مكشوف داخل المستودع.');
    }
}
