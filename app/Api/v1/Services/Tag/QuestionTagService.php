<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/8
 * Time: 上午9:12
 */

namespace App\Api\V1\Services\Tag;


use App\Api\V1\Services\Tag\Base\TagService;

class QuestionTagService extends TagService
{
    public function __construct()
    {
        parent::__construct('bangumis', 'question_tag_relations', 5);
    }
}