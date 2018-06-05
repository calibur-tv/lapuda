<?php

namespace App\Jobs\Trial\Image;

use App\Models\Image;
use App\Services\Trial\ImageFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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
        $url = Image::where('id', $this->imageId)->pluck('url')->first();

        $imageFilter = new ImageFilter();
        $badImageCount = $imageFilter->exec($url);

        $state = 1;
        if ($badImageCount > 0)
        {
            $state = 2;
        }

        Image::where('id', $this->imageId)
            ->update([
                'state' => $state
            ]);
    }
}
