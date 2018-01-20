<?php

namespace App\Jobs\Trial\Post;

use App\Api\V1\Repositories\PostRepository;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class Reply implements ShouldQueue
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
        if (is_null($post))
        {
            return;
        }

        $badWordsCount = 0;
        $badImageCount = 0;
        $needDelete = false;
        $state = 3;
        $deletedAt = null;

        // 图片审核流程
        foreach ($post['images'] as $url)
        {
            // 色情
            $respSex = json_decode(file_get_contents(env('website.image') . $url . '?qpulp'), true);
            if ($respSex['code'] != 0)
            {
                $badImageCount++;
            }
            else
            {
                if ($respSex['result']['label'] == 1)
                {
                    $badImageCount++;
                }
                else if ($respSex['result']['label'] == 0)
                {
                    $badImageCount++;
                    $needDelete = true;
                }
                if ($respSex['result']['review'] == true)
                {
                    $needDelete = true;
                }
            }
            // 暴恐
            $respWarn = json_decode(file_get_contents(env('website.image') . $url . '?qterror'), true);
            if ($respWarn['code'] != 0)
            {
                $badImageCount++;
            }
            else
            {
                if ($respWarn['result']['label'] == 1)
                {
                    $badImageCount++;
                }
                if ($respWarn['result']['review'] == true)
                {
                    $needDelete = true;
                }
            }
            // 政治敏感
            $respDaddy = json_decode(file_get_contents(env('website.image') . $url . '?qpolitician'), true);
            if ($respDaddy['code'] != 0)
            {
                $badImageCount++;
            }
            else
            {
                if ($respDaddy['result']['review'] == true)
                {
                    $needDelete = true;
                }
            }
        }

        if ($needDelete)
        {
            $state = 5;
            $deletedAt = Carbon::now();
        }
        else if ($badImageCount || $badWordsCount)
        {
            $state = 4;
        }

        DB::table('posts')
            ->where('id', $this->postId)
            ->update([
                'state' => $state,
                'deleted_at' => $deletedAt
            ]);

        if ($needDelete)
        {
            Redis::DEL('post_'.$post['id']);
        }
    }
}
