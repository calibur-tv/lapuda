<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/4
 * Time: 下午9:17
 */

namespace App\Api\V1\Transformers;


class QuestionTransformer extends Transformer
{
    public function show($question)
    {
        return $this->transformer($question, function ($question)
        {
            return [
                'id' => (int)$question['id'],
                'title' => $question['title'],
                'intro' => $question['intro'],
                'content' => $question['content'],
                'images' => $this->collection($question['images'], function ($image)
                {
                    return [
                        'width' => (int)$image['width'],
                        'height' => (int)$image['height'],
                        'size' => (int)$image['size'],
                        'type' => $image['type'],
                        'url' => config('website.image'). $image['url']
                    ];
                }),
                'tags' => $question['tags'],
                'user_id' => (int)$question['user_id'],
                'view_count' => (int)$question['view_count'],
                'commented' => $question['commented'],
                'answer_count' => $question['answer_count'],
                'my_answer' => $question['my_answer'],
                'comment_count' => (int)$question['comment_count'],
                'followed' => $question['followed'],
                'follow_users' => $question['follow_users'],
                'created_at' => $question['created_at'],
                'updated_at' => $question['updated_at']
            ];
        });
    }

    public function search($question)
    {
        return $this->transformer($question, function ($question)
        {
            return [
                'id' => (int)$question['id'],
                'title' => $question['title'],
                'desc' => $question['desc'],
                'images' => $question['images'],
                'created_at' => $question['created_at']
            ];
        });
    }

    public function userFlow($list)
    {
        return $this->collection($list, function ($item)
        {
            return array_merge(
                $this->baseFlow($item),
                [
                    'answer_count' => $item['answer_count'],
                    'follow_count' => $item['follow_count'],
                    'comment_count' => $item['comment_count']
                ]
            );
        });
    }

    public function trendingFlow($list)
    {
        return $this->collection($list, function ($item)
        {
            return array_merge(
                $this->baseFlow($item),
                [
                    'answer' => $item['answer'] ? $this->transformer($item['answer'], function ($answer)
                    {
                        $images = array_filter($answer['content'], function ($item)
                        {
                            return $item['type'] === 'img';
                        });

                        return [
                            'id' => (int)$answer['id'],
                            'intro' => $answer['intro'],
                            'poster' => empty($images) ? null : current($images),
                            'is_creator' => !(boolean)$answer['source_url'],
                            'vote_count' => $answer['vote_count']
                        ];
                    }) : null
                ]
            );
        });
    }

    protected function baseFlow($item)
    {
        return $this->transformer($item, function ($item)
        {
            return [
                'id' => (int)$item['id'],
                'title' => $item['title'],
                'intro' => $item['intro'],
                'user' => $this->transformer($item['user'], function ($user)
                {
                    return [
                        'id' => (int)$user['id'],
                        'nickname' => $user['nickname'],
                        'avatar' => $user['avatar'],
                        'zone' => $user['zone']
                    ];
                }),
                'answer_count' => $item['answer_count'],
                'follow_count' => $item['follow_count'],
                'comment_count' => $item['comment_count'],
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at']
            ];
        });
    }
}