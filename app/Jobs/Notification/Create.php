<?php

namespace App\Jobs\Notification;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/20
 * Time: 上午6:21
 */

use App\Api\V1\Repositories\Repository;
use App\Models\Notifications;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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

        $now = Carbon::now();
        $id = Notifications::insertGetId([
            'type' => $this->convertStrTypeToInt(),
            'model_id' => $this->modelId,
            'comment_id' => $this->commentId,
            'reply_id' => $this->replyId,
            'to_user_id' => $this->toUserId,
            'from_user_id' => $this->fromUserId,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        $repository = new Repository();
        $repository->ListInsertBefore('user-' . $this->toUserId . '-notification-ids', $id);
    }

    protected function convertStrTypeToInt()
    {
        switch ($this->type)
        {
            case 'post-like':
                return 1;
                break;
            case 'post-reward':
                return 2;
                break;
            case 'post-mark':
                return 3;
                break;
            case 'post-comment':
                return 4;
                break;
            case 'post-reply':
                return 5;
                break;
            case 'image-like':
                return 6;
                break;
            case 'image-reward':
                return 7;
                break;
            case 'image-mark':
                return 8;
                break;
            case 'image-comment':
                return 9;
                break;
            case 'image-reply':
                return 10;
                break;
            case 'score-like':
                return 11;
                break;
            case 'score-reward':
                return 12;
                break;
            case 'score-mark':
                return 13;
                break;
            case 'score-comment':
                return 14;
                break;
            case 'score-reply':
                return 15;
                break;
            case 'video-comment':
                return 16;
                break;
            case 'video-reply':
                return 17;
                break;
            case 'post-comment-like':
                return 18;
                break;
            case 'post-reply-like':
                return 19;
                break;
            case 'image-comment-like':
                return 20;
                break;
            case 'image-reply-like':
                return 21;
                break;
            case 'score-comment-like':
                return 22;
                break;
            case 'score-reply-like':
                return 23;
                break;
            case 'video-comment-like':
                return 24;
                break;
            case 'video-reply-like':
                return 25;
                break;
            default:
                return 0;
                break;
        }
    }
}