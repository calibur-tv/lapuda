<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/12
 * Time: 上午7:29
 */
namespace App\Api\V1\Services\Toggle\Score;

use App\Api\V1\Services\Toggle\ToggleService;

class ScoreLikeService extends ToggleService
{
    public function __construct()
    {
        parent::__construct('scores', 'score_like');
    }
}