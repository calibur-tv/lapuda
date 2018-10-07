<?php

namespace App\Jobs\Notification;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/20
 * Time: 上午6:21
 */

use App\Api\V1\Presenter\NotificationPresenter;
use App\Api\V1\Repositories\Repository;
use App\Models\Notifications;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;

class Create implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $type;
    protected $toUserId;
    protected $fromUserId;
    protected $modelId;
    protected $commentId;
    protected $replyId;

    /**
     * Create constructor.
     * @param $type             [一个字符串，然后被转化为数字存到数据库]
     * @param $toUserId         [发给谁]
     * @param $fromUserId       [谁发的]
     * @param int $modelId      [模型的id，文章id，图片id...，如果是系统消息，就可能没有]
     * @param int $commentId    [主评论的id，如果是打赏、点赞等，就没有]
     * @param int $replyId      [子评论的id，不一定有]
     */
    public function __construct($type, $toUserId, $fromUserId, $modelId = 0, $commentId = 0, $replyId = 0)
    {
        $this->type = $type;
        $this->toUserId = $toUserId;
        $this->fromUserId = $fromUserId;
        $this->modelId = $modelId;
        $this->commentId = $commentId;
        $this->replyId = $replyId;
    }

    public function handle()
    {
        if (!$this->toUserId)
        {
            return;
        }

        if ($this->fromUserId === $this->toUserId)
        {
            return;
        }

        $notificationPresenter = new NotificationPresenter();
        $type = $notificationPresenter->convertStrTypeToInt($this->type);
        if (!$type)
        {
            return;
        }

        $now = Carbon::now();
        $userId = $this->toUserId;

        $id = Notifications::insertGetId([
            'type' => $type,
            'model_id' => $this->modelId,
            'comment_id' => $this->commentId,
            'reply_id' => $this->replyId,
            'to_user_id' => $userId,
            'from_user_id' => $this->fromUserId,
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
