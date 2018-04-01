<?php

return [
    'url' => 'https://www.calibur.tv/',
    'image' => 'https://image.calibur.tv/',
    'video' => env('APP_ENV') !== 'production' ? 'https://image.calibur.tv/' : 'https://video.calibur.tv/',
    'list_count' => 15,
    'push_baidu_token' => env('PUSH_BAIDU_TOKEN')
];