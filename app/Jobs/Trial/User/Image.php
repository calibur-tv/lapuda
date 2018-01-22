<?php

namespace App\Jobs\Trial\User;

use App\Api\V1\Repositories\UserRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Image implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $type;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id, $type)
    {
        $this->userId = $id;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $repository = new UserRepository();
        $user = $repository->item($this->userId);

        $badImageCount = 0;
        $needDelete = false;
        $state = 0;
        $url = $user[$this->type];

        // 色情
        $respSex = json_decode(file_get_contents(env('website.image') . $url . '?qpulp'), true);
        if ($respSex['code'] != 0)
        {
            $badImageCount++;
        }
        else
        {
            if ($respSex['result']['label'] == 1)
            {
                $badImageCount++;
            }
            else if ($respSex['result']['label'] == 0)
            {
                $badImageCount++;
                $needDelete = true;
            }
            if ($respSex['result']['review'] == true)
            {
                $needDelete = true;
            }
        }
        // 暴恐
        $respWarn = json_decode(file_get_contents(env('website.image') . $url . '?qterror'), true);
        if ($respWarn['code'] != 0)
        {
            $badImageCount++;
        }
        else
        {
            if ($respWarn['result']['label'] == 1)
            {
                $badImageCount++;
            }
            if ($respWarn['result']['review'] == true)
            {
                $needDelete = true;
            }
        }
        // 政治敏感
        $respDaddy = json_decode(file_get_contents(env('website.image') . $url . '?qpolitician'), true);
        if ($respDaddy['code'] != 0)
        {
            $badImageCount++;
        }
        else
        {
            if ($respDaddy['result']['review'] == true)
            {
                $needDelete = true;
            }
        }

        if ($needDelete || $badImageCount)
        {
            $state = 1;
        }

        if ($state && $needDelete)
        {
            DB::table('users')
                ->where('id', $this->userId)
                ->update([
                    'state' => $state,
                    $this->type = ''
                ]);
        }
        else if ($state)
        {
            DB::table('users')
                ->where('id', $this->userId)
                ->update([
                    'state' => $state
                ]);
        }

        if ($needDelete)
        {
            Redis::DEL('user'.$this->userId);
        }
    }
}
