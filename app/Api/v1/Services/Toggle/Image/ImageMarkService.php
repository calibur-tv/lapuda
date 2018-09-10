<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/2
 * Time: 下午9:30
 */

namespace App\Api\V1\Services\Toggle\Image;

use App\Api\V1\Services\Toggle\ToggleService;

class ImageMarkService extends ToggleService
{
    public function __construct()
    {
        parent::__construct('image_mark');
    }
}