<?php

namespace App\Api\V1\Services\Toggle\Question;

use App\Api\V1\Services\Owner\QuestionLog;
use App\Api\V1\Services\Toggle\ToggleService;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午10:28
 */
class QuestionFollowService extends ToggleService
{
    public function __construct()
    {
        parent::__construct('question_follows');
    }

    public function beforeHook($bangumiId, $userId)
    {
        $questionLog = new QuestionLog();
        if ($questionLog->isLeader($bangumiId, $userId))
        {
            return '提问者不能取消关注';
        }

        return true;
    }
}