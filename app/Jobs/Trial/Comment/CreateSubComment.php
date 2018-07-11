<?php

namespace App\Jobs\Trial\Comment;

use App\Api\V1\Services\Comment\CommentService;
use App\Services\Trial\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

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
        $content = $comment['content'];

        $filter = new WordsFilter();
        $badWordsCount = $filter->count($content);

        if ($badWordsCount > 0)
        {
            $service->changeCommentState($this->id, $comment['user_id']);
        }
    }
}
