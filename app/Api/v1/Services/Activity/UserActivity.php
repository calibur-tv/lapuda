<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/10/30
 * Time: 上午8:36
 */

namespace App\Api\V1\Services\Activity;


use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Models\Notifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

class UserActivity extends Activity
{
    public function __construct()
    {
        parent::__construct('user_day_activity');
    }

    protected function hook($userId, $score)
    {
        if ($score < 100)
        {
            return;
        }

        $userRepository = new UserRepository();
        $user = $userRepository->item($userId);
        if ($user['banned_to'])
        {
            return;
        }
        // 送团子
        $result = $userRepository->toggleCoin(false, 0, $userId, 15, 0);
        if ($result)
        {
            // 发消息通知
            $this->createNotification(42, $userId);
        }

        $bangumiManager = new BangumiManager();
        if (!$bangumiManager->isAManager($userId))
        {
            return;
        }
        // 送团子
        $result = $userRepository->toggleCoin(false, 0, $userId, 16, 0);
        if ($result)
        {
            // 发消息通知
            $this->createNotification(43, $userId);
        }
    }

    protected function createNotification($type, $userId)
    {
        $now = Carbon::now();

        $id = Notifications::insertGetId([
            'type' => $type,
            'model_id' => 0,
            'comment_id' => 0,
            'reply_id' => 0,
            'to_user_id' => $userId,
            'from_user_id' => 0,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        $repository = new Repository();
        $repository->ListInsertBefore('user-' . $userId . '-notification-ids', $id);
        if (Redis::EXISTS('user_' . $userId . '_notification_count'))
        {
            Redis::INCRBY('user_' . $userId . '_notification_count', 1);
        }
    }
}