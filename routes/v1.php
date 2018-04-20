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
    $api->group(['prefix' => '/search'], function ($api)
    {
        $api->get('/index', 'App\Api\V1\Controllers\SearchController@index');
    });

    $api->group(['prefix' => '/door'], function ($api)
    {
        $api->post('/send', 'App\Api\V1\Controllers\DoorController@sendEmailOrMessage')->middleware(['geetest', 'throttle:60,1']);

        $api->post('/register', 'App\Api\V1\Controllers\DoorController@register')->middleware(['geetest', 'throttle']);

        $api->post('/login', 'App\Api\V1\Controllers\DoorController@login')->middleware(['geetest', 'throttle']);

        $api->post('/user', 'App\Api\V1\Controllers\DoorController@refresh')->middleware(['jwt.refresh', 'throttle:60,1']);

        $api->post('/forgot', 'App\Api\V1\Controllers\DoorController@forgotPassword')->middleware(['geetest', 'throttle']);

        $api->post('/reset', 'App\Api\V1\Controllers\DoorController@resetPassword')->middleware(['geetest', 'throttle']);

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

            $api->post('/images', 'App\Api\V1\Controllers\BangumiController@images');

            $api->post('/follow', 'App\Api\V1\Controllers\BangumiController@follow')->middleware(['jwt.auth', 'throttle:30,1']);

            $api->post('/followers', 'App\Api\V1\Controllers\BangumiController@followers');

            $api->get('/roles', 'App\Api\V1\Controllers\CartoonRoleController@listOfBangumi');

            $api->group(['prefix' => '/role/{roleId}'], function ($api)
            {
                $api->get('/fans', 'App\Api\V1\Controllers\CartoonRoleController@fans');

                $api->post('/star', 'App\Api\V1\Controllers\CartoonRoleController@star')->middleware(['jwt.auth']);
            });
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
            $api->post('/profile', 'App\Api\V1\Controllers\UserController@profile')->middleware(['jwt.auth', 'throttle']);

            $api->post('/image', 'App\Api\V1\Controllers\UserController@image')->middleware(['jwt.auth', 'throttle']);
        });

        $api->group(['prefix' => '/notification'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\UserController@notifications')->middleware(['jwt.auth', 'throttle:30,1']);

            $api->get('/count', 'App\Api\V1\Controllers\UserController@waitingReadNotifications')->middleware('jwt.auth');

            $api->post('/read', 'App\Api\V1\Controllers\UserController@readNotification')->middleware('jwt.auth');

            $api->post('/clear', 'App\Api\V1\Controllers\UserController@clearNotification')->middleware('jwt.auth');
        });

        $api->post('/daySign', 'App\Api\V1\Controllers\UserController@daySign')->middleware('jwt.auth');

        $api->post('/feedback', 'App\Api\V1\Controllers\UserController@feedback')->middleware('throttle');

        $api->group(['prefix' => '/{zone}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\UserController@show');

            $api->group(['prefix' => '/followed'], function ($api)
            {
                $api->get('/bangumi', 'App\Api\V1\Controllers\UserController@followedBangumis');

                $api->get('/role', 'App\Api\V1\Controllers\UserController@followedRoles');
            });

            $api->group(['prefix' => '/images'], function ($api)
            {
                $api->post('/list', 'App\Api\V1\Controllers\UserController@imageList')->middleware('throttle:30,1');
            });

            $api->group(['prefix' => '/posts'], function ($api)
            {
                $api->post('/mine', 'App\Api\V1\Controllers\UserController@postsOfMine')->middleware('throttle:30,1');

                $api->post('/reply', 'App\Api\V1\Controllers\UserController@postsOfReply')->middleware('throttle:30,1');

                $api->post('/like', 'App\Api\V1\Controllers\UserController@postsOfLiked')->middleware('throttle:30,1');

                $api->post('/mark', 'App\Api\V1\Controllers\UserController@postsOfMarked')->middleware('throttle:30,1');
            });
        });
    });

    $api->group(['prefix' => '/post'], function ($api)
    {
        $api->post('/create', 'App\Api\V1\Controllers\PostController@create')->middleware(['jwt.auth', 'geetest', 'throttle']);

        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->post('/show', 'App\Api\V1\Controllers\PostController@show');

            $api->post('/comments', 'App\Api\V1\Controllers\PostController@comments')->middleware('throttle:30,1');

            $api->post('/reply', 'App\Api\V1\Controllers\PostController@reply')->middleware(['jwt.auth', 'geetest', 'throttle:20,1']);

            $api->post('/comment', 'App\Api\V1\Controllers\PostController@comment')->middleware(['jwt.auth', 'throttle:20,1']);

            $api->post('/toggleLike', 'App\Api\V1\Controllers\PostController@toggleLike')->middleware(['jwt.auth', 'throttle:30,1']);

            $api->post('/toggleMark', 'App\Api\V1\Controllers\PostController@toggleMark')->middleware(['jwt.auth', 'throttle:30,1']);

            $api->post('/likeUsers', 'App\Api\V1\Controllers\PostController@likeUsers')->middleware('throttle:30,1');

            $api->post('/deletePost', 'App\Api\V1\Controllers\PostController@deletePost')->middleware(['jwt.auth', 'throttle:30,1']);

            $api->post('/deleteComment', 'App\Api\V1\Controllers\PostController@deleteComment')->middleware(['jwt.auth', 'throttle:30,1']);
        });
    });

    $api->group(['prefix' => '/image'], function ($api)
    {
        $api->get('/banner', 'App\Api\V1\Controllers\ImageController@banner');

        $api->get('/uploadType', 'App\Api\V1\Controllers\ImageController@uploadType');

        $api->post('/captcha', 'App\Api\V1\Controllers\ImageController@captcha')->middleware('throttle:10,1');

        $api->post('/uptoken', 'App\Api\V1\Controllers\ImageController@uptoken')->middleware(['jwt.auth', 'throttle:20,1']);

        $api->post('/upload', 'App\Api\V1\Controllers\ImageController@upload')->middleware(['jwt.auth', 'throttle:20,1']);

        $api->post('/delete', 'App\Api\V1\Controllers\ImageController@delete')->middleware(['jwt.auth', 'throttle:20,1']);

        $api->post('/report', 'App\Api\V1\Controllers\ImageController@report')->middleware(['throttle:20,1']);

        $api->post('/edit', 'App\Api\V1\Controllers\ImageController@edit')->middleware(['jwt.auth', 'throttle:20,1']);

        $api->post('/toggleLike', 'App\Api\V1\Controllers\ImageController@toggleLike')->middleware(['jwt.auth', 'throttle:20,1']);

        $api->post('/trendingList', 'App\Api\V1\Controllers\ImageController@trendingList');
    });

    $api->group(['prefix' => '/trending'], function ($api)
    {
        $api->group(['prefix' => '/post'], function ($api)
        {
            $api->post('/new', 'App\Api\V1\Controllers\TrendingController@postNew');

            $api->post('/hot', 'App\Api\V1\Controllers\TrendingController@postHot');
        });

        $api->get('/cartoon_role', 'App\Api\V1\Controllers\TrendingController@cartoonRole');
    });
});