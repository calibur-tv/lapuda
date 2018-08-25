<?php

namespace App\Jobs\Trial\Answer;

use App\Api\V1\Repositories\AnswerRepository;
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

    protected $answerId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->answerId = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $answerRepository = new AnswerRepository();
        $answer = $answerRepository->item($this->answerId);

        $filter = new JsonContentFilter();
        $wordsFilter = new WordsFilter();
        $result = $filter->exec($answer['content']);

        $needDelete = false;
        $needTrial = false;

        if ($result['delete'])
        {
            $needDelete = true;
        }
        if ($result['review'] || $wordsFilter->count($answer['title'] . $answer['intro']) > 0)
        {
            $needTrial = true;
        }

        if ($needDelete)
        {
            $answerRepository->deleteProcess($this->answerId, $answer['user_id']);
        }
        else if ($needTrial)
        {
            $answerRepository->createProcess($this->answerId, $answer['user_id']);
        }
        else
        {
            if ($answer['created_at'] !== $answer['updated_at'])
            {
                $answerRepository->updateProcess($this->answerId);
            }
            else
            {
                $answerRepository->createProcess($this->answerId);
            }
        }
    }
}
