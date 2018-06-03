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
        if (config('app.env') === 'local')
        {
            return;
        }

        $service = new CommentService($this->modal);
        $comment = $service->getMainCommentItem($this->id, true);

        $content = $comment['content'];
        $images = $comment['images'];
        $badCount = 0;

        $wordsFilter = new WordsFilter();
        $imageFilter = new ImageFilter();
        $badCount += $wordsFilter->count($content);
        $badCount += $imageFilter->list($images);

        if ($badCount > 0)
        {
            $service->update($this->id, [
                'state' => 2
            ]);
            return;
        }

        $service->update($this->id, [
            'state' => 1
        ]);
    }
}
