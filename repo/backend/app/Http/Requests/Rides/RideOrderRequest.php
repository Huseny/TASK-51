<?php

namespace App\Http\Requests\Rides;

use Illuminate\Foundation\Http\FormRequest;

class RideOrderRequest extends FormRequest
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
            'origin_address' => ['required', 'string', 'max:500'],
            'destination_address' => ['required', 'string', 'max:500'],
            'rider_count' => ['required', 'integer', 'min:1', 'max:6'],
            'time_window_start' => ['required', 'date_format:Y-m-d H:i', 'after:now'],
            'time_window_end' => ['required', 'date_format:Y-m-d H:i', 'after:time_window_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
