<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/23
 * Time: 下午10:48
 */

namespace App\Api\V1\Services\Counter;


use App\Api\V1\Services\Counter\Base\RelationCounterService;

class QuestionAnswerCounter extends RelationCounterService
{
    public function __construct()
    {
        parent::__construct('question_answers', 'question_id');
    }
}