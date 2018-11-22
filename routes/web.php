<?php

Route::get('/', 'HelloController@index');

Route::group(['prefix' => '/callback'], function ()
{
    Route::group(['prefix' => '/qiniu'], function ()
    {
        Route::post('/avthumb', 'CallbackController@qiniuAvthumb');

        Route::post('/uploadimage', 'CallbackController@qiniuUploadImage');
    });

    Route::group(['prefix' => '/auth'], function ()
    {
        Route::get('/qq', 'CallbackController@qqAuthRedirect');

        Route::get('/wechat', 'CallbackController@wechatAuthRedirect');

        Route::get('/weixin', 'CallbackController@weixinAuthRedirect');
    });

    Route::group(['prefix' => '/oauth2'], function ()
    {
        Route::get('/qq', 'CallbackController@qqAuthEntry');

        Route::get('/wechat', 'CallbackController@wechatAuthEntry');

        Route::get('/weixin', 'CallbackController@weixinAuthEntry');
    });
});