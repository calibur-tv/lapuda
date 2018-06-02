<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午3:15
 */

namespace App\Api\V1\Services\Counter;


class PostViewCounter extends CounterService
{
    public function __construct($id)
    {
        parent::__construct('posts', 'view_count', $id);
    }
}