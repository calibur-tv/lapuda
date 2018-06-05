<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午3:15
 */

namespace App\Api\V1\Services\Counter\Post;


use App\Api\V1\Services\Counter\CounterService;

class PostViewCounter extends CounterService
{
    public function __construct()
    {
        parent::__construct('posts', 'view_count');
    }
}