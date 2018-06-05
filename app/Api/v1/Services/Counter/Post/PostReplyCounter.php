<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/5
 * Time: 下午7:47
 */

namespace App\Api\V1\Services\Counter\Post;


use App\Api\V1\Services\Counter\CounterService;
use Illuminate\Support\Facades\DB;

class PostReplyCounter extends CounterService
{
    public function __construct()
    {
        parent::__construct('posts', 'comment_count');
    }

    public function migrate()
    {
        return DB::table('post_comments')
            ->where('modal_id', $this->id)
            ->count();
    }
}