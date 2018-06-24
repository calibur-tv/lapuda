<?php

namespace App\Jobs\Trial\Post;

use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Services\Trending\TrendingService;
use App\Services\OpenSearch\Search;
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

        if ($badWordsCount + $badImageCount > 2)
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

        if ($state === 3)
        {
            $searchService = new Search();
            $searchService->create(
                $post['id'],
                $post['title'] . ',' . $post['desc'],
                'post'
            );

            $trendingService = new TrendingService('posts');
            $trendingService->create($post['id']);

            $job = (new \App\Jobs\Push\Baidu('post/trending/new', 'update'));
            dispatch($job);

            $job = (new \App\Jobs\Push\Baidu('post/' . $post['id']));
            dispatch($job);

            $job = (new \App\Jobs\Push\Baidu('bangumi/' . $post['bangumi_id'], 'update'));
            dispatch($job);

            return;
        }
        if ($needDelete)
        {
            Redis::DEL('post_'.$post['id']);
        }
    }
}
