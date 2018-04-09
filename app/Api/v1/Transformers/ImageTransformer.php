<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午9:21
 */

namespace App\Api\V1\Transformers;


class ImageTransformer extends Transformer
{
    public function item($image)
    {
        return $this->transformer($image, function ($image)
        {
           return [
               'id' => (int)$image['id'],
               'url' => $image['url'],
               'name' => $image['name'],
               'width' => (int)$image['width'],
               'height' => (int)$image['height'],
               'like_count' => $image['like_count'],
               'creator' => (boolean)$image['creator'],
               'tags' => $image['tags'],
               'role_id' => $image['role_id'],
               'user_id' => (int)$image['user_id'],
               'bangumi_id' => (int)$image['bangumi_id'],
               'bangumi' => $image['bangumi'] ? $this->transformer($image['bangumi'], function ($bangumi)
               {
                   return [
                       'id' => (int)$bangumi['id'],
                       'name' => $bangumi['name'],
                       'avatar' => $bangumi['avatar']
                   ];
               }) : null,
               'user' => $image['user'] ? $this->transformer($image['user'], function ($user)
               {
                   return [
                       'id' => (int)$user['id'],
                       'zone' => $user['zone'],
                       'avatar' => $user['avatar'],
                       'nickname' => $user['nickname']
                   ];
               }) : null,
               'role' => $image['role'] ? $this->transformer($image['role'], function ($role)
               {
                   return [
                       'id' => (int)$role['id'],
                       'name' => $role['name'],
                       'avatar' => $role['avatar']
                   ];
               }) : null
           ];
        });
    }

    public function indexBanner($list)
    {
        return $this->collection($list, function ($data)
        {
            return [
                'id' => (int)$data['id'],
                'url' => $data['url'],
                'gray' => (int)$data['gray'],
                'user_id' => (int)$data['user_id'],
                'user_nickname' => $data['user_nickname'],
                'user_zone' => $data['user_zone'],
                'user_avatar' => $data['user_avatar'],
                'bangumi_id' => (int)$data['bangumi_id'],
                'bangumi_name' => $data['bangumi_name']
            ];
        });
    }
}