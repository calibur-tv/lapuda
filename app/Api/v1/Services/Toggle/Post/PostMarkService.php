<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午4:55
 */

namespace App\Api\V1\Services\Toggle\Post;

use App\Api\V1\Services\Toggle\ToggleDoService;

class PostMarkService extends ToggleDoService
{
    public function __construct()
    {
        parent::__construct('posts', 'mark_count', 'post_mark', true);
    }
}