<?php

return [
    'signed_url_minutes' => (int) env('MEDIA_SIGNED_URL_MINUTES', 10),
    'max_image_bytes' => 8 * 1024 * 1024,
    'max_video_bytes' => 200 * 1024 * 1024,
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'mp4'],
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'video/mp4'],
];
