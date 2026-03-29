<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ReportExportRequest extends FormRequest
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
            'type' => ['required', 'in:trends,distribution,regions'],
            'format' => ['required', 'in:csv,xlsx'],
            'destination' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'filters' => ['nullable', 'array'],
            'filters.start_date' => ['nullable', 'date'],
            'filters.end_date' => ['nullable', 'date', 'after_or_equal:filters.start_date'],
            'filters.grouping' => ['nullable', 'in:day,month'],
        ];
    }
}
