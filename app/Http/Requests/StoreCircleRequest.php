<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('circles', 'name')->where('teacher_id', $this->user()?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الحلقة مطلوب.',
            'name.unique'   => 'لديك حلقة بهذا الاسم بالفعل.',
        ];
    }
}
