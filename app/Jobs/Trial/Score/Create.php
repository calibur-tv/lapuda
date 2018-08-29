<?php

namespace App\Jobs\Trial\Score;

use App\Api\V1\Repositories\ScoreRepository;
use App\Services\Trial\JsonContentFilter;
use App\Services\Trial\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Create implements ShouldQueue
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
        $wordsFilter = new WordsFilter();
        $result = $filter->exec($score['content']);

        $needDelete = false;
        $needTrial = false;

        if ($result['delete'])
        {
            $needDelete = true;
        }
        if ($result['review'] || $wordsFilter->count($score['title'] . $score['intro']) > 0)
        {
            $needTrial = true;
        }

        if ($needDelete)
        {
            $scoreRepository->deleteProcess($this->scoreId, $score['user_id']);
        }
        else if ($needTrial)
        {
            $scoreRepository->createProcess($this->scoreId, $score['user_id']);
        }
        else
        {
            if ($score['created_at'] !== $score['updated_at'])
            {
                $scoreRepository->updateProcess($this->scoreId);
            }
            else
            {
                $scoreRepository->createProcess($this->scoreId);
            }
        }
    }
}
