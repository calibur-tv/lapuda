<?php

Route::get('/', 'HelloController@index');

Route::group(['prefix' => '/callback'], function ()
{
    Route::group(['prefix' => '/qiniu'], function ()
    {
        Route::post('/avthumb', 'CallbackController@qiniuAvthumb');
    });
});