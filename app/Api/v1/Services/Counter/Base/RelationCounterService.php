<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/9
 * Time: 下午2:33
 */

namespace App\Api\V1\Services\Counter\Base;


use App\Api\V1\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RelationCounterService extends Repository
{
    protected $table;
    protected $field;

    public function __construct($stateTable, $fieldName = 'modal_id')
    {
        $this->table = $stateTable;
        $this->field = $fieldName;
    }

    public function add($id, $num = 1)
    {
        $this->id = $id;
        $cacheKey = $this->cacheKey($id);

        if (Redis::EXISTS($cacheKey))
        {
            Redis::INCRBY($cacheKey, $num);
        }

        return $this->get($id);
    }

    public function get($id)
    {
        return (int)$this->RedisItem($this->cacheKey($id), function () use ($id)
        {
           $count = DB::table($this->table)
               ->where($this->field, $id)
               ->count();

           return $count ? $count : null;
        });
    }

    public function batchGet($list, $key)
    {
        foreach ($list as $i => $item)
        {
            $list[$i][$key] = $this->get($item['id']);
        }

        return $list;
    }

    protected function cacheKey($id)
    {
        return $this->table . '_' . $id . '_total';
    }
}