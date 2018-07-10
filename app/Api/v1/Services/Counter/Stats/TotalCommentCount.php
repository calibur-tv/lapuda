<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 上午6:44
 */

namespace App\Api\V1\Services\Counter\Stats;


use App\Api\V1\Services\Counter\Base\TotalCounterService;

class TotalCommentCount extends TotalCounterService
{
    public function __construct()
    {
        parent::__construct([
            'post_comments',
            'video_comments',
            'image_comments'
        ], 'comment');
    }
}