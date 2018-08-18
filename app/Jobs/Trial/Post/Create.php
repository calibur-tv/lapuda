<?php

namespace App\Jobs\Trial\Post;

use App\Api\V1\Repositories\PostRepository;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Create implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $postId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->postId = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $postRepository = new PostRepository();
        $post = $postRepository->item($this->postId);
        if (is_null($post))
        {
            return;
        }

        $needDelete = false;
        $needTrial = false;

        // 文字审核流程
        $wordsFilter = new WordsFilter();
        if ($wordsFilter->count($post['title'] . $post['content']))
        {
            $needTrial = true;
        }

        // 图片审核流程
        $imageFilter = new ImageFilter();
        foreach ($post['images'] as $image)
        {
            $imageCheckResult = $imageFilter->check($image['url']);
            if ($imageCheckResult['review'])
            {
                $needTrial = true;
            }
            if ($imageCheckResult['delete'])
            {
                $needDelete = true;
            }
        }

        if ($needDelete)
        {
            $postRepository->deleteProcess($this->postId, $post['user_id']);
        }
        else if ($needTrial)
        {
            $postRepository->trialProcess($this->postId, $post['user_id']);
        }
        else
        {
            if ($post['created_at'] !== $post['updated_at'])
            {
                $postRepository->updateProcess($this->postId);
            }
            else
            {
                $postRepository->createProcess($this->postId);
            }
        }
    }
}
