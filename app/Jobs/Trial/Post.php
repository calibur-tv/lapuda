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
        // 5：加入最新文章列表
        Redis::ZADD('post_new_ids', strtotime($post['created_at']), $post['id']);
    }
}
