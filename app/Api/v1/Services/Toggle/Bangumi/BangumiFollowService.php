<?php

namespace App\Api\V1\Services\Toggle\Bangumi;

use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\ToggleService;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午10:28
 */
class BangumiFollowService extends ToggleService
{
    public function __construct()
    {
        parent::__construct('bangumis', 'bangumi_follows', true);
    }

    public function beforeHook($bangumiId, $userId)
    {
        $bangumiManager = new BangumiManager();
        if ($bangumiManager->isOwner($bangumiId, $userId))
        {
            return '管理员不能取消关注';
        }

        return true;
    }
}