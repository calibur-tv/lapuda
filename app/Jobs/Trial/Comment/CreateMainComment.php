<?php

namespace App\Jobs\Trial\Comment;

use App\Api\V1\Services\Comment\CommentService;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class CreateMainComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $modal;

    protected $id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($modal, $id)
    {
        $this->modal = $modal;

        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $service = new CommentService($this->modal);
        $comment = $service->getMainCommentItem($this->id);

        // 全量评论进审核
        $service->changeCommentState($this->id, $comment['from_user_id']);
        return;

        $content = $comment['content'];
        $images = $comment['images'];

        $badCount = 0;
        $needDelete = false;
        $imageFilter = new ImageFilter();

        foreach ($images as $image)
        {
            $result = $imageFilter->check($image['url']);
            if ($result['delete'])
            {
                $needDelete = true;
            }
            if ($result['review'])
            {
                $badCount++;
            }
        }
        if ($needDelete)
        {
            $service->deleteMainComment($this->id, 0, 0, false);
            return;
        }

        $wordsFilter = new WordsFilter();
        $badCount += $wordsFilter->count($content);

        if ($badCount > 0)
        {
            $service->changeCommentState($this->id, $comment['from_user_id']);
        }
    }
}
