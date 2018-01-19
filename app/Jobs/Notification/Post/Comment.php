<?php

namespace App\Jobs\Notification\Post;

use App\Api\V1\Repositories\PostRepository;
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
        $repository = new PostRepository();
        $comment = $repository->item($this->commentId);
        $reply = $repository->item($comment['parent_id']);
        $post = $repository->item($reply['parent_id']);

        if(is_null($comment) || is_null($reply) || is_null($post))
        {
            return;
        }

        Notifications::create([
            'from_user_id' => $comment['user_id'],
            'to_user_id' => $comment['target_user_id'],
            'about_id' => $comment['id'] . ',' . $reply['id'] . ',' . $post['id'],
            'type' => 2
        ]);
    }
}
