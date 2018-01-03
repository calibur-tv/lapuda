<?php

namespace App\Jobs\Trial;

use App\Repositories\PostRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;

class Post implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->postId = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $repository = new PostRepository();
        $post = $repository->item($this->postId);
        // 1：文字审核，如果有1个高危，直接删 return
        // 2: 文字审核，如果有2个及其以上可疑，进人工审核 return
        // 3：从七牛获取图片鉴黄结果，如果有确诊的，直接删 return
        // 4: 如果图片鉴黄有疑似，进入人工审核 return
        // 5：修改文章状态并加入最新文章列表
        \App\Models\Post::where('id', $this->postId)->update([
           'state' => 3
        ]);
        Redis::pipeline(function ($pipe) use ($post)
        {
            $cache = 'post_'.$post['id'];
            if ($pipe->EXISTS($cache))
            {
                $pipe->HSET($cache, 'state', 3);
            }
            $pipe->ZADD('post_new_ids', strtotime($post['created_at']), $post['id']);
            $pipe->EXPIREAT('post_new_ids', strtotime(date('Y-m-d')) + 86400 + rand(3600, 10800));
        });
    }
}
