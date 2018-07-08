<?php

namespace App\Api\V1\Services\Tag;

use App\Api\V1\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/8
 * Time: 上午7:45
 */
class TagService extends Repository
{
    protected $tag_table;
    protected $relation_table;
    protected $max_count;
    protected $all_tag_cache_key;

    public function __construct($tagTable, $relationTable, $maxCount = 0)
    {
        $this->tag_table = $tagTable;
        $this->relation_table = $relationTable;
        $this->max_count = $maxCount;
        $this->all_tag_cache_key = $tagTable . '_all';
    }

    public function all()
    {
        return $this->Cache($this->all_tag_cache_key, function ()
        {
            return DB::table($this->tag_table)
                ->select('id', 'name')
                ->get();
        });
    }

    public function tags($modalId)
    {
        $tagIds = $this->getModalTagIds($modalId);

        if (empty($tagIds))
        {
            return [];
        }

        return $this->Cache($this->modalTagsCacheKey($modalId), function () use ($tagIds)
        {
            return DB::table($this->tag_table)
                ->whereIn('id', $tagIds)
                ->select('id', 'name')
                ->get();
        });
    }

    public function append($modalId, $tagId)
    {
        if (!$modalId || !$tagId)
        {
            return '请求参数错误';
        }

        if ($this->contain($modalId, $tagId))
        {
            return true;
        }

        if (!$this->valid($tagId))
        {
            return '这个标签已被移除了';
        }

        if ($this->max_count && $this->max_count - count($this->getModalTagIds($modalId)) <= 1)
        {
            return '最多允许设置' . $this->max_count . '个标签';
        }

        DB::table($this->relation_table)
            ->inset([
                'model_id' => $modalId,
                'tag_id' => $tagId
            ]);

        Redis::DEL($this->modalTagsCacheKey($modalId));

        return true;
    }

    public function remove($modalId, $tagId)
    {
        if (!$modalId || !$tagId)
        {
            return false;
        }

        DB::table($this->relation_table)
            ->whereRaw('model_id = ? and tag_id = ?', [$modalId, $tagId])
            ->delete();

        Redis::DEL($this->modalTagsCacheKey($modalId));

        return true;
    }

    public function update($modalId, $tagIds)
    {
        if (!$modalId)
        {
            return false;
        }

        $hasTagIds = $this->getModalTagIds($modalId);
        if (empty($hasTagIds))
        {
            $appendIds = $tagIds;
            $removeIds = [];
        }
        else if (empty($tagIds))
        {
            $appendIds = [];
            $removeIds = $hasTagIds;
        }
        else
        {
            $appendIds = array_diff(array_unique(array_merge($hasTagIds, $tagIds)), $hasTagIds);
            $removeIds = array_intersect($hasTagIds, $tagIds);
        }

        if ($removeIds)
        {
            DB::table($this->relation_table)
                ->whereIn('id', $removeIds)
                ->delete();
        }

        if ($appendIds)
        {
            $appendTags = [];
            foreach ($appendIds as $tagId)
            {
                $appendTags[] = [
                    'model_id' => $modalId,
                    'tag_id' => $tagId
                ];
            }

            DB::table($this->relation_table)
                ->insert($appendTags);
        }

        Redis::DEL($this->modalTagsCacheKey($modalId));

        return true;
    }

    public function createTag($name)
    {
        $hasTag = DB::table($this->tag_table)
            ->where('name', $name)
            ->count();

        if ($hasTag)
        {
            return false;
        }

        DB::table($this->tag_table)
            ->insert([
                'name' => $name
            ]);

        Redis::DEL($this->all_tag_cache_key);

        return true;
    }

    public function updateTag($tagId, $name)
    {
        $hasTag = DB::table($this->tag_table)
            ->where('name', $name)
            ->count();

        if ($hasTag)
        {
            return false;
        }

        DB::table($this->tag_table)
            ->where('id', $tagId)
            ->update([
                'name' => $name
            ]);

        Redis::DEL($this->all_tag_cache_key);

        return true;
    }

    protected function valid($tagId)
    {
        return (boolean)DB::table($this->tag_table)
            ->where('id', $tagId)
            ->count();
    }

    protected function contain($modalId, $tagId)
    {
        return (int)DB::table($this->relation_table)
            ->whereRaw('model_id = ? and tag_id = ?', [$modalId, $tagId])
            ->count();
    }

    protected function getModalTagIds($modalId)
    {
        if (!$modalId)
        {
            return [];
        }

        return DB::table($this->relation_table)
            ->where('model_id', $modalId)
            ->pluck('tag_id');
    }

    protected function modalTagsCacheKey($modalId)
    {
        return $this->tag_table . '_' . $modalId . '_tags';
    }
}
