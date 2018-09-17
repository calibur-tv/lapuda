<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/2
 * Time: 下午9:41
 */

namespace App\Api\V1\Services\Toggle\Image;


use App\Api\V1\Services\Toggle\Base\RewardService;

class ImageRewardService extends RewardService
{
    public function __construct()
    {
        parent::__construct('image_reward', 10);
    }
}