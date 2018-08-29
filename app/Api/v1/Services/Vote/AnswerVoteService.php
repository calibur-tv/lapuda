<?php

namespace App\Api\V1\Services\Vote;

use App\Api\V1\Services\Vote\Base\BanPickService;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 下午12:11
 */
class AnswerVoteService extends BanPickService
{
    public function __construct()
    {
        parent::__construct('answer_votes');
    }
}