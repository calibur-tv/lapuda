<?php

namespace App\Api\V1\Services\Toggle\Image;

use App\Api\V1\Services\Toggle\ToggleService;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/3
 * Time: 上午9:31
 */
class ImageLikeService extends ToggleService
{
    public function __construct()
    {
        parent::__construct('images', 'image_likes', true);
    }
}
