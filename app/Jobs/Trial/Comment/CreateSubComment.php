<?php

namespace App\Jobs\Trial\Comment;

use App\Api\V1\Services\Comment\CommentService;
use App\Services\Trial\WordsFilter\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class CreateSubComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $table;

    protected $id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($table, $id)
    {
        $this->table = $table;

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

        $service = new CommentService($this->table);
        $comment = $service->getSubCommentItem($this->id);
        $content = $comment['content'];

        $filter = new WordsFilter();
        $badWordsCount = $filter->count($content);

        if ($badWordsCount > 0)
        {
            $service->deleteSubComment($this->id, 0, true);
            return;
        }
        $service->update($this->id, [
            'state' => 1
        ]);
    }
}
