<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/2
 * Time: 下午9:42
 */

namespace App\Api\V1\Services\Toggle\Video;


use App\Api\V1\Services\Toggle\Base\RewardService;

class VideoRewardService extends RewardService
{
    public function __construct()
    {
        parent::__construct('video_reward', 14);
    }
}