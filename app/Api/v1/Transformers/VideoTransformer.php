<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午11:36
 */

namespace App\Api\V1\Transformers;


class VideoTransformer extends Transformer
{
    public function show($video)
    {
        return $this->transformer($video, function ($video)
        {
            return [
                'user_id' => (int)$video['user_id'],
                'bangumi_id' => (int)$video['bangumi_id'],
                'comment_count' => (int)$video['comment_count'],
                'count_played' => (int)$video['count_played'],
                'created_at' => $video['created_at'],
                'name' => $video['name'],
                'part' => (int)$video['part'],
                'id' => (int)$video['id'],
                'poster' => $video['poster'],
                'url' => $video['url'],
                'resource' => $video['resource']
            ];
        });
    }

    public function search($video)
    {
        return $this->transformer($video, function ($video)
        {
            return [
                'name' => $video['name'],
                'id' => (int)$video['id'],
                'poster' => $video['poster']
            ];
        });
    }
}