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
    /**
     * 使用场景：关注统计，不需要回写数据到表里，缓存失效后重新通过关联表算出来再写到缓存里
     */
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
           return $this->migration($id);
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

    public function deleteCache($id)
    {
        Redis::DEL($this->cacheKey($id));
    }

    protected function migration($id)
    {
        return DB::table($this->table)
            ->where($this->field, $id)
            ->count();
    }

    protected function cacheKey($id)
    {
        return $this->table . '_' . $id . '_' . $this->field  .'_total';
    }
}