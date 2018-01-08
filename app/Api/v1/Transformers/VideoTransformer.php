<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午11:36
 */

namespace App\Api\V1\Transformers;


class VideoTransformer extends Transformer
{
    public function bangumi($videos)
    {
        return $this->collection($videos, function ($video)
        {
            return $video;
        });
    }
}