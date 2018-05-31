<?php

namespace App\Jobs\Trial\Comment;

use App\Services\Trial\WordsFilter\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class CreateMainComment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $table;

    protected $id;

    protected $modalId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($table, $id, $modalId)
    {
        $this->table = $table;

        $this->id = $id;

        $this->modalId = $modalId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $content = DB::table($this->table)
            ->where('id = ? and modal_id = ?', [$this->id, $this->modalId])
            ->pluck('content')
            ->first();

        // TODO：rich content

        $state = 1;

        $filter = new WordsFilter();
        $badWordsCount = $filter->count($content);

        if ($badWordsCount > 0)
        {
            $state = 2;
        }

        DB::table($this->table)
            ->where('id = ? and modal_id = ?', [$this->id, $this->modalId])
            ->update([
                'state' => $state
            ]);
    }
}
