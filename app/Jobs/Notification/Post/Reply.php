<?php

namespace App\Jobs\Notification\Post;

use App\Api\V1\Repositories\PostRepository;
use App\Models\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Reply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $replyId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->replyId = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $repository = new PostRepository();
        $reply = $repository->item($this->replyId);
        $post = $repository->item($reply['parent_id']);
        if (is_null($reply) || is_null($post))
        {
            return;
        }

        Notifications::create([
            'from_user_id' => $reply['user_id'],
            'to_user_id' => $reply['target_user_id'],
            'about_id' => $reply['id'] . ',' . $post['id'],
            'type' => 1
        ]);
    }
}
