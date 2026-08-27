<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLegacyApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expiresInDays' => [
                'nullable',
                'integer',
                Rule::in([30, 60, 90, 180, 365]),
            ],
        ];
    }
}
