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
        $api->get('/new', 'App\Api\V1\Controllers\SearchController@search');

        $api->get('/bangumis', 'App\Api\V1\Controllers\SearchController@bangumis');

        $api->get('/migration', 'App\Api\V1\Controllers\SearchController@migration');
    });

    $api->group(['prefix' => '/door'], function ($api)
    {
        $api->post('/message', 'App\Api\V1\Controllers\DoorController@sendMessage')->middleware(['geetest']);

        $api->post('/register', 'App\Api\V1\Controllers\DoorController@register');

        $api->post('/login', 'App\Api\V1\Controllers\DoorController@login')->middleware(['geetest']);

        $api->post('/user', 'App\Api\V1\Controllers\DoorController@refresh')->middleware(['jwt.refresh']);

        $api->post('/reset', 'App\Api\V1\Controllers\DoorController@resetPassword');

        $api->post('/logout', 'App\Api\V1\Controllers\DoorController@logout')->middleware(['jwt.auth']);
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

            $api->group(['prefix' => '/posts'], function ($api)
            {
                $api->get('/news', 'App\Api\V1\Controllers\BangumiController@newsPosts');

                $api->get('/top', 'App\Api\V1\Controllers\BangumiController@topPosts');
            });

            $api->get('/images', 'App\Api\V1\Controllers\BangumiController@images');

            $api->get('/cartoon', 'App\Api\V1\Controllers\BangumiController@cartoon');

            $api->post('/toggleFollow', 'App\Api\V1\Controllers\BangumiController@toggleFollow')->middleware(['jwt.auth']);

            $api->get('/followers', 'App\Api\V1\Controllers\BangumiController@followers');

            $api->get('/managers', 'App\Api\V1\Controllers\BangumiController@managers');

            $api->get('/roles', 'App\Api\V1\Controllers\CartoonRoleController@listOfBangumi');

            $api->post('/edit', 'App\Api\V1\Controllers\BangumiController@editBangumiInfo')->middleware(['jwt.auth']);

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
        });

        $api->post('/playing', 'App\Api\V1\Controllers\VideoController@playing');
    });

    $api->group(['prefix' => '/user'], function ($api)
    {
        $api->group(['prefix' => '/setting'], function ($api)
        {
            $api->post('/profile', 'App\Api\V1\Controllers\UserController@profile')->middleware(['jwt.auth']);

            $api->post('/image', 'App\Api\V1\Controllers\UserController@image')->middleware(['jwt.auth']);
        });

        $api->group(['prefix' => '/notification'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\UserController@notifications')->middleware(['jwt.auth']);

            $api->get('/count', 'App\Api\V1\Controllers\UserController@waitingReadNotifications')->middleware('jwt.auth');

            $api->post('/read', 'App\Api\V1\Controllers\UserController@readNotification')->middleware('jwt.auth');

            $api->post('/clear', 'App\Api\V1\Controllers\UserController@clearNotification')->middleware('jwt.auth');
        });

        $api->post('/daySign', 'App\Api\V1\Controllers\UserController@daySign')->middleware('jwt.auth');

        $api->post('/feedback', 'App\Api\V1\Controllers\UserController@feedback');

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
                $api->get('/list', 'App\Api\V1\Controllers\UserController@imageList');
            });

            $api->group(['prefix' => '/posts'], function ($api)
            {
                $api->get('/mine', 'App\Api\V1\Controllers\UserController@postsOfMine');

                $api->get('/reply', 'App\Api\V1\Controllers\UserController@postsOfReply');

                $api->get('/like', 'App\Api\V1\Controllers\UserController@postsOfLiked');

                $api->get('/mark', 'App\Api\V1\Controllers\UserController@postsOfMarked');
            });
        });

        $api->get('/images/albums', 'App\Api\V1\Controllers\UserController@imageAlbums')->middleware(['jwt.auth']);
    });

    $api->group(['prefix' => '/post'], function ($api)
    {
        $api->post('/create', 'App\Api\V1\Controllers\PostController@create')->middleware(['jwt.auth', 'geetest']);

        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\PostController@show');

            $api->get('/likeUsers', 'App\Api\V1\Controllers\PostController@likeUsers');

            $api->post('/toggleLike', 'App\Api\V1\Controllers\PostController@toggleLike')->middleware(['jwt.auth']);

            $api->post('/toggleMark', 'App\Api\V1\Controllers\PostController@toggleMark')->middleware(['jwt.auth']);

            $api->post('/deletePost', 'App\Api\V1\Controllers\PostController@deletePost')->middleware(['jwt.auth']);

            $api->post('/deleteComment', 'App\Api\V1\Controllers\PostController@deleteComment')->middleware(['jwt.auth']);
        });

        $api->group(['prefix' => '/trending'], function ($api)
        {
            $api->get('/news', 'App\Api\V1\Controllers\PostController@postNews');

            $api->get('/active', 'App\Api\V1\Controllers\PostController@postActive');

            $api->get('/hot', 'App\Api\V1\Controllers\PostController@postHot');
        });

        $api->group(['prefix' => '/manager', 'middleware' => ['jwt.auth']], function ($api)
        {
            $api->group(['prefix' => '/top'], function ($api)
            {
                $api->post('/set', 'App\Api\V1\Controllers\PostController@setTop');

                $api->post('/remove', 'App\Api\V1\Controllers\PostController@removeTop');
            });

            $api->group(['prefix' => '/nice'], function ($api)
            {
                $api->post('/set', 'App\Api\V1\Controllers\PostController@setNice');

                $api->post('/remove', 'App\Api\V1\Controllers\PostController@removeNice');
            });
        });
    });

    $api->group(['prefix' => '/{type}/comment'], function ($api)
    {
        $api->get('/{id}/main/list', 'App\Api\V1\Controllers\CommentController@mainList');

        $api->get('/{id}/sub/list', 'App\Api\V1\Controllers\CommentController@subList');

        $api->post('/{id}/create', 'App\Api\V1\Controllers\CommentController@create')->middleware(['jwt.auth']);

        $api->post('/{id}/reply', 'App\Api\V1\Controllers\CommentController@reply')->middleware(['jwt.auth']);

        $api->post('/delete/main/{id}', 'App\Api\V1\Controllers\CommentController@deleteMainComment')->middleware(['jwt.auth']);

        $api->post('/delete/sub/{id}', 'App\Api\V1\Controllers\CommentController@deleteSubComment')->middleware(['jwt.auth']);

        $api->post('/sub/toggleLike/{id}', 'App\Api\V1\Controllers\CommentController@toggleLikeSubComment')->middleware(['jwt.auth']);

        $api->post('/main/toggleLike/{id}', 'App\Api\V1\Controllers\CommentController@toggleLikeMainComment')->middleware(['jwt.auth']);
    });

    $api->group(['prefix' => '/image'], function ($api)
    {
        $api->get('/banner', 'App\Api\V1\Controllers\ImageController@banner');

        $api->get('/uploadType', 'App\Api\V1\Controllers\ImageController@uploadType');

        $api->get('/captcha', 'App\Api\V1\Controllers\ImageController@captcha');

        $api->get('/uptoken', 'App\Api\V1\Controllers\ImageController@uptoken')->middleware(['jwt.auth']);

        $api->post('/upload', 'App\Api\V1\Controllers\ImageController@upload')->middleware(['jwt.auth']);

        $api->post('/delete', 'App\Api\V1\Controllers\ImageController@deleteImage')->middleware(['jwt.auth']);

        $api->post('/report', 'App\Api\V1\Controllers\ImageController@report');

        $api->post('/edit', 'App\Api\V1\Controllers\ImageController@editImage')->middleware(['jwt.auth']);

        $api->post('/toggleLike', 'App\Api\V1\Controllers\ImageController@toggleLike')->middleware(['jwt.auth']);

        $api->post('/createAlbum', 'App\Api\V1\Controllers\ImageController@createAlbum')->middleware(['jwt.auth']);

        $api->post('/editAlbum', 'App\Api\V1\Controllers\ImageController@editAlbum')->middleware(['jwt.auth']);

        $api->post('/viewedMark', 'App\Api\V1\Controllers\ImageController@viewedMark');

        $api->get('/trendingList', 'App\Api\V1\Controllers\ImageController@trendingList');

        $api->group(['prefix' => '/album/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\ImageController@albumShow');

            $api->post('/sort', 'App\Api\V1\Controllers\ImageController@albumSort')->middleware(['jwt.auth']);

            $api->post('/deleteImage', 'App\Api\V1\Controllers\ImageController@deleteAlbumImage')->middleware(['jwt.auth']);
        });
    });

    $api->group(['prefix' => '/cartoon_role'], function ($api)
    {
        $api->group(['prefix' => '/manager', 'middleware' => ['jwt.auth']], function ($api)
        {
            $api->post('/create', 'App\Api\V1\Controllers\CartoonRoleController@create');

            $api->post('/edit', 'App\Api\V1\Controllers\CartoonRoleController@edit');
        });

        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\CartoonRoleController@show');

            $api->get('/images', 'App\Api\V1\Controllers\CartoonRoleController@images');
        });
    });

    $api->group(['prefix' => '/trending'], function ($api)
    {
        $api->get('/cartoon_role', 'App\Api\V1\Controllers\TrendingController@cartoonRole');
    });

    $api->group(['prefix' => '/admin', 'middleware' => ['jwt.admin']], function ($api)
    {
        $api->get('/todo', 'App\Api\V1\Controllers\TrialController@todo');

        $api->group(['prefix' => '/stats'], function ($api)
        {
            $api->get('/realtime', 'App\Api\V1\Controllers\StatsController@realtime');

            $api->get('/timeline', 'App\Api\V1\Controllers\StatsController@timeline');
        });

        $api->group(['prefix' => '/search'], function ($api)
        {
            $api->get('/user', 'App\Api\V1\Controllers\SearchController@getUserInfo');
        });

        $api->group(['prefix' => '/bangumi'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\BangumiController@adminList');

            $api->get('/info', 'App\Api\V1\Controllers\BangumiController@getAdminBangumiInfo');

            $api->post('/create', 'App\Api\V1\Controllers\BangumiController@create');

            $api->post('/edit', 'App\Api\V1\Controllers\BangumiController@edit');

            $api->post('/release', 'App\Api\V1\Controllers\BangumiController@updateBangumiRelease');

            $api->post('/delete', 'App\Api\V1\Controllers\BangumiController@deleteBangumi');

            $api->group(['prefix' => '/manager'], function ($api)
            {
                $api->post('/set', 'App\Api\V1\Controllers\BangumiController@setManager');

                $api->post('/remove', 'App\Api\V1\Controllers\BangumiController@removeManager');

                $api->post('/upgrade', 'App\Api\V1\Controllers\BangumiController@upgradeManager');

                $api->post('/downgrade', 'App\Api\V1\Controllers\BangumiController@downgradeManager');
            });
        });

        $api->group(['prefix' => '/banner'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\ImageController@getIndexBanners');

            $api->post('/upload', 'App\Api\V1\Controllers\ImageController@uploadIndexBanner');

            $api->post('/toggle_use', 'App\Api\V1\Controllers\ImageController@toggleIndexBanner');

            $api->post('/edit', 'App\Api\V1\Controllers\ImageController@editIndexBanner');
        });

        $api->group(['prefix' => '/tag'], function ($api)
        {
            $api->get('/all', 'App\Api\V1\Controllers\TagController@all');

            $api->post('/edit', 'App\Api\V1\Controllers\TagController@edit');

            $api->post('/create', 'App\Api\V1\Controllers\TagController@create');
        });

        $api->group(['prefix' => '/cartoon'], function ($api)
        {
            $api->get('/bangumis', 'App\Api\V1\Controllers\CartoonController@bangumis');

            $api->get('/list_of_bangumi', 'App\Api\V1\Controllers\CartoonController@listOfBangumi');

            $api->post('/sort', 'App\Api\V1\Controllers\CartoonController@sortOfBangumi');

            $api->post('/edit', 'App\Api\V1\Controllers\CartoonController@edit');
        });

        $api->group(['prefix' => '/video'], function ($api)
        {
            $api->get('/bangumis', 'App\Api\V1\Controllers\VideoController@bangumis');

            $api->get('/trending', 'App\Api\V1\Controllers\VideoController@playTrending');

            $api->post('/edit', 'App\Api\V1\Controllers\VideoController@edit');

            $api->post('/save', 'App\Api\V1\Controllers\VideoController@save');

            $api->post('/delete', 'App\Api\V1\Controllers\VideoController@delete');
        });

        $api->group(['prefix' => '/cartoon_role'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\CartoonRoleController@adminShow');

            $api->post('/edit', 'App\Api\V1\Controllers\CartoonRoleController@edit');

            $api->post('/create', 'App\Api\V1\Controllers\CartoonRoleController@create');
        });

        $api->group(['prefix' => '/user'], function ($api)
        {
            $api->group(['prefix' => '/faker'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\UserController@fakers');

                $api->post('/reborn', 'App\Api\V1\Controllers\UserController@fakerReborn');

                $api->post('/create', 'App\Api\V1\Controllers\DoorController@createFaker');
            });

            $api->group(['prefix' => '/feedback'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\UserController@feedbackList');

                $api->post('/read', 'App\Api\V1\Controllers\UserController@readFeedback');
            });

            $api->post('/add_to_trial', 'App\Api\V1\Controllers\UserController@addUserToTrial');

            $api->post('/block', 'App\Api\V1\Controllers\UserController@blockUser');

            $api->post('/recover', 'App\Api\V1\Controllers\UserController@recoverUser');

            $api->get('/dalao', 'App\Api\V1\Controllers\UserController@coinDescList');
        });

        $api->group(['prefix' => '/console'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\UserController@adminUsers');

            $api->post('/remove', 'App\Api\V1\Controllers\UserController@removeAdmin');

            $api->post('/add', 'App\Api\V1\Controllers\UserController@addAdmin');
        });

        $api->group(['prefix' => '/trial'], function ($api)
        {
            $api->group(['prefix' => '/words'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\TrialController@words');

                $api->post('/delete', 'App\Api\V1\Controllers\TrialController@deleteWords');

                $api->post('/add', 'App\Api\V1\Controllers\TrialController@addWords');
            });

            $api->group(['prefix' => '/test'], function ($api)
            {
                $api->get('/image', 'App\Api\V1\Controllers\TrialController@imageTest');

                $api->get('/text', 'App\Api\V1\Controllers\TrialController@textTest');
            });

            $api->group(['prefix' => '/user'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\UserController@trials');

                $api->post('/delete_info', 'App\Api\V1\Controllers\UserController@deleteUserInfo');

                $api->post('/pass', 'App\Api\V1\Controllers\UserController@passUser');
            });

            $api->group(['prefix' => '/image'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\ImageController@trialList');

                $api->post('/delete', 'App\Api\V1\Controllers\ImageController@trialDelete');

                $api->post('/pass', 'App\Api\V1\Controllers\ImageController@trialPass');
            });

            $api->group(['prefix' => '/comment'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\CommentController@trialList');

                $api->post('/delete', 'App\Api\V1\Controllers\CommentController@trialDelete');

                $api->post('/pass', 'App\Api\V1\Controllers\CommentController@trialPass');
            });

            $api->group(['prefix' => '/bangumi'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\BangumiController@trialList');

                $api->post('/pass', 'App\Api\V1\Controllers\BangumiController@trialPass');
            });

            $api->group(['prefix' => '/post'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\PostController@trialList');

                $api->post('/delete_image', 'App\Api\V1\Controllers\PostController@deletePostImage');

                $api->post('/delete', 'App\Api\V1\Controllers\PostController@trialDelete');

                $api->post('/pass', 'App\Api\V1\Controllers\PostController@trialPass');
            });

            $api->group(['prefix' => '/cartoon_role'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\CartoonRoleController@trialList');

                $api->post('/ban', 'App\Api\V1\Controllers\CartoonRoleController@trialBan');

                $api->post('/pass', 'App\Api\V1\Controllers\CartoonRoleController@trialPass');
            });
        });
    });
});