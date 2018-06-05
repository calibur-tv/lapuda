<?php

namespace App\Jobs\Notification\Post;

use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Models\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Comment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $commentId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->commentId = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $postCommentService = new PostCommentService();
        $comment = $postCommentService->getSubCommentItem($this->commentId);
        if (is_null($comment))
        {
            return;
        }

        $reply = $postCommentService->getMainCommentItem($comment['parent_id']);
        if (is_null($reply))
        {
            return;
        }

        $postRepository = new PostRepository();
        $post = $postRepository->item($reply['modal_id']);
        if (is_null($post))
        {
            return;
        }

        Notifications::create([
            'from_user_id' => $comment['from_user_id'],
            'to_user_id' => $comment['to_user_id'],
            'about_id' => $comment['id'] . ',' . $reply['id'] . ',' . $post['id'],
            'type' => 2
        ]);
    }
}
