<?php

namespace App\Jobs\Trial\Comment;

use App\Api\V1\Services\PostCommentService;
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

    protected $parentId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($table, $id, $parentId)
    {
        $this->table = $table;

        $this->id = $id;

        $this->parentId = $parentId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $service = new PostCommentService();

        $comment = $service->getSubCommentItem($this->id, $this->parentId);
        $content = $comment['content'];


        $filter = new WordsFilter();
        $badWordsCount = $filter->count($content);

        if ($badWordsCount > 0)
        {
            $service->deleteSubComment($this->id, $this->parentId, 0);
        }
        else
        {
            DB::table($this->table)
                ->whereRaw('id = ? and parent_id = ?', [$this->id, $this->parentId])
                ->update([
                    'state' => 1
                ]);
        }
    }
}
