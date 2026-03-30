<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class NotificationScenarioRequest extends FormRequest
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
            'scenario' => ['required', 'in:comment,reply,mention,follower,moderation,announcement'],
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'ride_id' => ['required_if:scenario,comment,reply,mention', 'nullable', 'integer', 'exists:ride_orders,id'],
            'entity_type' => ['nullable', 'string', 'max:40'],
            'entity_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string', 'max:255'],
        ];
    }
}
