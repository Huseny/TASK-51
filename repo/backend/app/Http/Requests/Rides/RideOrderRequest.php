<?php

namespace App\Http\Requests\Rides;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class RideOrderRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    private array $acceptedFormats = [
        'Y-m-d H:i',
        'Y-m-d H:i:s',
        'Y-m-d g:i A',
        'Y-m-d h:i A',
        'm/d/Y H:i',
        'm/d/Y g:i A',
        \DateTimeInterface::ATOM,
    ];

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
            'time_window_start' => ['required', 'date_format:Y-m-d H:i:s', 'after:now'],
            'time_window_end' => ['required', 'date_format:Y-m-d H:i:s', 'after:time_window_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'time_window_start' => $this->normalizeDateTime($this->input('time_window_start')),
            'time_window_end' => $this->normalizeDateTime($this->input('time_window_end')),
        ]);
    }

    private function normalizeDateTime(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $candidate = trim($value);

        foreach ($this->acceptedFormats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $candidate);

                if ($parsed !== false) {
                    return $parsed->format('Y-m-d H:i:s');
                }
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($candidate)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }
}
