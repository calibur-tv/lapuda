<?php

namespace App\Jobs\Trial\User;

use App\Api\V1\Repositories\UserRepository;
use App\Models\MixinSearch;
use App\Services\Trial\ImageFilter;
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

        $url = $user[$this->type];

        $imageFilter = new ImageFilter();
        $badImageCount = $imageFilter->exec($url);

        if ($badImageCount > 0)
        {
            DB::table('users')
                ->where('id', $this->userId)
                ->update([
                    'state' => 1
                ]);
        }
        else
        {
            $searchId = MixinSearch::whereRaw('type_id = ? and modal_id = ?', [1, $this->userId])
                ->pluck('id')
                ->first();

            if (!is_null($searchId))
            {
                MixinSearch::where('id', $searchId)->increment('score');
                MixinSearch::where('id', $searchId)->update([
                    'updated_at' => time()
                ]);
            }
        }
    }
}
