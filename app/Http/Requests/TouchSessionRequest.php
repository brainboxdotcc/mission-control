<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TouchSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'lease_id' => ['required', 'uuid'],
            'mode' => ['nullable', 'in:input,heartbeat'],
        ];
    }
}
