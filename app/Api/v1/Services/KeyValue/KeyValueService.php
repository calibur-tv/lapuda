<?php

namespace App\Api\V1\Services\KeyValue;

use App\Api\V1\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/11/13
 * Time: 上午8:29
 */
class KeyValueService
{
    protected $table;

    public function __construct($table_prefix)
    {
        $this->table = $table_prefix . '_key_value';
    }

    public function get($id)
    {
        $repository = new Repository();

        return $repository->RedisItem($this->table . '_' . $id, function () use ($id)
        {
            return DB
                ::table($this->table)
                ->where('model_id', $id)
                ->pluck('value')
                ->first();
        });
    }

    public function set($id, $score = 1)
    {
        $key = $this->table . '_' . $id;
        // key 存在则认为数据库存在
        if (Redis::EXISTS($key))
        {
            DB
                ::table($this->table)
                ->where('model_id', $id)
                ->increment('value', $score);

            Redis::INCRBYFLOAT($key, $score);
        }
        else
        {
            // 如果 key 不存在
            // 这里可以优化，用 updateOrCreate ?
            $data = DB
                ::table($this->table)
                ->firstOrCreate([
                    'model_id' => $id
                ]);

            DB
                ::table($this->table)
                ->where('id', $data['id'])
                ->increment('value', $score);
        }

        return;
    }
}