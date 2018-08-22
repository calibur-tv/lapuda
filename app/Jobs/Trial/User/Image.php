<?php

namespace App\Jobs\Trial\User;

use App\Api\V1\Repositories\UserRepository;
use App\Models\User;
use App\Services\BaiduSearch\BaiduPush;
use App\Services\Trial\ImageFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

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
        $userRepository = new UserRepository();
        $user = $userRepository->item($this->userId, true);

        if (is_null($user))
        {
            return;
        }

        $url = $user[$this->type];

        $imageFilter = new ImageFilter();
        if ($imageFilter->bad($url))
        {
            DB::table('users')
                ->where('id', $this->userId)
                ->update([
                    'state' => $this->userId
                ]);
        }

        $baiduPush = new BaiduPush();
        $baiduPush->update($user['zone'], 'user');
    }
}
