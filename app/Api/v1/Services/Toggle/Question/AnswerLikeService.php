<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/24
 * Time: 下午9:45
 */

namespace App\Api\V1\Services\Toggle\Question;


use App\Api\V1\Services\Toggle\ToggleService;

class AnswerLikeService extends ToggleService
{
    public function __construct()
    {
        parent::__construct('answer_like', true);
    }
}