<?php

namespace App\Jobs\Trial\JsonContent;

use App\Api\V1\Repositories\ScoreRepository;
use App\Services\Trial\JsonContentFilter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class TrialScore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $scoreId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->scoreId = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $scoreRepository = new ScoreRepository();
        $score = $scoreRepository->item($this->scoreId);

        $filter = new JsonContentFilter();
        $result = $filter->exec($score['content']);

        if ($result['delete'])
        {
            DB::table('scores')
                ->where('id', $score['id'])
                ->update([
                    'state' => $score['user_id'],
                    'deleted_at' => Carbon::now()
                ]);

            return;
        }
        if ($result['review'])
        {
            DB::table('scores')
                ->where('id', $score['id'])
                ->update([
                    'state' => $score['user_id']
                ]);
        }
    }
}
