<?php

namespace App\Api\V1\Services\Tag;

use App\Api\V1\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mews\Purifier\Facades\Purifier;

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
    /**
     * TODO：支持 Ban-Pick Vote
     */
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
                ->orderBy('id', 'DESC')
                ->select('id', 'name')
                ->get()
                ->toArray();
        });
    }

    public function tags($modelId)
    {
        $tagIds = $this->getModalTagIds($modelId);

        if (empty($tagIds))
        {
            return [];
        }

        return $this->Cache($this->modalTagsCacheKey($modelId), function () use ($tagIds)
        {
            return DB::table($this->tag_table)
                ->whereIn('id', $tagIds)
                ->select('id', 'name')
                ->get()
                ->toArray();
        });
    }

    public function append($modelId, $tagId)
    {
        if (!$modelId || !$tagId)
        {
            return '请求参数错误';
        }

        if ($this->contain($modelId, $tagId))
        {
            return true;
        }

        if (!$this->valid($tagId))
        {
            return '这个标签已被移除了';
        }

        if ($this->max_count && $this->max_count - count($this->getModalTagIds($modelId)) <= 1)
        {
            return '最多允许设置' . $this->max_count . '个标签';
        }

        DB::table($this->relation_table)
            ->inset([
                'model_id' => $modelId,
                'tag_id' => $tagId
            ]);

        Redis::DEL($this->modalTagsCacheKey($modelId));

        return true;
    }

    public function remove($modelId, $tagId)
    {
        if (!$modelId || !$tagId)
        {
            return false;
        }

        DB::table($this->relation_table)
            ->whereRaw('model_id = ? and tag_id = ?', [$modelId, $tagId])
            ->delete();

        Redis::DEL($this->modalTagsCacheKey($modelId));

        return true;
    }

    public function update($modelId, $tagIds)
    {
        if (!$modelId)
        {
            return false;
        }

        $hasTagIds = $this->getModalTagIds($modelId);

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
            $removeIds = array_diff($hasTagIds, $tagIds);
        }

        if (!empty($removeIds))
        {
            DB::table($this->relation_table)
                ->where('model_id', $modelId)
                ->whereIn('tag_id', $removeIds)
                ->delete();
        }

        if (!empty($appendIds))
        {
            $appendTags = [];
            foreach ($appendIds as $tagId)
            {
                $appendTags[] = [
                    'model_id' => $modelId,
                    'tag_id' => $tagId
                ];
            }

            DB::table($this->relation_table)
                ->insert($appendTags);
        }

        Redis::DEL($this->modalTagsCacheKey($modelId));

        return true;
    }

    public function createTag($name)
    {
        $name = Purifier::clean($name);

        $hasTag = DB::table($this->tag_table)
            ->where('name', $name)
            ->count();

        if ($hasTag)
        {
            return 0;
        }

        $newId = DB::table($this->tag_table)
            ->insertGetId([
                'name' => $name
            ]);

        Redis::DEL($this->all_tag_cache_key);

        return $newId;
    }

    public function updateTag($tagId, $name)
    {
        $name = Purifier::clean($name);

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

    protected function contain($modelId, $tagId)
    {
        return (int)DB::table($this->relation_table)
            ->whereRaw('model_id = ? and tag_id = ?', [$modelId, $tagId])
            ->count();
    }

    protected function getModalTagIds($modelId)
    {
        if (!$modelId)
        {
            return [];
        }

        return DB::table($this->relation_table)
            ->where('model_id', $modelId)
            ->pluck('tag_id')
            ->toArray();
    }

    protected function modalTagsCacheKey($modelId)
    {
        return $this->tag_table . '_' . $modelId . '_tags';
    }
}
