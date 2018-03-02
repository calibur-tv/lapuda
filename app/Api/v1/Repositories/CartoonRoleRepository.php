<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/2
 * Time: 下午12:49
 */

namespace App\Api\V1\Repositories;

use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;

class CartoonRoleRepository extends Repository
{
    protected $userRepository;
    protected $bangumiRepository;

    public function item($id)
    {
        return $this->RedisHash('cartoon_role_' . $id, function () use ($id)
        {
           $role = CartoonRole::find($id);

           if (is_null($role))
           {
               return null;
           }

           $userId = CartoonRoleFans::where('role_id', $id)->orderBy('star_count', 'DESC')->pluck('user_id')->first();
           $role->loverId = !is_null($userId) && count($userId) <= 3 ? intval($userId[0]) : 0;

           return $role->toArray();
        });
    }

    public function list($ids)
    {
        if (empty($ids))
        {
            return [];
        }

        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->item($id);
        }

        return $result;
    }

    public function bangumiOfIds($bangumiId, $page)
    {
        return $this->RedisList('bangumi_' . $bangumiId . 'cartoon_role_page_' . $page, function () use ($bangumiId, $page)
        {
            return CartoonRole::where('bangumi_id', $bangumiId)
                ->take(15)
                ->skip(15 * ($page - 1))
                ->pluck('id');
        });
    }

    public function checkHasStar($roleId, $userId)
    {
        $count = CartoonRoleFans::whereRaw('role_id = ? and user_id = ?', [$roleId, $userId])->pluck('star_count')->first();

        return is_null($count) ? 0 : $count;
    }

    public function newFansIds($roleId, $minId, $count = null)
    {
        $take = $count ?: config('website.list_count');

        $ids = $this->RedisSort('cartoon_role_' . $roleId . '_new_fans_ids', function () use ($roleId)
        {
            return CartoonRoleFans::where('role_id', $roleId)
                ->orderBy('created_at', 'desc')
                ->latest()
                ->take(100)
                ->pluck('created_at', 'user_id AS id');

        }, true, false, true);

        if (empty($ids))
        {
            return [];
        }

        $keys = array_keys($ids);
        if (!$minId || !in_array($minId, $keys))
        {
            return array_slice($ids, 0, $take, true);
        }

        return array_slice($ids, array_search($minId, $keys) + 1, $take, true);
    }

    public function hotFansIds($roleId, $seenIds)
    {
        $ids = $this->RedisSort('cartoon_role_' . $roleId . '_hot_fans_ids', function () use ($roleId)
        {
            return CartoonRoleFans::where('role_id', $roleId)
                ->orderBy('star_count', 'desc')
                ->latest()
                ->take(100)
                ->pluck('star_count', 'user_id AS id');

        }, false, false, true);

        if (empty($ids))
        {
            return [];
        }

        foreach ($ids as $key => $val)
        {
            if (in_array($key, $seenIds))
            {
                unset($ids[$key]);
            }
        }

        return array_slice($ids, 0, config('website.list_count'), true);
    }

    public function trendingIds($force = false)
    {
        return $this->RedisSort('cartoon_role_trending_ids', function ()
        {
            return CartoonRole::where('fans_count', '>', 0)
                ->orderBy('star_count', 'desc')
                ->latest()
                ->take(100)
                ->pluck('star_count', 'id');

        }, false, $force);
    }

    public function trendingItem($roleId)
    {
        return $this->RedisHash('cartoon_role_trending_' . $roleId, function () use ($roleId)
        {
            $role = $this->item($roleId);

            if (is_null($role))
            {
                return null;
            }

            if (is_null($this->bangumiRepository))
            {
                $this->bangumiRepository = new BangumiRepository();
            }

            $bangumi = $this->bangumiRepository->item($role['bangumi_id']);

            if (is_null($bangumi))
            {
                return null;
            }

            if (is_null($this->userRepository))
            {
                $this->userRepository = new UserRepository();
            }

            $user = $this->userRepository->item($role['loverId']);

            $result = [
                'id' => $role['id'],
                'avatar' => $role['avatar'],
                'name' => $role['name'],
                'intro' => $role['intro'],
                'star_count' => $role['star_count'],
                'fans_count' => $role['fans_count'],
                'bangumi_id' => $role['bangumi_id'],
                'bangumi_avatar' => $bangumi['avatar'],
                'bangumi_name' => $bangumi['name'],
                'lover_id' => $role['loverId'],
                'lover_avatar' => $user['avatar'],
                'lover_nickname' => $user['nickname'],
                'lover_zone' => $user['zone']
            ];

            return $result;
        });
    }
}