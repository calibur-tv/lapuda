<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */
    'qq' => [
        'client_id' => env('QQ_AUTH_APP_ID'),
        'client_secret' => env('QQ_AUTH_APP_KEY'),
        'redirect' => 'https://api.calibur.tv/callback/auth/qq'
    ],
    // PC 微信登录
    'wechat' => [
        'client_id' => env('WECHAT_APP_OPEN_ID'),
        'client_secret' => env('WECHAT_APP_OPEN_SECRET'),
        'redirect' => 'https://api.calibur.tv/callback/auth/wechat'
    ],
    // H5 微信登录
    'weixin' => [
        'client_id' => env('WECHAT_APP_OWNER_ID'),
        'client_secret' => env('WECHAT_APP_OWNER_SECRET'),
        'redirect' => 'https://api.calibur.tv/callback/auth/weixin'
    ]
];
