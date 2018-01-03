<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Trending;

use App\Models\Post;
use App\Repositories\PostRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

class PostHot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'PostHot';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'compute post hot list';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ids = Post::whereRaw('created_at > ? and parent_id = ?', [
            Carbon::now()->addDays(-7), 0
        ])->pluck('id')->get();

        $repository = new PostRepository();
        $list = $repository->list($ids);

        $result = [
            'id1' => 'score1',
            'id2' => 'score2'
        ];
        // https://toutiao.io/posts/eywxmu/preview

        Redis::pipeline(function ($pipe) use ($result)
        {
            $key = 'post_hot_ids';
            $pipe->DEL($key);
            $pipe->ZADD($key, $result);
        });
    }
}