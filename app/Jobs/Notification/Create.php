<?php

namespace App\Jobs\Notification;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/20
 * Time: 上午6:21
 */

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
    protected $modelType;
    protected $modelId;
    protected $commentId;
    protected $replyId;

    /**
     * Create constructor.
     * @param $type             [一个字符串，然后被转化为数字存到数据库]
     * @param $toUserId         [发给谁]
     * @param $fromUserId       [谁发的]
     * @param int $modelType    [模型是什么，post，image，score...，被转化为 int 存到数据库]
     * @param int $modelId      [模型的id，文章id，图片id...，如果是系统消息，就可能没有]
     * @param int $commentId    [主评论的id，如果是打赏、点赞等，就没有]
     * @param int $replyId      [子评论的id，不一定有]
     */
    public function __construct($type, $toUserId, $fromUserId, $modelType = 0, $modelId = 0, $commentId = 0, $replyId = 0)
    {
        $this->type = $type;
        $this->toUserId = $toUserId;
        $this->fromUserId = $fromUserId;
        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->commentId = $commentId;
        $this->replyId = $replyId;
    }

    public function handle()
    {
        
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
            default:
                return 0;
                break;
        }
    }

    protected function convertStrModelToInt()
    {
        switch ($this->modelType)
        {
            case 'post':
                return 1;
                break;
            case 'image':
                return 2;
                break;
            case 'score':
                return 3;
                break;
            default:
                return 0;
                break;
        }
    }
}