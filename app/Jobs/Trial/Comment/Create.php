<?php

namespace App\Jobs\Trial\Comment;

use App\Services\Trial\WordsFilter\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class Create implements ShouldQueue
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
        $content = DB::table($this->table)->where('id', $this->id)
            ->pluck('content')
            ->first();

        $state = 1;

        $filter = new WordsFilter();
        $badWordsCount = $filter->count($content);

        if ($badWordsCount > 0)
        {
            $state = 2;
        }

        DB::table($this->table)->where('id', $this->id)
            ->update([
                'state' => $state
            ]);
    }
}
