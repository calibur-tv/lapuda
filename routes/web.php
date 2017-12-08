<?php

Route::get('/', 'DoorController@index');

Route::group(['prefix' => '/door'], function ()
{
    Route::post('/captcha', 'DoorController@captcha');

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

        Route::get('/post', 'BangumiController@posts');

        Route::post('/follow', 'BangumiController@follow');
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


Route::group(['prefix' => '/user'], function ()
{
    Route::get('/show', 'UserController@show');

    Route::group(['prefix' => '/setting'], function ()
    {
        Route::post('/profile', 'UserController@profile');

        Route::post('/image', 'UserController@image');
    });

    Route::group(['prefix' => '/{zone}'], function ()
    {
        Route::group(['prefix' => '/followed'], function ()
        {
            Route::get('/bangumi', 'UserController@followedBangumis');
        });

        Route::group(['prefix' => '/manager'], function ()
        {
            Route::get('/post', 'UserController@posts');
        });
    });

    Route::get('/user_sign', 'UserController@getUserSign');
});

Route::group(['prefix' => '/post'], function ()
{
    Route::post('/create', 'PostController@create');

    Route::group(['prefix' => '/{id}'], function ()
    {
        Route::get('/show', 'PostController@show');

        Route::post('/reply', 'PostController@reply');

        Route::post('/nice', 'PostController@nice');

        Route::post('/delete', 'PostController@delete');
    });
});

Route::group(['prefix' => '/image'], function ()
{
    Route::post('/token', 'ImageController@token');
});