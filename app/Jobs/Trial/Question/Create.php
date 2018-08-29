<?php

namespace App\Jobs\Trial\Question;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/22
 * Time: 下午9:30
 */
use App\Api\V1\Repositories\QuestionRepository;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Create implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function handle()
    {
        $questionRepository = new QuestionRepository();
        $question = $questionRepository->item($this->id, true);

        $wordsFilter = new WordsFilter();
        $imageFilter = new ImageFilter();

        $needReview = false;
        $needDelete = false;

        $badWordsCount = $wordsFilter->count($question['title'] . $question['content']);
        $badImageCount = 0;
        foreach ($question['images'] as $image)
        {
            $result = $imageFilter->check($image['url']);
            if ($result['delete'])
            {
                $needDelete = true;
            }
            if ($result['review'])
            {
                $badImageCount++;
            }
        }

        if ($needDelete || $badImageCount + $badWordsCount > 3)
        {
            $needDelete = true;
        }

        if ($needDelete)
        {
            $questionRepository->deleteProcess($this->id, $question['user_id']);
        }
        else if ($needReview)
        {
            $questionRepository->createProcess($this->id, $question['user_id']);
        }
        else
        {
            if ($question['created_at'] !== $question['updated_at'])
            {
                $questionRepository->updateProcess($this->id);
            }
            else
            {
                $questionRepository->createProcess($this->id);
            }
        }
    }
}