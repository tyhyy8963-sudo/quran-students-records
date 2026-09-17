<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * تغيير كلمة المرور للمستخدم المسجّل دخوله.
 *
 * ملاحظة على التغيير (العطل C-01):
 * كانت العملية موزّعة على صفحتين — الأولى تتحقق من كلمة المرور الحالية عبر AJAX،
 * والثانية تحفظ الجديدة. لأن نتيجة التحقق لم تكن تُخزَّن في أي مكان، كان مسار الحفظ
 * يقبل أي طلب قادم من جلسة مفتوحة دون معرفة كلمة المرور الحالية إطلاقًا.
 * الحاجز كان في الواجهة فقط، والواجهة ليست حاجزًا.
 *
 * الآن صفحة واحدة، والتحقق من كلمة المرور الحالية يجري على الخادم
 * في نفس الطلب الذي يحفظ الجديدة.
 */
class PasswordController extends Controller
{
    /**
     * عرض صفحة تغيير كلمة المرور (نموذج واحد: الحالية + الجديدة + التأكيد).
     */
    public function edit()
    {
        return view('editpass');
    }

    /**
     * حفظ كلمة المرور الجديدة بعد التحقق من الحالية.
     */
    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'new_password'     => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required'         => 'الرجاء إدخال كلمة المرور الحالية.',
            'current_password.current_password' => 'كلمة المرور الحالية غير صحيحة.',
            'new_password.required'             => 'الرجاء إدخال كلمة المرور الجديدة.',
            'new_password.min'                  => 'كلمة المرور يجب أن تكون 6 رموز أو أكثر.',
            'new_password.confirmed'            => 'كلمة المرور وتأكيدها غير متطابقين.',
            'new_password.different'            => 'كلمة المرور الجديدة يجب أن تختلف عن الحالية.',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // 1) حفظ كلمة المرور الجديدة.
        $user->forceFill([
            'password' => Hash::make($request->input('new_password')),
        ])->save();

        // 2) إبطال جلسات بقية الأجهزة.
        //    logoutOtherDevices تتوقّع كلمة المرور الحالية للمستخدم — وقد صارت الجديدة
        //    بعد الخطوة الأولى — فتعيد تجزئتها بملح مختلف وتجدّد كوكي التذكّر للجلسة الحالية.
        //    فعّالة لأن المنطقة المحمية تمرّ بوسيط auth.session الذي يقارن بصمة كلمة المرور
        //    المحفوظة في الجلسة ببصمتها في قاعدة البيانات.
        Auth::logoutOtherDevices($request->input('new_password'));

        // 3) تدوير معرّف الجلسة الحالية بعد تغيير بيانات الاعتماد.
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'تم تحديث كلمة المرور بنجاح.');
    }
}
