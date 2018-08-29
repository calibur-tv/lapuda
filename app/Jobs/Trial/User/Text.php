<?php

namespace App\Jobs\Trial\User;

use App\Api\V1\Repositories\UserRepository;
use App\Models\User;
use App\Services\Trial\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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
        $userRepository = new UserRepository();
        $user = $userRepository->item($this->userId, true);

        $nickname = $user['nickname'];
        $signature = $user['signature'];

        $filter = new WordsFilter();
        $nameCount = $filter->count($nickname);
        $wordCount = $filter->count($signature);

        if ($nameCount + $wordCount > 0)
        {
            if ($nameCount > 1 || $wordCount > 1)
            {
                $nickname = '未命名';
                $signature = '签名什么的不重要';

                User::where('id', $this->userId)
                    ->update([
                        'state' => $this->userId,
                        'nickname' => $nickname,
                        'signature' => $signature
                    ]);
            }
            else
            {
                User::where('id', $this->userId)
                    ->update([
                        'state' => 1
                    ]);
            }
        }

        $userRepository->migrateSearchIndex('U', $this->userId, false);
    }
}
