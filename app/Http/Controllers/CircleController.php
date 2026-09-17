<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCircleRequest;
use App\Models\Circle;

/**
 * إدارة الحلقات (S6) — قائمة بسيطة داخل لوحة الطلاب، لا شاشة مستقلة.
 */
class CircleController extends Controller
{
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
