<?php

namespace App\Support;

/**
 * توليد كلمة مرور تُملى وتُكتب يدويًا (S13).
 *
 * في منظومة مغلقة لا تُرسَل كلمة المرور برسالة: المدير يقرؤها من الشاشة ويسلّمها
 * للمعلّم مباشرة، والمعلّم يكتبها على هاتفه. لذلك الأبجدية هنا خالية من كل رمز
 * يُقرأ خطأً عند الإملاء (0/O، 1/l/I) ومن الرموز الخاصة التي تختلف مواضعها بين
 * لوحات المفاتيح العربية والإنجليزية — الطول يعوّض عن التنوّع.
 */
class AccountPassword
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 10;

    public static function generate(int $length = self::LENGTH): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= self::ALPHABET[random_int(0, $max)];
        }

        return $password;
    }
}
