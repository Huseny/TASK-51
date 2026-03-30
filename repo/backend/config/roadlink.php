<?php

return [
    'channels' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', env('ROADLINK_NOTIFICATION_CHANNELS', 'in_app'))
    ))),

    'sms' => [
        'enabled' => filter_var(env('ROADLINK_SMS_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    'recommendations' => [
        'epsilon' => (float) env('ROADLINK_RECOMMENDATION_EPSILON', 0.10),
        'max_items_per_seller' => (int) env('ROADLINK_RECOMMENDATION_MAX_PER_SELLER', 2),
    ],
];
