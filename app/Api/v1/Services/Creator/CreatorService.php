<?php

namespace App\Api\V1\Services\Creator;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/8
 * Time: 上午9:56
 */
class CreatorService
{
    protected $modal;

    public function __construct($modal)
    {
        $this->modal = $modal;
    }
}