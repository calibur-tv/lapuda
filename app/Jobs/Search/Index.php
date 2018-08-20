<?php

namespace App\Jobs\Search;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Counter\Stats\TotalBangumiCount;
use App\Api\V1\Services\Counter\Stats\TotalImageAlbumCount;
use App\Api\V1\Services\Counter\Stats\TotalPostCount;
use App\Api\V1\Services\Counter\Stats\TotalRoleCount;
use App\Api\V1\Services\Counter\Stats\TotalScoreCount;
use App\Api\V1\Services\Counter\Stats\TotalUserCount;
use App\Api\V1\Services\Counter\Stats\TotalVideoCount;
use App\Services\BaiduSearch\BaiduPush;
use App\Services\OpenSearch\Search;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/19
 * Time: 上午8:57
 */
class Index implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;
    protected $model;
    protected $type;
    protected $text;

    public function __construct($type, $model, $id, $text = '')
    {
        if (config('app.env') !== 'production')
        {
            return;
        }

        if (!in_array($type, [
            'C', // create
            'U', // update
            'D'  // delete
        ]))
        {
            return;
        }

        if (!in_array($model, [
            'user',
            'bangumi',
            'video',
            'post',
            'image',
            'score',
            'role'
        ]))
        {
            return;
        }

        $this->id = $id;
        $this->model = $model;
        $this->type = $type;
        $this->text = $text;
    }

    public function handle()
    {
        $search = new Search();
        $baiduPush = new BaiduPush();

        $pushPath = $this->id;
        if ($this->model === 'user')
        {
            $userRepository = new UserRepository();
            $user = $userRepository->item($pushPath);
            $pushPath = $user['zone'];
        }

        if ($this->type === 'C')
        {
            $search->create($this->id, $this->text, $this->model);
            $baiduPush->create($pushPath, $this->model);

            $counter = $this->getTotalCounterByModel();
            if (!is_null($counter))
            {
                $counter->add();
            }
        }
        else if ($this->type === 'U')
        {
            $search->update($this->id, $this->text, $this->model);
            $baiduPush->update($pushPath, $this->model);
        }
        else
        {
            $search->delete($this->id, $this->model);
            $baiduPush->delete($pushPath, $this->model);

            $counter = $this->getTotalCounterByModel();
            if (!is_null($counter))
            {
                $counter->add(-1);
            }
        }
    }

    protected function getTotalCounterByModel()
    {
        switch ($this->model)
        {
            case 'user':
                return new TotalUserCount();
                break;
            case 'bangumi':
                return new TotalBangumiCount();
                break;
            case 'video':
                return new TotalVideoCount();
                break;
            case 'post':
                return new TotalPostCount();
                break;
            case 'image':
                return new TotalImageAlbumCount();
                break;
            case 'score':
                return new TotalScoreCount();
                break;
            case 'role':
                return new TotalRoleCount();
                break;
            default:
                return null;
        }
    }
}