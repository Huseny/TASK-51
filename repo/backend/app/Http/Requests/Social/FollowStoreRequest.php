<?php

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;

class FollowStoreRequest extends FormRequest
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
            'followed_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
