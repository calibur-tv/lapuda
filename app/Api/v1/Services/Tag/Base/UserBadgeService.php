<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019/1/6
 * Time: 上午10:41
 */

namespace App\Api\V1\Services\Tag\Base;


use App\Api\V1\Repositories\Repository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UserBadgeService
{
    private $badge_table = 'user_badges';
    private $badge_relation_table = 'user_badge_relations';

    public function createBage(array $form)
    {
        $repository = new Repository();
        $name = $form['name'];
        $icon = $form['icon'];
        $intro = $form['intro'];
        $level = $form['level'];

        $badgeId = DB
            ::table($this->badge_table)
            ->insertGetId([
                'name' => $name,
                'intro' => $intro,
                'level' => $level,
                'icon' => $repository->convertImagePath($icon)
            ]);

        return $badgeId;
    }

    public function updateBadge(array $form)
    {
        $repository = new Repository();
        $id = $form['id'];
        $name = $form['name'];
        $icon = $form['icon'];
        $intro = $form['intro'];
        $level = $form['level'];

        DB
            ::table($this->badge_table)
            ->where('id', $id)
            ->update([
                'name' => $name,
                'intro' => $intro,
                'level' => $level,
                'icon' => $repository->convertImagePath($icon)
            ]);

        Redis::DEL($this->badgeCacheKey($id));
        return $id;
    }

    public function deleteBadge($badgeId)
    {
        DB
            ::table($this->badge_relation_table)
            ->where('badge_id', $badgeId)
            ->delete();

        DB
            ::table($this->badge_table)
            ->where('id', $badgeId)
            ->delete();

        Redis::DEL($this->badgeCacheKey($badgeId));
        $userIds = DB
            ::table($this->badge_relation_table)
            ->where('badge_id', $badgeId)
            ->pluck('user_id')
            ->toArray();

        $repository = new Repository();
        foreach ($userIds as $uid)
        {
            $cacheKey = $this->userBadgeListCacheKey($uid);
            $repository->SortRemove($cacheKey, $badgeId);
        }
    }

    public function getAllBadge()
    {
        return DB
            ::table($this->badge_table)
            ->get()
            ->toArray();
    }

    public function setUserBadge($userId, $badgeId, $intro = '')
    {
        $hasBadgeId = DB
            ::table($this->badge_relation_table)
            ->where('badge_id', $badgeId)
            ->where('user_id', $userId)
            ->pluck('user_id')
            ->first();

        $badge = $this->getBadgeItem($badgeId);
        if ($hasBadgeId && $badge['level'] > 1)
        {
            return;
        }

        if ($hasBadgeId)
        {
            DB
                ::table($this->badge_relation_table)
                ->where('badge_id', $badgeId)
                ->where('user_id', $userId)
                ->increment('count');
        }
        else
        {
            DB
                ::table($this->badge_relation_table)
                ->insert([
                    'badge_id' => $badgeId,
                    'user_id' => $userId,
                    'intro' => $intro,
                    'count' => 1,
                    'created_at' => Carbon::now()
                ]);

            DB
                ::table($this->badge_table)
                ->where('id', $badgeId)
                ->increment('user_count');

            $badge = $this->getBadgeItem($badgeId);
            $repository = new Repository();
            $repository->SortAdd($this->userBadgeListCacheKey($userId), $badgeId, $badge['level']);
            Redis::HINCRBY($this->badgeCacheKey($badgeId), 'user_count', 1);
        }
    }

    public function removeUserBadge($userId, $badgeId, $deleteCount = 1)
    {

        $badgeCount = DB
            ::table($this->badge_relation_table)
            ->where('badge_id', $badgeId)
            ->where("user_id", $userId)
            ->pluck('count')
            ->first();

        if (!$badgeCount)
        {
            return;
        }
        if ($badgeCount > $deleteCount)
        {
            DB
                ::table($this->badge_relation_table)
                ->where('badge_id', $badgeId)
                ->where("user_id", $userId)
                ->increment('count', -$deleteCount);
        }
        else
        {
            DB
                ::table($this->badge_relation_table)
                ->where('badge_id', $badgeId)
                ->where("user_id", $userId)
                ->delete();

            DB
                ::table($this->badge_table)
                ->where('id', $badgeId)
                ->increment('user_count', -1);

            $repository = new Repository();
            $repository->SortRemove($this->userBadgeListCacheKey($userId), $badgeId);
            Redis::HINCRBY($this->badgeCacheKey($badgeId), 'user_count', -1);
        }
    }

    public function getBadgeUserIds($badgeId)
    {
        return DB
            ::table($this->badge_relation_table)
            ->where('badge_id', $badgeId)
            ->pluck('user_id')
            ->toArray();
    }

    public function getUserBadges($userId)
    {
        $repository = new Repository();
        $badgeIds = $repository->RedisSort($this->userBadgeListCacheKey($userId), function () use ($userId)
        {
            $badgeIds = DB
                ::table($this->badge_relation_table)
                ->where('user_id', $userId)
                ->orderBy('count', 'DESC')
                ->pluck('badge_id')
                ->toArray();

            $result = [];
            foreach ($badgeIds as $bid)
            {
                $badge = $this->getBadgeItem($bid);
                $result[$bid] = $badge['level'];
            }

            return $result;
        });
        $result = [];
        foreach ($badgeIds as $bid)
        {
            $badge = $this->getBadgeItem($bid);
            $result[] = [
                'id' => $bid,
                'name' => $badge['name'],
                'icon' => $badge['icon']
            ];
        }

        return $result;
    }

    public function getBadgeItem($badgeId, $userId = 0)
    {
        $repository = new Repository();
        $badge = $repository->RedisHash($this->badgeCacheKey($badgeId), function () use ($badgeId)
        {
            $badge = DB
                ::table($this->badge_table)
                ->where('id', $badgeId)
                ->first();
            if (!$badge)
            {
                return null;
            }
            return [
                'id' => $badge->id,
                'icon' => config('website.image') . $badge->icon,
                'name' => $badge->name,
                'intro' => $badge->intro,
                'level' => $badge->level,
                'user_count' => $badge->user_count
            ];
        });
        if (!$userId)
        {
            return $badge;
        }

        $badge['user_get_count'] = DB
            ::table($this->badge_relation_table)
            ->where('badge_id', $badgeId)
            ->where("user_id", $userId)
            ->pluck('count')
            ->first();

        return $badge;
    }

    private function badgeCacheKey($badgeId)
    {
        return "user_badge_{$badgeId}_item";
    }

    private function userBadgeListCacheKey($userId)
    {
        return "user_{$userId}_badge_list";
    }
}