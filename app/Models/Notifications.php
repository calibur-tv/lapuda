<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifications extends Model
{
    protected $table = 'notifications';

    /**
     * type
     * 1：回复主题帖
     * 2：回复楼层贴
     */
    protected $fillable = [
        'type',             // 通知的类型，枚举
        'from_user_id',     // 触发消息的用户id
        'to_user_id',       // 接受消息的用户id
        'parent_id',        // 祖辈相关的 Model_id，不一定存在，直接回复的 id，比如说 commentId
        'about_id',         // 通知关联的 Model_id 肯定存在，直接或间接相关的 id，比如说 postId
        'checked',          // 是否已读
    ];
}
