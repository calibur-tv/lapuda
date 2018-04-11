<?php

namespace App\Jobs\Trial\Image;

use App\Models\Image;
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

        $state = 1;

        // 色情
        $respSex = json_decode(file_get_contents($url . '?qpulp'), true);
        if (intval($respSex['code']) !== 0)
        {
            $state = 2;
        }
        else
        {
            $label = intval($respSex['result']['label']);
            $review = (boolean)$respSex['result']['review'];
            if ($label === 0 || $review === true)
            {
                $state = 2;
            }
        }

        // 暴恐
        $respWarn = json_decode(file_get_contents($url . '?qterror'), true);
        if (intval($respWarn['code']) !== 0)
        {
            $state = 2;
        }
        else
        {
            $label = intval($respSex['result']['label']);
            $review = (boolean)$respSex['result']['review'];
            if ($label === 1 || $review)
            {
                $state = 2;
            }
        }

        // 政治敏感
        $respDaddy = json_decode(file_get_contents($url . '?qpolitician'), true);
        if (intval($respDaddy['code']) !== 0)
        {
            $state = 2;
        }
        else if ((boolean)$respDaddy['result']['review'] === true)
        {
            $state = 2;
        }

        Image::where('id', $this->imageId)
            ->update([
                'state' => $state
            ]);
    }
}
