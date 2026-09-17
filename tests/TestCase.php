<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // الاختبارات قد تُشغَّل دون بناء أصول Vite مسبقًا (لا npm في بيئة CI
        // الأساسية هنا). withoutVite() تجعل @vite() يطبع وسومًا فارغة بدل
        // البحث عن ملف manifest.json غير موجود ورمي استثناء (S5 · #30).
        $this->withoutVite();
    }
}
