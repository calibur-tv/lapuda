<?php

namespace App\Api\V1\Services;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/10/4
 * Time: 下午2:34
 */
class UserLevel
{
    // EXP + 25 = (level + 5)^2
    // 用户自己主动操作的才加经验，被动的数据不加经验（被点赞/被打赏）
    // 发帖 + 4
    // 发图片 + 3
    // 发漫评 + 5
    // 提问 + 3
    // 回答 + 4
    // 主评论 + 2
    // 子评论 + 1
    // 签到 + 2

    public function convertExpToLevel($exp)
    {
        $result = 1;
        $computeExp = $exp;
        for ($i = 1; $i < 999; $i++)
        {
            $thisLevelNeedExp = $i * ($i + 10);
            if ($computeExp < $thisLevelNeedExp)
            {
                $result = $i;
                break;
            }
            else if ($computeExp === $thisLevelNeedExp)
            {
                $result = $i + 1;
                break;
            }
            else
            {
                $computeExp = $computeExp - $thisLevelNeedExp;
            }
        }

        return $result;
    }

    public function computeExpObject($exp)
    {
        $exp = intval($exp);
        $level = $this->convertExpToLevel($exp);
        $next_level_exp = $level * $level + ($level * 10);
        $totalExp = 0;
        if ($level > 1)
        {
            for ($i = 1; $i < $level; $i++)
            {
                $totalExp += $i * ($i + 10);
            }
            $have_exp = $exp - $totalExp;
        }
        else
        {
            $have_exp = $exp;
        }

        return [
            'level' => $level,
            'next_level_exp' => $next_level_exp,
            'have_exp' => $have_exp
        ];
    }

    public function change($userId, $score)
    {
        User::where('id', $userId)
            ->increment('exp', $score);

        if (Redis::EXISTS('user_' . $userId))
        {
            Redis::HINCRBYFLOAT('user_' . $userId, 'exp', $score);
        }
    }
}