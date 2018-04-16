<?php

namespace App\Jobs\Notification\Image;

use App\Api\V1\Repositories\ImageRepository;
use App\Models\ImageLike;
use App\Models\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Like implements ShouldQueue
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
        $like = ImageLike::find($this->likeId);

        if (is_null($like))
        {
            return;
        }

        $repository = new ImageRepository();
        $image = $repository->item($like['image_id']);

        if (is_null($image))
        {
            return;
        }

        Notifications::create([
            'from_user_id' => $like['user_id'],
            'to_user_id' => $image['user_id'],
            'about_id' => $image['id'],
            'type' => 5
        ]);
    }
}
