<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 下午1:10
 */

namespace App\Api\V1\Services\Owner;


use App\Api\V1\Services\Owner\Base\OwnerService;

class QuestionLog extends OwnerService
{
    public function __construct()
    {
        parent::__construct('question_changes');
    }
}