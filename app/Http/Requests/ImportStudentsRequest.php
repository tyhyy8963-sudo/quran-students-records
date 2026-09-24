<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * استيراد طلاب من ملف CSV (S11) أو إكسل حقيقي .xlsx/.xls (S17) — نفس تخطيط
 * عمودين (اسم الطالب، الحلقة) لكلا الصيغتين، يُعالَجان بنفس المنطق تمامًا في
 * StudentController::import() (راجع processImportRows() هناك). حجم الحد
 * الأقصى رُفع من 1 إلى 5 ميجابايت لأن ملفات .xlsx تحمل تنسيقًا إضافيًا يكبّر
 * حجمها عن CSV النصي المكافئ حتى لعدد الصفوف نفسه.
 */
class ImportStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'اختر ملفًا أولًا.',
            'file.mimes'    => 'الملف يجب أن يكون بصيغة CSV أو Excel (.xlsx/.xls).',
            'file.max'      => 'حجم الملف كبير جدًا (الحد الأقصى 5 ميجابايت).',
        ];
    }
}
