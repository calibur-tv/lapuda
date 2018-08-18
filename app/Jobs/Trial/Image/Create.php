<?php

namespace App\Jobs\Trial\Image;

use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Services\Counter\Stats\TotalImageCount;
use App\Models\AlbumImage;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class Create implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $imageId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->imageId = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $imageRepository = new ImageRepository();
        $image = $imageRepository->item($this->imageId);

        $imageFilter = new ImageFilter();
        $result = $imageFilter->check($image['url']);

        $needDelete = false;
        $needTrial = false;
        if ($result['delete'])
        {
            $needDelete = true;
        }

        $wordsFilter = new WordsFilter();
        $badWordsCount = $wordsFilter->count($image['name']);

        if ($result['review'] || $badWordsCount > 0)
        {
            $needTrial = true;
        }

        if ($needDelete)
        {
            $imageRepository->deleteProcess($this->imageId, $image['user_id']);
        }
        else if ($needTrial)
        {
            $imageRepository->trialProcess($this->imageId, $image['user_id']);
        }
        else
        {
            if ($image['updated_at'] !== $image['created_at'])
            {
                $imageRepository->updateProcess($this->imageId);
            }
            else
            {
                $imageRepository->createProcess($this->imageId);
            }
        }
    }
}
