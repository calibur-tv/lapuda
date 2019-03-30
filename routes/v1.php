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
        $api->get('/data', 'App\Api\V1\Controllers\DoorController@pageData');

        $api->post('/message', 'App\Api\V1\Controllers\DoorController@sendMessage')->middleware(['geetest']);

        $api->post('/register', 'App\Api\V1\Controllers\DoorController@register');

        $api->post('/bind_phone', 'App\Api\V1\Controllers\DoorController@bindPhone');

        $api->post('/login', 'App\Api\V1\Controllers\DoorController@login')->middleware(['geetest']);
        $api->put('/login', 'App\Api\V1\Controllers\DoorController@login')->middleware(['geetest']);

        $api->post('/wechat_mini_app_login', 'App\Api\V1\Controllers\DoorController@wechatMiniAppLogin');

        $api->post('/wechat_mini_app_get_token', 'App\Api\V1\Controllers\DoorController@wechatMiniAppToken');

        $api->post('/refresh', 'App\Api\V1\Controllers\DoorController@refreshUser')->middleware(['jwt.refresh']);

        $api->post('/refresh_token', 'App\Api\V1\Controllers\DoorController@refreshJwtToken')->middleware(['jwt.refresh']);

        $api->post('/current_user', 'App\Api\V1\Controllers\DoorController@currentUser')->middleware(['jwt.auth']);

        $api->post('/reset', 'App\Api\V1\Controllers\DoorController@resetPassword');

        $api->post('/logout', 'App\Api\V1\Controllers\DoorController@logout')->middleware(['jwt.auth']);

        $api->group(['prefix' => '/oauth2'], function ($api)
        {
            $api->post('/qq', 'App\Api\V1\Controllers\DoorController@qqAuthRedirect');

            $api->post('/wechat', 'App\Api\V1\Controllers\DoorController@wechatAuthRedirect');
        });
    });

    $api->group(['prefix' => '/app'], function ($api)
    {
        $api->get('/version/check', 'App\Api\V1\Controllers\AppVersionController@check');

        $api->get('/template', 'App\Api\V1\Controllers\AppVersionController@getTemplates');
    });

    $api->group(['prefix' => '/bangumi'], function ($api)
    {
        $api->get('/timeline', 'App\Api\V1\Controllers\BangumiController@timeline');

        $api->get('/seasons', 'App\Api\V1\Controllers\BangumiSeasonController@list');

        $api->get('/released', 'App\Api\V1\Controllers\BangumiSeasonController@released');

        $api->get('/tags', 'App\Api\V1\Controllers\BangumiController@tags');

        $api->get('/category', 'App\Api\V1\Controllers\BangumiController@category');

        $api->get('/recommended', 'App\Api\V1\Controllers\BangumiController@recommendedBangumis');

        $api->get('/hots', 'App\Api\V1\Controllers\BangumiController@hotBangumis');

        $api->group(['prefix' => '/manager', 'middleware' => ['jwt.auth']], function ($api)
        {
            $api->get('/get_info', 'App\Api\V1\Controllers\BangumiController@managerGetInfo');

            $api->get('/list', 'App\Api\V1\Controllers\BangumiController@getManagerList');

            $api->post('/set_manager', 'App\Api\V1\Controllers\BangumiController@setManager');

            $api->post('/remove_manager', 'App\Api\V1\Controllers\BangumiController@removeManager');

            $api->post('/edit_info', 'App\Api\V1\Controllers\BangumiController@managerEditInfo');

            $api->post('/create_season', 'App\Api\V1\Controllers\BangumiController@managerCreateSeason');

            $api->post('/edit_season', 'App\Api\V1\Controllers\BangumiController@managerEditSeason');

            $api->post('/update_videos', 'App\Api\V1\Controllers\BangumiController@managerUpdateVideos');
        });

        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\BangumiController@show');

            $api->get('/videos', 'App\Api\V1\Controllers\BangumiSeasonController@bangumiVideos');

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

            $api->post('/update', 'App\Api\V1\Controllers\VideoController@update')->middleware(['jwt.auth']);
        });

        $api->post('/create', 'App\Api\V1\Controllers\VideoController@create')->middleware(['jwt.auth']);

        $api->post('/playing', 'App\Api\V1\Controllers\VideoController@playing');

        $api->post('/fetch', 'App\Api\V1\Controllers\VideoController@fetchVideoSrc');

        $api->post('/buy', 'App\Api\V1\Controllers\VideoController@buy')->middleware(['jwt.auth']);
    });

    $api->group(['prefix' => '/score'], function ($api)
    {
        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\ScoreController@show')->middleware(['showDelete']);

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
            $api->post('/create', 'App\Api\V1\Controllers\QuestionController@create')->middleware(['geetest', 'jwt.auth', 'throttle:1,3']);

            $api->group(['prefix' => '/{id}'], function ($api)
            {
                $api->get('/show', 'App\Api\V1\Controllers\QuestionController@show')->middleware(['showDelete']);
            });
        });

        $api->group(['prefix' => '/soga'], function ($api)
        {
            $api->post('/create', 'App\Api\V1\Controllers\AnswerController@create')->middleware(['geetest', 'jwt.auth', 'throttle:5,10']);

            $api->get('/drafts', 'App\Api\V1\Controllers\AnswerController@drafts')->middleware(['jwt.auth']);

            $api->group(['prefix' => '/{id}'], function ($api)
            {
                $api->get('/show', 'App\Api\V1\Controllers\AnswerController@show')->middleware(['showDelete']);

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

        $api->group(['prefix' => '/notice'], function ($api)
        {
            $api->get('/show/{id}', 'App\Api\V1\Controllers\NoticeController@show');

            $api->get('/list', 'App\Api\V1\Controllers\NoticeController@list');

            $api->post('/mark', 'App\Api\V1\Controllers\NoticeController@mark')->middleware('jwt.auth');
        });

        $api->group(['prefix' => '/badge'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\UserBadgeController@userBadgeList');

            $api->get('/item', 'App\Api\V1\Controllers\UserBadgeController@show');
        });

        $api->post('/daySign', 'App\Api\V1\Controllers\UserController@daySign')->middleware('jwt.auth');

        $api->post('/feedback', 'App\Api\V1\Controllers\UserController@feedback');

        $api->get('/transactions', 'App\Api\V1\Controllers\UserController@transactions')->middleware('jwt.auth');

        $api->get('/recommended', 'App\Api\V1\Controllers\UserController@recommendedUsers');

        $api->get('/bookmarks', 'App\Api\V1\Controllers\UserController@bookmarks')->middleware('jwt.auth');

        $api->get('/invite/list', 'App\Api\V1\Controllers\UserController@userInviteList');

        $api->get('/invite/users', 'App\Api\V1\Controllers\UserController@userInviteUsers');

        $api->get('/card', 'App\Api\V1\Controllers\UserController@userCard');

        $api->group(['prefix' => '/{zone}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\UserController@show')->middleware(['showDelete']);

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
        $api->get('/tags', 'App\Api\V1\Controllers\PostController@tags');

        $api->post('/create', 'App\Api\V1\Controllers\PostController@create')->middleware(['jwt.auth', 'geetest', 'throttle:10,30']);

        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\PostController@show')->middleware(['showDelete']);

            $api->get('/show_cache', 'App\Api\V1\Controllers\PostController@show_cache')->middleware(['showDelete']);

            $api->get('/show_meta', 'App\Api\V1\Controllers\PostController@show_meta');

            $api->get('/get_preview_images', 'App\Api\V1\Controllers\PostController@get_preview_images');

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
            $api->get('/item', 'App\Api\V1\Controllers\CommentController@mainItem');

            $api->get('/list', 'App\Api\V1\Controllers\CommentController@mainList');

            $api->post('/create', 'App\Api\V1\Controllers\CommentController@create')->middleware(['jwt.auth', 'throttle:6,1']);

            $api->post('/reply', 'App\Api\V1\Controllers\CommentController@reply')->middleware(['jwt.auth', 'throttle:10,1']);

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
            $api->get('/show', 'App\Api\V1\Controllers\ImageController@show')->middleware(['showDelete']);
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
            $api->post('/upload', 'App\Api\V1\Controllers\ImageController@uploadSingleImage')->middleware(['geetest', 'throttle:1,1']);

            $api->post('/edit', 'App\Api\V1\Controllers\ImageController@editSingleImage');
        });

        $api->group(['prefix' => '/album', 'middleware' => ['jwt.auth']], function ($api)
        {
            $api->post('/upload', 'App\Api\V1\Controllers\ImageController@uploadAlbumImages');

            $api->post('/create', 'App\Api\V1\Controllers\ImageController@createAlbum')->middleware(['throttle:3,1']);

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

            $api->post('/user_create', 'App\Api\V1\Controllers\CartoonRoleController@publicCreate');
        });

        $api->group(['prefix' => '/{id}'], function ($api)
        {
            $api->get('/show', 'App\Api\V1\Controllers\CartoonRoleController@show');

            $api->get('/stock_show', 'App\Api\V1\Controllers\CartoonRoleController@stockShow');

            $api->get('/deal_show', 'App\Api\V1\Controllers\CartoonRoleController@dealShow');

            $api->get('/stock_chart', 'App\Api\V1\Controllers\CartoonRoleController@stochChart');

            $api->get('/fans', 'App\Api\V1\Controllers\CartoonRoleController@fans');

            $api->get('/owners', 'App\Api\V1\Controllers\CartoonRoleController@owners');
            $api->post('/owners', 'App\Api\V1\Controllers\CartoonRoleController@owners');

            $api->post('/star', 'App\Api\V1\Controllers\CartoonRoleController@star')->middleware(['jwt.auth']);

            $api->post('/buy_stock', 'App\Api\V1\Controllers\CartoonRoleController@buyStock')->middleware(['jwt.auth']);

            $api->get('/get_idol_deal', 'App\Api\V1\Controllers\CartoonRoleController@getMyIdolDeal')->middleware(['jwt.auth']);
        });

        $api->get('/products', 'App\Api\V1\Controllers\CartoonRoleController@products');

        $api->get('/get_idol_request_list', 'App\Api\V1\Controllers\CartoonRoleController@get_idol_request_list');

        $api->get('/get_mine_product_orders', 'App\Api\V1\Controllers\CartoonRoleController@get_mine_product_orders')->middleware(['jwt.auth']);

        $api->get('/can_use_income', 'App\Api\V1\Controllers\CartoonRoleController@can_use_income')->middleware(['jwt.auth']);

        $api->get('/get_my_product_request_list', 'App\Api\V1\Controllers\CartoonRoleController@get_my_product_request_list')->middleware(['jwt.auth']);

        $api->post('/create_buy_request', 'App\Api\V1\Controllers\CartoonRoleController@create_buy_request')->middleware(['jwt.auth']);

        $api->post('/delete_buy_request', 'App\Api\V1\Controllers\CartoonRoleController@delete_buy_request')->middleware(['jwt.auth']);

        $api->post('/over_buy_request', 'App\Api\V1\Controllers\CartoonRoleController@over_buy_request')->middleware(['jwt.auth']);

        $api->post('/check_product_request', 'App\Api\V1\Controllers\CartoonRoleController@check_product_request')->middleware(['jwt.auth']);

        $api->get('/get_idol_days_chart', 'App\Api\V1\Controllers\CartoonRoleController@idolSomeDayStockChartData');

        $api->get('/deal_list', 'App\Api\V1\Controllers\CartoonRoleController@getDealList');
        $api->post('/deal_list', 'App\Api\V1\Controllers\CartoonRoleController@getDealList');

        $api->get('/my_deal', 'App\Api\V1\Controllers\CartoonRoleController@myDeal')->middleware(['jwt.auth']);

        $api->get('/get_user_deal_list', 'App\Api\V1\Controllers\CartoonRoleController@getUserDealList');

        $api->get('/stock_meta', 'App\Api\V1\Controllers\CartoonRoleController@stockMeta');

        $api->get('/recent_buy', 'App\Api\V1\Controllers\CartoonRoleController@recentBuyList');

        $api->get('/recent_deal', 'App\Api\V1\Controllers\CartoonRoleController@recentDealList');

        $api->get('/deal_exchange_record', 'App\Api\V1\Controllers\CartoonRoleController@getDealExchangeRecord');

        $api->get('/market_price_draft_list', 'App\Api\V1\Controllers\CartoonRoleController@getIdolMarketPriceDraftList');

        $api->get('/user_draft_work', 'App\Api\V1\Controllers\CartoonRoleController@getMyTodoWork')->middleware(['jwt.auth']);

        $api->post('/change_idol_profile', 'App\Api\V1\Controllers\CartoonRoleController@changeIdolProfile')->middleware(['jwt.auth']);

        $api->post('/create_market_price_draft', 'App\Api\V1\Controllers\CartoonRoleController@createIdolMarketPriceDraft')->middleware(['jwt.auth']);

        $api->post('/delete_market_price_draft', 'App\Api\V1\Controllers\CartoonRoleController@deleteIdolMarketPriceDraft')->middleware(['jwt.auth']);

        $api->post('/vote_market_price_draft', 'App\Api\V1\Controllers\CartoonRoleController@voteIdolMarketPriceDraft')->middleware(['jwt.auth']);

        $api->post('/create_deal', 'App\Api\V1\Controllers\CartoonRoleController@createDeal')->middleware(['jwt.auth']);

        $api->post('/delete_deal', 'App\Api\V1\Controllers\CartoonRoleController@deleteDeal')->middleware(['jwt.auth']);

        $api->post('/make_deal', 'App\Api\V1\Controllers\CartoonRoleController@makeDeal')->middleware(['jwt.auth']);

        $api->group(['prefix' => '/list'], function ($api)
        {
            $api->get('/idols', 'App\Api\V1\Controllers\CartoonRoleController@getIdolList');
            $api->post('/idols', 'App\Api\V1\Controllers\CartoonRoleController@getIdolList');

            $api->get('/today', 'App\Api\V1\Controllers\CartoonRoleController@todayActivity');

            $api->get('/dalao', 'App\Api\V1\Controllers\CartoonRoleController@dalaoUsers');

            $api->get('/newbie', 'App\Api\V1\Controllers\CartoonRoleController@newbieUsers');
        });
    });

    $api->group(['prefix' => '/flow'], function ($api)
    {
        $api->get('/list', 'App\Api\V1\Controllers\TrendingController@flowlist');

        $api->get('/mixin', 'App\Api\V1\Controllers\TrendingController@mixinFlow');

        $api->post('/list', 'App\Api\V1\Controllers\TrendingController@flowlist');

        $api->get('/meta', 'App\Api\V1\Controllers\TrendingController@meta');

        $api->get('/recommended', 'App\Api\V1\Controllers\TrendingController@recommended');
    });

    $api->group(['prefix' => '/toggle'], function ($api)
    {
        $api->group(['middleware' => ['jwt.auth', 'throttle:10,1']], function ($api)
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

    $api->group(['prefix' => '/cm'], function ($api)
    {
        $api->group(['prefix' => '/loop'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\CmController@cmLoop');

            $api->post('/view', 'App\Api\V1\Controllers\CmController@cmView');

            $api->post('/click', 'App\Api\V1\Controllers\CmController@cmClick');
        });

        $api->group(['prefix' => '/recommended'], function ($api)
        {
            $api->post('/set', 'App\Api\V1\Controllers\CmController@setRecommendedBangumi');

            $api->post('/del', 'App\Api\V1\Controllers\CmController@delRecommendedBangumi');
        });
    });

    $api->group(['prefix' => '/admin', 'middleware' => ['jwt.admin']], function ($api)
    {
        $api->get('/todo', 'App\Api\V1\Controllers\TrialController@todo');

        $api->get('/console_todo', 'App\Api\V1\Controllers\TrialController@consoleTodo');

        $api->get('/trial_todo', 'App\Api\V1\Controllers\TrialController@trialTodo');

        $api->group(['prefix' => '/stats'], function ($api)
        {
            $api->get('/realtime', 'App\Api\V1\Controllers\StatsController@realtime');

            $api->get('/timeline', 'App\Api\V1\Controllers\StatsController@timeline');
        });

        $api->group(['prefix' => '/cm'], function ($api)
        {
            $api->group(['prefix' => '/loop'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\LooperController@list');

                $api->post('/update', 'App\Api\V1\Controllers\LooperController@update');

                $api->post('/sort', 'App\Api\V1\Controllers\LooperController@sort');

                $api->post('/delete', 'App\Api\V1\Controllers\LooperController@delete');

                $api->post('/add', 'App\Api\V1\Controllers\LooperController@add');
            });

            $api->group(['prefix' => '/notice'], function ($api)
            {
                $api->post('/create', 'App\Api\V1\Controllers\NoticeController@create');

                $api->post('/update', 'App\Api\V1\Controllers\NoticeController@update');

                $api->post('/delete', 'App\Api\V1\Controllers\NoticeController@delete');
            });
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

            $api->post('/edit_season', 'App\Api\V1\Controllers\BangumiSeasonController@edit');

            $api->post('/create_season', 'App\Api\V1\Controllers\BangumiSeasonController@create');

            $api->post('/release', 'App\Api\V1\Controllers\BangumiController@updateBangumiRelease');

            $api->post('/delete', 'App\Api\V1\Controllers\BangumiController@deleteBangumi');

            $api->group(['prefix' => '/manager'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\BangumiController@managers');

                $api->post('/set', 'App\Api\V1\Controllers\BangumiController@setManager');

                $api->post('/remove', 'App\Api\V1\Controllers\BangumiController@removeManager');

                $api->post('/upgrade', 'App\Api\V1\Controllers\BangumiController@upgradeManager');

                $api->post('/downgrade', 'App\Api\V1\Controllers\BangumiController@downgradeManager');
            });
        });

        $api->group(['prefix' => '/season'], function ($api)
        {
            $api->get('/search_all', 'App\Api\V1\Controllers\BangumiSeasonController@all');

            $api->post('/update_key', 'App\Api\V1\Controllers\BangumiSeasonController@update_season_key');
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

        $api->group(['prefix' => '/badge'], function ($api)
        {
            $api->get('/all', 'App\Api\V1\Controllers\UserBadgeController@allBadge');

            $api->get('/users', 'App\Api\V1\Controllers\UserBadgeController@badgeUsers');

            $api->post('/create', 'App\Api\V1\Controllers\UserBadgeController@createBadge');

            $api->post('/update', 'App\Api\V1\Controllers\UserBadgeController@updateBadge');

            $api->post('/delete', 'App\Api\V1\Controllers\UserBadgeController@deleteBadge');

            $api->post('/set', 'App\Api\V1\Controllers\UserBadgeController@setUserBadge');

            $api->post('/batch_set', 'App\Api\V1\Controllers\UserBadgeController@batchSetUserBadge');

            $api->post('/remove', 'App\Api\V1\Controllers\UserBadgeController@removeUserBadge');
        });

        $api->group(['prefix' => '/cartoon'], function ($api)
        {
            $api->get('/bangumis', 'App\Api\V1\Controllers\CartoonController@bangumis');

            $api->post('/edit', 'App\Api\V1\Controllers\CartoonController@edit');
        });

        $api->group(['prefix' => '/video'], function ($api)
        {
            $api->get('/bangumis', 'App\Api\V1\Controllers\VideoController@bangumis');

            $api->get('/trending', 'App\Api\V1\Controllers\VideoController@playTrending');

            $api->get('/baidu_list', 'App\Api\V1\Controllers\VideoController@baiduVideos');

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

            $api->group(['prefix' => '/banned'], function ($api)
            {
                $api->get('/freezeUsers', 'App\Api\V1\Controllers\UserController@freezeUserList');

                $api->get('/mutil_users', 'App\Api\V1\Controllers\UserController@mutilUsers');

                $api->post('/freeze', 'App\Api\V1\Controllers\UserController@freezeUser');

                $api->post('/free', 'App\Api\V1\Controllers\UserController@freeUser');
            });

            $api->post('/add_to_trial', 'App\Api\V1\Controllers\UserController@addUserToTrial');

            $api->post('/transactions', 'App\Api\V1\Controllers\UserController@getUserCoinTransactions');

            $api->post('/withdrawal', 'App\Api\V1\Controllers\UserController@withdrawal');

            $api->post('/give_user_money', 'App\Api\V1\Controllers\UserController@giveUserMoney');

            $api->get('/dalao', 'App\Api\V1\Controllers\UserController@coinDescList');

            $api->get('/matrix', 'App\Api\V1\Controllers\UserController@matrixUsers');

            $api->get('/delete_ip_report', 'App\Api\V1\Controllers\UserController@clearNoOneIpAddress');
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

                $api->post('/text', 'App\Api\V1\Controllers\TrialController@textTest');
            });

            $api->group(['prefix' => '/user'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\UserController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\UserController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\UserController@pass');

                $api->post('/recover', 'App\Api\V1\Controllers\UserController@recover');

                $api->post('/delete_info', 'App\Api\V1\Controllers\UserController@deleteUserInfo');

                $api->post('/banned_user_cherr', 'App\Api\V1\Controllers\UserController@bannedUserCherr');
            });

            $api->group(['prefix' => '/image'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\ImageController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\ImageController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\ImageController@pass');

                $api->post('/approve', 'App\Api\V1\Controllers\ImageController@approve');

                $api->post('/reject', 'App\Api\V1\Controllers\ImageController@reject');

                $api->post('/delete_poster', 'App\Api\V1\Controllers\ImageController@deleteAlbumPoster');
            });

            $api->group(['prefix' => '/comment'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\CommentController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\CommentController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\CommentController@pass');

                $api->post('/approve', 'App\Api\V1\Controllers\CommentController@approve');

                $api->post('/reject', 'App\Api\V1\Controllers\CommentController@reject');

                $api->post('/batch_ban', 'App\Api\V1\Controllers\CommentController@batchBan');

                $api->post('/batch_pass', 'App\Api\V1\Controllers\CommentController@batchPass');
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

                $api->post('/approve', 'App\Api\V1\Controllers\PostController@approve');

                $api->post('/reject', 'App\Api\V1\Controllers\PostController@reject');

                $api->post('/delete_image', 'App\Api\V1\Controllers\PostController@deletePostImage');

                $api->post('/delete_title', 'App\Api\V1\Controllers\PostController@deletePostTitle');

                $api->post('/change_area', 'App\Api\V1\Controllers\PostController@changePostArea');

                $api->get('/self_flow_status', 'App\Api\V1\Controllers\PostController@selfPostFlowStatus');

                $api->get('/get_flow_status', 'App\Api\V1\Controllers\PostController@getPostFlowStatus');

                $api->post('/batch_change_flow_status', 'App\Api\V1\Controllers\PostController@batchChangeFlowStatus');

                $api->post('/change_flow_status', 'App\Api\V1\Controllers\PostController@changeFlowStatus');
            });

            $api->group(['prefix' => '/score'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\ScoreController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\ScoreController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\ScoreController@pass');

                $api->post('/approve', 'App\Api\V1\Controllers\ScoreController@approve');

                $api->post('/reject', 'App\Api\V1\Controllers\ScoreController@reject');
            });

            $api->group(['prefix' => '/question'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\QuestionController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\QuestionController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\QuestionController@pass');

                $api->post('/approve', 'App\Api\V1\Controllers\QuestionController@approve');

                $api->post('/reject', 'App\Api\V1\Controllers\QuestionController@reject');
            });

            $api->group(['prefix' => '/answer'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\AnswerController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\AnswerController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\AnswerController@pass');

                $api->post('/approve', 'App\Api\V1\Controllers\AnswerController@approve');

                $api->post('/reject', 'App\Api\V1\Controllers\AnswerController@reject');
            });

            $api->group(['prefix' => '/cartoon_role'], function ($api)
            {
                $api->get('/list', 'App\Api\V1\Controllers\CartoonRoleController@trials');

                $api->post('/ban', 'App\Api\V1\Controllers\CartoonRoleController@ban');

                $api->post('/pass', 'App\Api\V1\Controllers\CartoonRoleController@pass');
            });
        });

        $api->group(['prefix' => '/report'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\ReportController@list');

            $api->get('/item', 'App\Api\V1\Controllers\ReportController@item');

            $api->post('/remove', 'App\Api\V1\Controllers\ReportController@remove');
        });

        $api->group(['prefix' => '/ip_blocker'], function ($api)
        {
            $api->get('/list', 'App\Api\V1\Controllers\UserController@getBlockedUserIpAddress');

            $api->post('/block', 'App\Api\V1\Controllers\UserController@blockUserByIp');

            $api->post('/recover', 'App\Api\V1\Controllers\UserController@recoverUserIp');
        });

        $api->group(['prefix' => '/app'], function ($api)
        {
            $api->group(['prefix' => '/version'], function ($api)
            {
                $api->post('/create', 'App\Api\V1\Controllers\AppVersionController@create');

                $api->post('/delete', 'App\Api\V1\Controllers\AppVersionController@delete');

                $api->post('/toggleForce', 'App\Api\V1\Controllers\AppVersionController@toggleForce');

                $api->get('/list', 'App\Api\V1\Controllers\AppVersionController@list');

                $api->get('/uptoken', 'App\Api\V1\Controllers\AppVersionController@uploadAppToken');
            });

            $api->post('/setTemplates', 'App\Api\V1\Controllers\AppVersionController@setTemplates');
        });

        $api->group(['prefix' => '/web'], function ($api)
        {
            $api->group(['prefix' => '/friend_link'], function ($api)
            {
                $api->post('/append', 'App\Api\V1\Controllers\FriendLinkController@append');

                $api->post('/remove', 'App\Api\V1\Controllers\FriendLinkController@remove');

                $api->get('/list', 'App\Api\V1\Controllers\FriendLinkController@list');
            });
        });

        $api->group(['prefix' => '/role'], function ($api)
        {
            $api->post('/set_role', 'App\Api\V1\Controllers\UserRoleController@setRole');

            $api->post('/delete_role', 'App\Api\V1\Controllers\UserRoleController@deleteRole');

            $api->post('/clear_role', 'App\Api\V1\Controllers\UserRoleController@clearRole');

            $api->post('/create_role', 'App\Api\V1\Controllers\UserRoleController@createRole');

            $api->post('/update_role', 'App\Api\V1\Controllers\UserRoleController@updateRole');

            $api->post('/destroy_role', 'App\Api\V1\Controllers\UserRoleController@destroyRole');

            $api->get('/user_roles', 'App\Api\V1\Controllers\UserRoleController@userRoles');

            $api->get('/role_users', 'App\Api\V1\Controllers\UserRoleController@roleUsers');

            $api->get('/all', 'App\Api\V1\Controllers\UserRoleController@all');
        });
    });
});