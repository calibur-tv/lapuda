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

class Delete implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // TODO delete one, delete all
    public function __construct($type, $toUserId, $fromUserId, $messageId)
    {

    }
}