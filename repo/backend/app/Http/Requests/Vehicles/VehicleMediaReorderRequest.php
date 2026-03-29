<?php

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class VehicleMediaReorderRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1'],
            'order.*.media_id' => ['required', 'integer', 'min:1'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
