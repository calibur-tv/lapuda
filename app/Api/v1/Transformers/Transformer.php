<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午10:53
 */

namespace App\Api\V1\Transformers;


class Transformer
{
    public function transformer($item, $func)
    {
        return fractal([$item], function (array $one) use ($func) {
            return $func($one);
        })->toArray()['data'][0];
    }

    public function collection($list, $func)
    {
        return fractal($list, function (array $item) use ($func) {
            return $func($item);
        })->toArray()['data'];
    }
}