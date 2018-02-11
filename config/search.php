<?php

return [
    'name' => env('ALIYUN_OPEN_SEARCH_APP_NAME'),
    'access' => env('ALIYUN_OPEN_SEARCH_ACCESS'),
    'secret' => env('ALIYUN_OPEN_SEARCH_SECRET'),
    'endpoint' => env('APP_ENV') === 'production' ? env('ALIYUN_OPEN_SEARCH_END_POINT_PROD') : env('ALIYUN_OPEN_SEARCH_END_POINT_TEST')
];
