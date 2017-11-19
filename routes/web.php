<?php

Route::get('/', 'DoorController@index');

Route::post('/deploy', 'DoorController@deploy');

Route::group(['prefix' => '/door'], function ()
{
    Route::post('/captcha', 'DoorController@captcha');

    Route::post('/check/{method}/{access}', 'DoorController@checkAccessUnique');

    Route::post('/send', 'DoorController@sendEmailOrMessage');

    Route::post('/register', 'DoorController@register');

    Route::post('/login', 'DoorController@login');

    Route::post('/user', 'DoorController@refresh');

    Route::post('/logout', 'DoorController@logout');
});

Route::group(['prefix' => '/bangumi'], function ()
{
    Route::get('/news', 'BangumiController@news');

    Route::get('/tags', 'BangumiController@tags');

    Route::group(['prefix' => '/{id}'], function ()
    {
        Route::get('/show', 'BangumiController@show');
    });
});

Route::group(['prefix' => '/video'], function ()
{
    Route::group(['prefix' => '/{id}'], function ()
    {
        Route::get('/show', 'VideoController@show');

        Route::post('/playing', 'VideoController@playing');
    });
});

Route::group(['prefix' => '/cartoon'], function ()
{
    Route::get('/banner', 'DoorController@banner');
});