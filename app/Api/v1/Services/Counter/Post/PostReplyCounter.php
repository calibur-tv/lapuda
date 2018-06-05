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
    protected $id;

    public function __construct($postId)
    {
        parent::__construct('posts', 'comment_count');

        $this->id = $postId;
    }

    public function migrate()
    {
        return DB::table('post_comments')
            ->where('modal_id', $this->id)
            ->count();
    }
}