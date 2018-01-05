<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午9:21
 */

namespace App\Api\V1\Transformers;


class BangumiTransformer
{
    public function item($bangumi)
    {
        return [
            'id' => (int)$bangumi['id'],
            'name' => $bangumi['name'],
            'avatar' => $bangumi['avatar']
        ];
    }
}