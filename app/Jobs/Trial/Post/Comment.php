<?php

namespace App\Jobs\Trial\Post;

use App\Api\V1\Repositories\PostRepository;
use App\Models\MixinSearch;
use App\Services\Trial\WordsFilter\WordsFilter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class Comment implements ShouldQueue
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

        $state = 0;
        $needDelete = false;
        $deletedAt = null;

        // 文字审核流程
        $filter = new WordsFilter();
        $badWordsCount = $filter->count($post['content']);

        if ($badWordsCount > 1)
        {
            $needDelete = true;
        }

        if ($needDelete)
        {
            $state = 5;
            $deletedAt = Carbon::now();
        }
        else if ($badWordsCount)
        {
            $state = 4;
        }
        else
        {
            $searchId = MixinSearch::whereRaw('type_id = ? and modal_id = ?', [3, $this->postId])
                ->pluck('id')
                ->first();

            if (!is_null($searchId))
            {
                MixinSearch::where('id', $searchId)->increment('score', 2);
                MixinSearch::where('id', $searchId)->update([
                    'updated_at' => time()
                ]);
            }
        }

        if ($state || $needDelete)
        {
            DB::table('posts')
                ->where('id', $this->postId)
                ->update([
                    'state' => $state,
                    'deleted_at' => $deletedAt
                ]);
        }

        if ($needDelete)
        {
            Redis::LREM('post_'.$post['parent_id'].'_commentIds', 1, $post['id']);
        }
    }
}
