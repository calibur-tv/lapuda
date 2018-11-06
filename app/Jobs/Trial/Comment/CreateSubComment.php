<?php

namespace App\Jobs\Trial\Comment;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\CommentService;
use App\Api\V1\Services\UserLevel;
use App\Services\Trial\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateSubComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $model;

    protected $id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($model, $id)
    {
        $this->model = $model;

        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $service = new CommentService($this->model);
        $comment = $service->getSubCommentItem($this->id);

        $userLevel = new UserLevel();
        $userRepository = new UserRepository();

        $user = $userRepository->item($comment['from_user_id']);
        $level = $userLevel->convertExpToLevel($user['exp']);

        if ($level < 5)
        {
            // 等级小于5级的用户，全量评论进审核
            $service->changeCommentState($this->id, $comment['from_user_id']);
            return;
        }

        $content = $comment['content'];

        $filter = new WordsFilter();
        $badWordsCount = $filter->count($content);

        if ($badWordsCount > 0)
        {
            $service->changeCommentState($this->id, $comment['from_user_id']);
        }
    }
}
