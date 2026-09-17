<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePoemRequest;
use App\Models\Poem;

/**
 * إدارة مرجع المتون — الشاشة الوحيدة التي يُضاف منها متن جديد (S15)، على غرار
 * TeacherAccountController لحسابات المعلّمين. لا حذف ولا تعديل من الواجهة.
 */
class PoemController extends Controller
{
    public function index()
    {
        $poems = Poem::orderBy('name')->get();

        return view('admin.poems.index', compact('poems'));
    }

    public function store(StorePoemRequest $request)
    {
        $poem = Poem::create($request->validated());

        return response()->json([
            'data'    => $poem,
            'message' => 'تمت إضافة المتن بنجاح.',
        ], 201);
    }
}
