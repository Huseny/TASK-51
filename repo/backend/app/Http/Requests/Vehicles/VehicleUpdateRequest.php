<?php

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class VehicleUpdateRequest extends FormRequest
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
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'between:1990,2030'],
            'license_plate' => ['required', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'between:1,15'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }
}
