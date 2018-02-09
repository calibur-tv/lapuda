<?php

namespace App\Jobs\Search\Post;

use App\Models\MixinSearch;
use App\Models\PostLike;
use App\Models\PostMark;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Delete implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($postId)
    {
        $this->postId = $postId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        PostLike::where('post_id', $this->postId)->delete();
        PostMark::where('post_id', $this->postId)->delete();
        MixinSearch::whereRaw('modal_id = ? and type = ?', [$this->postId, 2])->delete();
    }
}
