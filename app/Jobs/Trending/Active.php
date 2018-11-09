<?php

namespace App\Jobs\Trending;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/21
 * Time: 下午7:45
 */
use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Services\Trending\AnswerTrendingService;
use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Services\Trending\PostTrendingService;
use App\Api\V1\Services\Trending\QuestionTrendingService;
use App\Api\V1\Services\Trending\ScoreTrendingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class Active implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;
    protected $type;
    protected $bangumiId;

    public function __construct($id, $type, $bangumiId)
    {
        $this->id = $id;
        $this->type = $type;
        $this->bangumiId = $bangumiId;
    }

    public function handle()
    {
        $service = $this->getTrendingServiceByModel();
        if (is_null($service))
        {
            return;
        }

        $table = $this->getTableByModel();
        if (!$table)
        {
            return;
        }

        DB::table($table)
            ->where('id', $this->id)
            ->update([
                'updated_at' => Carbon::now()
            ]);

        $service->update($this->id);
    }

    protected function getTableByModel()
    {
        switch ($this->type)
        {
            case 'post':
                return 'posts';
                break;
            case 'video':
                return 'videos';
                break;
            case 'image':
                return 'images';
                break;
            case 'score':
                return 'scores';
                break;
            case 'question':
                return 'questions';
                break;
            case 'answer':
                return 'question_answers';
                break;
            default:
                return '';
                break;
        }
    }

    protected function getTrendingServiceByModel()
    {
        switch ($this->type)
        {
            case 'post':
                return new PostTrendingService($this->bangumiId);
                break;
            case 'video':
                return null;
                break;
            case 'image':
                return new ImageTrendingService($this->bangumiId);
                break;
            case 'score':
                return new ScoreTrendingService($this->bangumiId);
                break;
            case 'question':
                return new QuestionTrendingService($this->bangumiId);
                break;
            case 'answer':
                return new AnswerTrendingService($this->bangumiId);
                break;
            default:
                return null;
                break;
        }
    }

    protected function getRepositoryByType()
    {
        switch ($this->type)
        {
            case 'post':
                return new PostRepository();
                break;
            case 'video':
                return null;
                break;
            case 'image':
                return new ImageRepository();
                break;
            case 'score':
                return new ScoreRepository();
                break;
            case 'question':
                return new QuestionRepository();
                break;
            case 'answer':
                return new AnswerRepository();
                break;
            default:
                return null;
                break;
        }
    }
}