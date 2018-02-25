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

        $badImageCount = 0;
        $needDelete = false;
        $state = 0;
        $deletedAt = null;

        // 图片审核流程
        foreach ($post['images'] as $url)
        {
            $tmp = explode('|http' ,$url);
            $url = count($tmp) === 2 ? 'http' . end($tmp) : $url;
            // 色情
            $respSex = json_decode(file_get_contents(env('website.image') . $url . '?qpulp'), true);
            if (intval($respSex['code']) !== 0)
            {
                $badImageCount++;
            }
            else
            {
                if (intval($respSex['result']['label']) === 1)
                {
                    $badImageCount++;
                }
                else if (intval($respSex['result']['label']) === 0)
                {
                    $badImageCount++;
                    $needDelete = true;
                }
                if ((boolean)$respSex['result']['review'] === true)
                {
                    $needDelete = true;
                }
            }
            // 暴恐
            $respWarn = json_decode(file_get_contents(env('website.image') . $url . '?qterror'), true);
            if (intval($respWarn['code']) !== 0)
            {
                $badImageCount++;
            }
            else
            {
                if (intval($respWarn['result']['label']) === 1)
                {
                    $badImageCount++;
                }
                if ((boolean)$respWarn['result']['review'] === true)
                {
                    $needDelete = true;
                }
            }
            // 政治敏感
            $respDaddy = json_decode(file_get_contents(env('website.image') . $url . '?qpolitician'), true);
            if (intval($respDaddy['code']) !== 0)
            {
                $badImageCount++;
            }
            else
            {
                if ((boolean)$respDaddy['result']['review'] === true)
                {
                    $needDelete = true;
                }
            }
        }

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
        else if ($badImageCount || $badWordsCount)
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
                MixinSearch::where('id', $searchId)->increment('score', 5);
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
            Redis::DEL('post_'.$post['id']);
        }
    }
}
