<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/8
 * Time: 上午9:12
 */

namespace App\Api\V1\Services\Tag;


class BangumiTagService extends TagService
{
    public function __construct()
    {
        parent::__construct('bangumi_tags', 'bangumi_tag_relations', 10);
    }
}