<?php

namespace App\Http\Requests\Rides;

use Illuminate\Foundation\Http\FormRequest;

class FleetRideCancelRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
