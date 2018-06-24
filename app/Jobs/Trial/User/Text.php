<?php

namespace App\Jobs\Trial\User;

use App\Models\User;
use App\Services\OpenSearch\Search;
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
        $user = User::find($this->userId);

        $filter = new WordsFilter();
        $nameCount = $filter->count($user->nickname);
        $wordCount = $filter->count($user->signature);

        if ($nameCount + $wordCount > 1)
        {
            User::where('id', $this->userId)
                ->update([
                    'state' => 1
                ]);
        }
        else
        {
            $searchService = new Search();
            $searchService->update(
                $user->id,
                $user->nickname . ',' . $user->zone,
                'user'
            );
        }
    }
}
