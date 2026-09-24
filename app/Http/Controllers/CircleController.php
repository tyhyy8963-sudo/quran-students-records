<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCircleRequest;
use App\Models\Circle;

/**
 * إدارة الحلقات (S6) — كانت قائمة بسيطة داخل نافذة منبثقة في لوحة القرآن؛
 * صارت صفحة مستقلّة بتبويبها الخاصّ "الحلقات" (طلب صريح من يحيى
 * 2026-09-23: "ضيف تبويب الحلقات ... وأحذفه من صفحة القرآن"). index() هنا
 * جديد بالكامل — store()/destroy() لم يتغيّرا (الواجهة الجديدة تستدعيهما
 * بنفس الطريقة تمامًا التي كانت النافذة المنبثقة تستدعيهما بها).
 */
class CircleController extends Controller
{
    public function index()
    {
        $circles = Circle::orderBy('name')->get();

        return view('circles.index', ['circles' => $circles]);
    }

    public function store(StoreCircleRequest $request)
    {
        $circle = Circle::create(['name' => $request->validated()['name']]);

        return response()->json([
            'data'    => ['id' => $circle->id, 'name' => $circle->name],
            'message' => 'تمت إضافة الحلقة.',
        ], 201);
    }

    public function destroy($id)
    {
        $circle = Circle::findOrFail($id);

        $this->authorize('delete', $circle);

        // حذف الحلقة لا يحذف طلابها — ترجع الحلقة إلى "بلا حلقة" (set null
        // في الهجرة)، فلا خوف من فقدان طلاب بالخطأ.
        $circle->delete();

        return response()->json([
            'data'    => ['id' => $circle->id],
            'message' => 'تم حذف الحلقة. طلابها لم يُحذفوا.',
        ]);
    }
}
