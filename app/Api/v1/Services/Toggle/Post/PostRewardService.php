<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/2
 * Time: 下午9:42
 */

namespace App\Api\V1\Services\Toggle\Post;


use App\Api\V1\Services\Toggle\Base\RewardService;

class PostRewardService extends RewardService
{
    public function __construct()
    {
        parent::__construct('post_reward', 9);
    }
}