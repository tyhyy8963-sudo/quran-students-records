<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ProfileEditController extends Controller
{
    // عرض صفحة تعديل البيانات
    public function edit()
    {
        $user = Auth::user();
        return view('profile_edit', compact('user'));
    }

    // تحديث البيانات العامة فقط
    public function update(Request $request)
{
    /** @var \App\Models\User $user */
        $user = Auth::user();


    $request->validate([
        'name' => 'required|string|max:255',
        'mosque' => 'nullable|string|max:255',
        'classroom' => 'nullable|string|max:255',
    ]);

    $user->update([
        'name' => $request->name,
        'mosque' => $request->mosque,
        'classroom' => $request->classroom,
    ]);

    return redirect()->route('profile.edit')->with('success', 'تم تحديث البيانات بنجاح!');
}
}
