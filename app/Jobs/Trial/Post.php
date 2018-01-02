<?php

namespace App\Jobs\Trial;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Post implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $post;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(\App\Models\Post $post)
    {
        $this->post = $post;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 1：文字审核，如果有1个高危，直接删 return
        // 2: 文字审核，如果有2个及其以上可疑，进人工审核 return
        // 3：从七牛获取图片鉴黄结果，如果有确诊的，直接删 return
        // 4: 如果图片鉴黄有疑似，进入人工审核 return
        // 5：加入最新文章列表
    }
}
