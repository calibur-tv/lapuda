<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/3
 * Time: 下午10:16
 */
$api = app('Dingo\Api\Routing\Router');

$api->version(['v1', 'latest'], function ($api)
{
    $api->group(['prefix' => '/door'], function ($api)
    {
        $api->post('/send', 'App\Api\V1\Controllers\DoorController@sendEmailOrMessage');

        $api->post('/register', 'App\Api\V1\Controllers\DoorController@register');
        $api->get('/register', 'App\Api\V1\Controllers\DoorController@register');

        $api->post('/login', 'App\Api\V1\Controllers\DoorController@login');

        $api->post('/user', 'App\Api\V1\Controllers\DoorController@refresh');

        $api->post('/logout', 'App\Api\V1\Controllers\DoorController@logout');
    });

    $api->group(['prefix' => '/bangumi'], function ($api)
    {
        $api->get('/timeline', 'App\Api\V1\Controllers\BangumiController@timeline');

        $api->get('/released', 'App\Api\V1\Controllers\BangumiController@released');

        $api->get('/tags', 'App\Api\V1\Controllers\BangumiController@tags');

        $api->get('/category', 'App\Api\V1\Controllers\BangumiController@category');

        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\BangumiController@show');

            $api->get('/videos', 'App\Api\V1\Controllers\BangumiController@videos');

            $api->post('/posts', 'App\Api\V1\Controllers\BangumiController@posts');

            $api->post('/follow', 'App\Api\V1\Controllers\BangumiController@follow');

            $api->post('/followers', 'App\Api\V1\Controllers\BangumiController@followers');
        });
    });

    $api->group(['prefix' => '/video'], function ($api)
    {
        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\VideoController@show');

            $api->post('/playing', 'App\Api\V1\Controllers\VideoController@playing');
        });
    });

    $api->group(['prefix' => '/user'], function ($api)
    {
        $api->group(['prefix' => '/setting'], function ($api)
        {
            $api->post('/profile', 'App\Api\V1\Controllers\UserController@profile')->middleware('throttle:5,10');

            $api->post('/image', 'App\Api\V1\Controllers\UserController@image');
        });

        $api->group(['prefix' => '/notification'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\UserController@notifications');

            $api->get('/count', 'App\Api\V1\Controllers\UserController@waitingReadNotifications');

            $api->post('/read', 'App\Api\V1\Controllers\UserController@readNotification');
        });

        $api->post('/daySign', 'App\Api\V1\Controllers\UserController@daySign');

        $api->post('/feedback', 'App\Api\V1\Controllers\UserController@feedback');

        $api->group(['prefix' => '/{zone}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\UserController@show');

            $api->group(['prefix' => '/followed'], function ($api)
            {
                $api->get('/bangumi', 'App\Api\V1\Controllers\UserController@followedBangumis');
            });

            $api->post('/posts/mine', 'App\Api\V1\Controllers\UserController@postsOfMine');

            $api->post('/posts/reply', 'App\Api\V1\Controllers\UserController@postsOfReply');
        });
    });

    $api->group(['prefix' => '/post'], function ($api)
    {
        $api->post('/create', 'App\Api\V1\Controllers\PostController@create')->middleware('throttle:5,10');

        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->post('/show', 'App\Api\V1\Controllers\PostController@show');
            $api->get('/show', 'App\Api\V1\Controllers\PostController@show');

            $api->post('/comments', 'App\Api\V1\Controllers\PostController@comments');

            $api->post('/reply', 'App\Api\V1\Controllers\PostController@reply')->middleware('throttle');

            $api->post('/comment', 'App\Api\V1\Controllers\PostController@comment')->middleware('throttle:20,1');

            $api->post('/toggleLike', 'App\Api\V1\Controllers\PostController@toggleLike');

            $api->post('/likeUsers', 'App\Api\V1\Controllers\PostController@likeUsers');

            $api->post('/deletePost', 'App\Api\V1\Controllers\PostController@deletePost');

            $api->post('/deleteComment', 'App\Api\V1\Controllers\PostController@deleteComment');
        });
    });

    $api->group(['prefix' => '/image'], function ($api)
    {
        $api->get('/banner', 'App\Api\V1\Controllers\ImageController@banner');

        $api->post('/captcha', 'App\Api\V1\Controllers\ImageController@captcha');

        $api->post('/uptoken', 'App\Api\V1\Controllers\ImageController@uptoken');
    });

    $api->group(['prefix' => '/trending'], function ($api)
    {
        $api->group(['prefix' => '/post'], function ($api)
        {
            $api->post('/new', 'App\Api\V1\Controllers\TrendingController@postNew');

            $api->post('/hot', 'App\Api\V1\Controllers\TrendingController@postHot');
        });
    });
});