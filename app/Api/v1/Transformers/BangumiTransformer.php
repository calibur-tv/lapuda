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

    public function post($bangumi)
    {
        return $this->transformer($bangumi, function ($bangumi)
        {
            return [
                'id' => (int)$bangumi['id'],
                'name' => $bangumi['name'],
                'avatar' => $bangumi['avatar'],
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
                'count_score' => (float)$bangumi['count_score'],
                'count_like' => (int)$bangumi['count_like'],
                'alias' => $bangumi['alias'] === 'null' ? '' : json_decode($bangumi['alias'])->search,
                'season' => $bangumi['season'] === 'null' ? null : $this->transformer(json_decode($bangumi['season'], true), function ($season)
                {
                    return [
                        're' => isset($season['re']) ?(boolean)$season['re'] : false,
                        'name' => $season['name'],
                        'part' => $season['part'],
                        'time' => $season['time']
                    ];
                }),
                'followed' => $bangumi['followed'],
                'tags' => $this->collection($bangumi['tags'], function ($tag)
                {
                    return [
                        'id' => (int)$tag['id'],
                        'name' => $tag['name']
                    ];
                }),
                'followers' => $this->collection($bangumi['followers'], function ($follower)
                {
                    return [
                        'id' => (int)$follower['id'],
                        'zone' => $follower['zone'],
                        'avatar' => $follower['avatar'],
                        'nickname' => $follower['nickname']
                    ];
                })
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
                'tags' => $bangumi['tags'],
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
                'end' => isset($bangumi['end']) ? $bangumi['end'] : false
            ];
        });
    }
}