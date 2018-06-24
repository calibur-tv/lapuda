<?php

namespace App\Jobs\Search\User;

use App\Api\V1\Repositories\UserRepository;
use App\Services\OpenSearch\Search;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Register implements ShouldQueue
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
        $user = $userRepository->item($this->userId);

        $searchService = new Search();
        $searchService->create(
            $user['id'],
            $user['nickname'] . ',' . $user['zone'],
            'user'
        );
    }
}
