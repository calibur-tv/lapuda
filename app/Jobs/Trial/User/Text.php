<?php

namespace App\Jobs\Trial\User;

use App\Api\V1\Repositories\UserRepository;
use App\Models\MixinSearch;
use App\Models\User;
use App\Services\Trial\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;

class Text implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->userId = $id;
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

        $filter = new WordsFilter();
        $nameCount = $filter->count($user['nickname']);
        $wordCount = $filter->count($user['signature']);

        if ($nameCount + $wordCount > 1)
        {
            User::where('id', $this->userId)
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
                    'updated_at' => time(),
                    'content' => $user['signature'],
                    'title' => $user['nickname']
                ]);
            }
        }
    }
}
