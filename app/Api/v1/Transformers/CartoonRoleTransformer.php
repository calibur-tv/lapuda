<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/5
 * Time: 上午11:36
 */

namespace App\Api\V1\Transformers;


class CartoonRoleTransformer extends Transformer
{
    public function show($data)
    {
        return $this->transformer($data, function ($role)
        {
            return [
                'bangumi' => $role['bangumi'],
                'share_data' => $role['share_data'],
                'data' => $this->transformer($role['data'], function ($info)
                {
                    return [
                        'id' => (int)$info['id'],
                        'alias' => $info['alias'],
                        'avatar' => $info['avatar'],
                        'fans_count' => (int)$info['fans_count'],
                        'hasStar' => (int)$info['hasStar'],
                        'intro' => $info['intro'],
                        'lover' => $info['lover'],
                        'name' => $info['name'],
                        'star_count' => (int)$info['star_count'],
                        'trending' => $info['trending']
                    ];
                }),
            ];
        });
    }

    public function idol($role)
    {
        return $this->transformer($role, function ($info)
        {
            return [
                'id' => (int)$info['id'],
                'alias' => $info['alias'],
                'avatar' => $info['avatar'],
                'intro' => $info['intro'],
                'name' => $info['name'],
                'boss' => $info['boss'],
                'manager' => $info['manager'],
                'lover_words' => $info['lover_words'],
                'has_star' => sprintf("%.2f", $info['has_star']),
                'market_price' => sprintf("%.2f", $info['market_price']),
                'stock_price' => sprintf("%.2f", $info['stock_price']),
                'star_count' => sprintf("%.2f", $info['star_count']),
                'max_stock_count' => sprintf("%.2f", $info['max_stock_count']),
                'is_locked' => floatval($info['max_stock_count']) && floatval($info['max_stock_count']) <= floatval($info['star_count']),
                'company_state' => intval($info['company_state']),
                'fans_count' => intval($info['fans_count']),
                'ipo_at' => $info['ipo_at'],
                'created_at' => $info['created_at']
            ];
        });
    }

    public function market($list)
    {
        return $this->collection($list, function ($info)
        {
            return [
                'id' => (int)$info['id'],
                'avatar' => $info['avatar'],
                'name' => $info['name'],
                'market_price' => sprintf("%.2f", $info['market_price']),
                'stock_price' => sprintf("%.2f", $info['stock_price']),
                'star_count' => sprintf("%.2f", $info['star_count']),
                'fans_count' => intval($info['fans_count']),
                'company_state' => intval($info['company_state']),
                'ipo_at' => $info['ipo_at'],
                'created_at' => $info['created_at']
            ];
        });
    }

    public function fans($users)
    {
        return $this->collection($users, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'nickname' => $user['nickname'],
                'score' => (int)$user['score']
            ];
        });
    }

    public function owners($users)
    {
        return $this->collection($users, function ($user)
        {
            return [
                'id' => (int)$user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'nickname' => $user['nickname'],
                'score' => sprintf("%.2f", $user['score'])
            ];
        });
    }

    public function search($data)
    {
        return $this->transformer($data, function ($role)
        {
            return [
                'id' => (int)$role['id'],
                'name' => $role['name'],
                'avatar' => $role['avatar'],
                'intro' => $role['intro']
            ];
        });
    }

    public function trending($list)
    {
        return $this->collection($list, function ($item)
        {
            $result = [
                'id' => (int)$item['id'],
                'avatar' => $item['avatar'],
                'name' => $item['name'],
                'intro' => $item['intro'],
                'star_count' => (int)$item['star_count'],
                'fans_count' => (int)$item['fans_count'],
                'bangumi' => $this->transformer($item['bangumi'], function ($bangumi)
                {
                    return [
                        'id' => (int)$bangumi['id'],
                        'name' => $bangumi['name'],
                        'avatar' => $bangumi['avatar']
                    ];
                })
            ];

            if ($item['loverId'])
            {
                $result = array_merge($result, [
                    'lover' => [
                        'id' => $item['loverId'],
                        'avatar' => $item['lover_avatar'],
                        'nickname' => $item['lover_nickname'],
                        'zone' => $item['lover_zone']
                    ]
                ]);
            }
            else
            {
                $result = array_merge($result, [
                    'lover' => null
                ]);
            }

            return $result;
        });
    }
}