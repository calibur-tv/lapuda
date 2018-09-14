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

            $api->get('/posts/top', 'App\Api\V1\Controllers\PostController@bangumiTops');

            $api->get('/cartoon', 'App\Api\V1\Controllers\ImageController@cartoon');

            $api->post('/edit', 'App\Api\V1\Controllers\BangumiController@editBangumiInfo')->middleware(['jwt.auth']);
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

    $api->group(['prefix' => '/score'], function ($api)
    {
        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\ScoreController@show');

            $api->get('/edit', 'App\Api\V1\Controllers\ScoreController@edit')->middleware(['jwt.auth']);
        });

        $api->get('/bangumis', 'App\Api\V1\Controllers\ScoreController@bangumis');

        $api->get('/drafts', 'App\Api\V1\Controllers\ScoreController@drafts')->middleware(['jwt.auth']);

        $api->get('/check', 'App\Api\V1\Controllers\ScoreController@check')->middleware(['jwt.auth']);

        $api->post('/delete', 'App\Api\V1\Controllers\ScoreController@delete')->middleware(['jwt.auth']);

        $api->post('/update', 'App\Api\V1\Controllers\ScoreController@update')->middleware(['jwt.auth']);

        $api->post('/create', 'App\Api\V1\Controllers\ScoreController@create')->middleware(['jwt.auth', 'geetest']);
    });

    $api->group(['prefix' => '/question'], function ($api)
    {
        $api->group(['prefix' => '/qaq'], function ($api)
        {
            $api->post('/create', 'App\Api\V1\Controllers\QuestionController@create')->middleware(['geetest', 'jwt.auth']);

            $api->group(['prefix' => '/{id}'], function ($api)
            {
                $api->get('/show', 'App\Api\V1\Controllers\QuestionController@show');
            });
        });

        $api->group(['prefix' => '/soga'], function ($api)
        {
            $api->post('/create', 'App\Api\V1\Controllers\AnswerController@create')->middleware(['geetest', 'jwt.auth']);

            $api->get('/drafts', 'App\Api\V1\Controllers\AnswerController@drafts')->middleware(['jwt.auth']);

            $api->group(['prefix' => '/{id}'], function ($api)
            {
                $api->get('/show', 'App\Api\V1\Controllers\AnswerController@show');

                $api->get('/resource', 'App\Api\V1\Controllers\AnswerController@editData')->middleware(['jwt.auth']);

                $api->post('/update', 'App\Api\V1\Controllers\AnswerController@update')->middleware(['jwt.auth']);

                $api->post('/delete', 'App\Api\V1\Controllers\AnswerController@delete')->middleware(['jwt.auth']);
            });
        });
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
            });

            $api->group(['prefix' => '/posts'], function ($api)
            {
                $api->get('/reply', 'App\Api\V1\Controllers\UserController@postsOfReply');
            });
        });
    });

    $api->group(['prefix' => '/post'], function ($api)
    {
        $api->post('/create', 'App\Api\V1\Controllers\PostController@create')->middleware(['jwt.auth', 'geetest']);

        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\PostController@show');

            $api->post('/deletePost', 'App\Api\V1\Controllers\PostController@deletePost')->middleware(['jwt.auth']);
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

    $api->group(['prefix' => '/comment'], function ($api)
    {
        $api->group(['prefix' => '/main'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\CommentController@mainList');

            $api->post('/create', 'App\Api\V1\Controllers\CommentController@create')->middleware(['jwt.auth']);

            $api->post('/reply', 'App\Api\V1\Controllers\CommentController@reply')->middleware(['jwt.auth']);

            $api->post('/delete', 'App\Api\V1\Controllers\CommentController@deleteMainComment')->middleware(['jwt.auth']);

            $api->post('/toggleLike', 'App\Api\V1\Controllers\CommentController@toggleLikeMainComment')->middleware(['jwt.auth']);
        });

        $api->group(['prefix' => '/sub'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\CommentController@subList');

            $api->post('/delete', 'App\Api\V1\Controllers\CommentController@deleteSubComment')->middleware(['jwt.auth']);

            $api->post('/toggleLike', 'App\Api\V1\Controllers\CommentController@toggleLikeSubComment')->middleware(['jwt.auth']);
        });
    });

    $api->group(['prefix' => '/image'], function ($api)
    {
        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\ImageController@show');
        });

        $api->get('/banner', 'App\Api\V1\Controllers\ImageController@banner');

        $api->get('/captcha', 'App\Api\V1\Controllers\ImageController@captcha');

        $api->get('/uptoken', 'App\Api\V1\Controllers\ImageController@uptoken')->middleware(['jwt.auth']);

        $api->post('/editAlbum', 'App\Api\V1\Controllers\ImageController@editAlbum')->middleware(['jwt.auth']);

        $api->group(['prefix' => '/album/{id}'], function ($api)
        {
            $api->post('/sort', 'App\Api\V1\Controllers\ImageController@albumSort')->middleware(['jwt.auth']);

            $api->post('/deleteImage', 'App\Api\V1\Controllers\ImageController@deleteAlbumImage')->middleware(['jwt.auth']);
        });

        $api->group(['prefix' => '/single', 'middleware' => ['jwt.auth']], function ($api)
        {
            $api->post('/upload', 'App\Api\V1\Controllers\ImageController@uploadSingleImage')->middleware(['geetest']);

            $api->post('/edit', 'App\Api\V1\Controllers\ImageController@editSingleImage');
        });

        $api->group(['prefix' => '/album', 'middleware' => ['jwt.auth']], function ($api)
        {
            $api->post('/upload', 'App\Api\V1\Controllers\ImageController@uploadAlbumImages');

            $api->post('/create', 'App\Api\V1\Controllers\ImageController@createAlbum');

            $api->post('/edit', 'App\Api\V1\Controllers\ImageController@editAlbum');

            $api->post('/delete', 'App\Api\V1\Controllers\ImageController@deleteAlbum');

            $api->get('/users', 'App\Api\V1\Controllers\ImageController@userAlbums');
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

            $api->get('/fans', 'App\Api\V1\Controllers\CartoonRoleController@fans');

            $api->post('/star', 'App\Api\V1\Controllers\CartoonRoleController@star')->middleware(['jwt.auth']);
        });
    });

    $api->group(['prefix' => '/flow'], function ($api)
    {
        $api->get('/list', 'App\Api\V1\Controllers\TrendingController@flowlist');

        $api->get('/meta', 'App\Api\V1\Controllers\TrendingController@meta');
    });

    $api->group(['prefix' => '/toggle'], function ($api)
    {
        $api->group(['middleware' => ['jwt.auth']], function ($api)
        {
            $api->post('/like', 'App\Api\V1\Controllers\ToggleController@like');

            $api->post('/mark', 'App\Api\V1\Controllers\ToggleController@mark');

            $api->post('/follow', 'App\Api\V1\Controllers\ToggleController@follow');

            $api->post('/reward', 'App\Api\V1\Controllers\ToggleController@reward');

            $api->post('/vote', 'App\Api\V1\Controllers\ToggleController@vote');

            $api->post('/check', 'App\Api\V1\Controllers\ToggleController@mixinCheck');
        });

        $api->get('/users', 'App\Api\V1\Controllers\ToggleController@mixinUsers');
    });

    $api->group(['prefix' => '/report'], function ($api)
    {
        $api->post('/send', 'App\Api\V1\Controllers\ReportController@send');
    });

    $api->group(['prefix' => '/callback'], function ($api)
    {
        $api->group(['prefix' => '/qiniu'], function ($api)
        {
            $api->post('/avthumb', 'App\Api\V1\Controllers\CallbackController@qiniuAvthumb');
        });
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
            $api->get('/user', 'App\Api\V1\Controllers\UserController@getUserInfo');
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

            $api->post('/transactions', 'App\Api\V1\Controllers\UserController@getUserCoinTransactions');

            $api->post('/withdrawal', 'App\Api\V1\Controllers\UserController@withdrawal');

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

                $api->post('/ban', 'App\Api\V1\Controllers\UserController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\UserController@pass');

                $api->post('/recover', 'App\Api\V1\Controllers\UserController@recover');

                $api->post('/delete_info', 'App\Api\V1\Controllers\UserController@deleteUserInfo');
            });

            $api->group(['prefix' => '/image'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\ImageController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\ImageController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\ImageController@pass');
            });

            $api->group(['prefix' => '/comment'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\CommentController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\CommentController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\CommentController@pass');
            });

            $api->group(['prefix' => '/bangumi'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\BangumiController@trials');

                $api->post('/pass', 'App\Api\V1\Controllers\BangumiController@pass');
            });

            $api->group(['prefix' => '/post'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\PostController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\PostController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\PostController@pass');

                $api->post('/delete_image', 'App\Api\V1\Controllers\PostController@deletePostImage');
            });

            $api->group(['prefix' => '/score'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\ScoreController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\ScoreController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\ScoreController@pass');
            });

            $api->group(['prefix' => '/question'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\QuestionController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\QuestionController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\QuestionController@pass');
            });

            $api->group(['prefix' => '/answer'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\AnswerController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\AnswerController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\AnswerController@pass');
            });

            $api->group(['prefix' => '/cartoon_role'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\CartoonRoleController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\CartoonRoleController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\CartoonRoleController@pass');
            });
        });
    });
});