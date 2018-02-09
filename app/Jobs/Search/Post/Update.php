<?php

namespace App\Jobs\Search\Post;

use App\Models\MixinSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Update implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postId;

    protected $count;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id, $num)
    {
        $this->postId = $id;
        $this->count = $num;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $searchId = MixinSearch::whereRaw('type_id = ? and modal_id = ?', [2, $this->postId])
            ->pluck('id')
            ->first();

        if (!is_null($searchId))
        {
            MixinSearch::where('id', $searchId)->increment('score', $this->count);
            MixinSearch::where('id', $searchId)->update([
                'updated_at' => time()
            ]);
        }
    }
}
