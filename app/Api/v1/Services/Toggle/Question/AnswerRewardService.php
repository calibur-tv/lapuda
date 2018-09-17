<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/23
 * Time: 下午10:41
 */

namespace App\Api\V1\Services\Toggle\Question;


use App\Api\V1\Services\Toggle\Base\RewardService;

class AnswerRewardService extends RewardService
{
    public function __construct()
    {
        parent::__construct('answer_reward', 12);
    }
}