<?php

namespace App\Jobs\Trial\Post;

use App\Api\V1\Repositories\PostRepository;
use App\Models\MixinSearch;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class Create implements ShouldQueue
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

        $badImageCount = 0;
        $needDelete = false;
        $state = 3;
        $deletedAt = null;

        // 文字审核流程
        $wordsFilter = new WordsFilter();
        $badWordsCount = $wordsFilter->count($post['content']);
        // 图片审核流程
        $imageFilter = new ImageFilter();
        foreach ($post['images'] as $image)
        {
            $badImageCount += $imageFilter->exec($image['url']);
        }

        if ($badWordsCount > 1)
        {
            $needDelete = true;
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

        if (!$badWordsCount && !$badImageCount && !$needDelete)
        {
            $now = time();

            MixinSearch::create([
                'title' => $post['title'],
                'content' => $post['desc'],
                'type_id' => 3,
                'modal_id' => $post['id'],
                'url' => '/post/' . $post['id'],
                'created_at' => $now,
                'updated_at' => $now
            ]);

            // TODO：set post-state
            Redis::ZADD('post_new_ids', strtotime($post['created_at']), $post['id']);
            Redis::EXPIREAT('post_new_ids', strtotime(date('Y-m-d')) + 86400 + rand(3600, 10800));

            $job = (new \App\Jobs\Push\Baidu('post/trending/new', 'update'));
            dispatch($job);

            return;
        }
        if ($needDelete)
        {
            Redis::DEL('post_'.$post['id']);
        }
    }
}
