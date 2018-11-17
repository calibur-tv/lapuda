<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/11/17
 * Time: 下午8:23
 */
return [
    'qq' => [
        'client_id' => env('QQ_AUTH_APP_ID'),
        'client_secret' => env('QQ_AUTH_APP_KEY'),
        'redirect' => 'https://api.calibur.tv/callback/auth/qq'
    ],
    'wechat' => [
        'client_id' => env('WECHAT_APP_OPEN_ID'),
        'client_secret' => env('WECHAT_APP_OPEN_SECRET'),
        'redirect' => 'https://api.calibur.tv/callback/auth/wechat'
    ],
    'wechat_owner' => [
        'client_id' => env('WECHAT_APP_OWNER_ID'),
        'client_secret' => env('WECHAT_APP_OWNER_SECRET')
    ]
];
