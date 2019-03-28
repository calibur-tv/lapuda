<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午9:21
 */

namespace App\Api\V1\Transformers;


class BangumiTransformer extends Transformer
{
    public function item($bangumi)
    {
        return $this->transformer($bangumi, function ($bangumi)
        {
           return [
               'id' => (int)$bangumi['id'],
               'name' => $bangumi['name'],
               'avatar' => $bangumi['avatar']
            ];
        });
    }

    public function video($bangumi)
    {
        return $this->transformer($bangumi, function ($bangumi)
        {
            return [
                'id' => (int)$bangumi['id'],
                'name' => $bangumi['name'],
                'avatar' => $bangumi['avatar'],
                'others_site_video' => (boolean)$bangumi['others_site_video'],
                'summary' => $bangumi['summary'],
                'followed' => $bangumi['followed']
            ];
        });
    }

    public function show($bangumi)
    {
        return $this->transformer($bangumi, function () use ($bangumi)
        {
            return [
                'id' => (int)$bangumi['id'],
                'name' => $bangumi['name'],
                'avatar' => $bangumi['avatar'],
                'banner' => $bangumi['banner'],
                'summary' => $bangumi['summary'],
                'score' => (float)$bangumi['score'],
                'count_score' => (int)$bangumi['count_score'],
                'share_data' => $bangumi['share_data'],
                'alias' => $bangumi['alias'],
                'followed' => $bangumi['followed'],
                'scored' => $bangumi['scored'],
                'is_master' => $bangumi['is_master'],
                'is_leader' => $bangumi['is_leader'],
                'tags' => $bangumi['tags'],
                'follow_users' => $bangumi['follow_users'],
                'manager_users' => $bangumi['manager_users'],
                'has_video' => (boolean)$bangumi['has_video'],
                'has_cartoon' => (boolean)$bangumi['has_cartoon'],
                'power' => $bangumi['power'],
                'qq_group' => $bangumi['qq_group']
            ];
        });
    }

    public function list($list)
    {
        return $this->collection($list, function ($bangumi)
        {
            return [
                'id' => (int)$bangumi['id'],
                'name' => $bangumi['name'],
                'avatar' => $bangumi['avatar']
            ];
        });
    }

    public function recommended($list)
    {
        return $this->collection($list, function ($bangumi)
        {
            return [
                'id' => (int)$bangumi['id'],
                'name' => $bangumi['name'],
                'avatar' => $bangumi['avatar'],
                'tag' => $bangumi['tag']
            ];
        });
    }

    public function timeline($list)
    {
        return $this->collection($list, function ($bangumi)
        {
            return [
                'id' => (int)$bangumi['id'],
                'name' => $bangumi['name'],
                'timeline' => $bangumi['timeline'],
                'avatar' => $bangumi['avatar'],
                'summary' => $bangumi['summary']
            ];
        });
    }

    public function category($list)
    {
        return $this->collection($list, function ($bangumi)
        {
            return [
                'id' => (int)$bangumi['id'],
                'name' => $bangumi['name'],
                'avatar' => $bangumi['avatar'],
                'summary' => $bangumi['summary']
            ];
        });
    }

    public function released($list)
    {
        return $this->collection($list, function ($bangumi)
        {
            return [
                'id' => (int)$bangumi['id'],
                'name' => $bangumi['name'],
                'avatar' => $bangumi['avatar'],
                'update' => $bangumi['update'],
                'released_video_id' => (int)$bangumi['released_video_id'],
                'released_part' => (string)$bangumi['released_part'],
                'end' => (boolean)$bangumi['end']
            ];
        });
    }

    public function panel($bangumi)
    {
        return [
            'id' => (int)$bangumi['id'],
            'name' => $bangumi['name'],
            'avatar' => $bangumi['avatar'],
            'summary' => $bangumi['summary'],
            'followed' => (boolean)$bangumi['followed'],
            'is_master' => (boolean)$bangumi['is_master']
        ];
    }

    public function userFollowedList($bangumis)
    {
        return $this->collection($bangumis, function ($bangumi)
        {
            return [
                'id' => (int)$bangumi['id'],
                'name' => $bangumi['name'],
                'avatar' => $bangumi['avatar'],
                'created_at' => (int)$bangumi['created_at']
            ];
        });
    }

    public function search($bangumi)
    {
        return [
            'id' => (int)$bangumi['id'],
            'name' => $bangumi['name'],
            'avatar' => $bangumi['avatar'],
            'summary' => $bangumi['summary']
        ];
    }

    public function meta($bangumi)
    {
        return [
            'id' => (int)$bangumi['id'],
            'name' => $bangumi['name'],
            'avatar' => $bangumi['avatar'],
            'summary' => $bangumi['summary']
        ];
    }
}