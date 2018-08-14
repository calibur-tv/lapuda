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
                'count_like' => (int)$bangumi['count_like'],
                'alias' => $bangumi['alias'],
                'followed' => $bangumi['followed'],
                'scored' => $bangumi['scored'],
                'is_master' => $bangumi['is_master'],
                'managers' => $bangumi['managers'],
                'tags' => $bangumi['tags'],
                'followers' => $bangumi['followers'],
                'has_video' => (boolean)$bangumi['has_video'],
                'has_cartoon' => (boolean)$bangumi['has_cartoon']
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
                'released_video_id' => $bangumi['released_video_id'],
                'released_part' => $bangumi['released_part'],
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
}