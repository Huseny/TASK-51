<?php

namespace App\Http\Requests\Rides;

use Illuminate\Foundation\Http\FormRequest;

class RideOrderTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:cancel'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
