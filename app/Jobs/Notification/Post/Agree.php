<?php

namespace App\Jobs\Notification\Post;

use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Models\Notifications;
use App\Models\PostLike;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class Agree implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $likeId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->likeId = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $like = DB::table('post_comment_like')
            ->where('id', $this->likeId)
            ->first();

        if (is_null($like))
        {
            return;
        }

        $postCommentService = new PostCommentService();
        $reply = $postCommentService->getMainCommentItem($like->modal_id);

        if (is_null($reply))
        {
            return;
        }

        Notifications::create([
            'from_user_id' => $like->user_id,
            'to_user_id' => $reply['from_user_id'],
            'about_id' => $reply['id'] . ',' . $reply['modal_id'],
            'type' => 4
        ]);
    }
}
