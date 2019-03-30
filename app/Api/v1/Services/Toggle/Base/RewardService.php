<?php

namespace App\Api\V1\Services\Toggle\Base;

use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Services\Toggle\ToggleService;
use App\Api\V1\Services\Toggle\Video\VideoRewardService;
use App\Api\V1\Services\VirtualCoinService;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/17
 * Time: 下午4:23
 */
class RewardService extends ToggleService
{
    protected $type;

    public function __construct($table, $type)
    {
        parent::__construct($table);
        $this->type = $type;
    }

    public function cancel($id)
    {
        $repository = $this->getRepositoryByType();
        if (is_null($repository))
        {
            return;
        }

        $item = $repository->item($id, true);
        if (is_null($item) || $item['is_creator'] == 0)
        {
            return;
        }

        $count = $this->total($id);
        if (!$count)
        {
            return;
        }

        $virtualCoinService = new VirtualCoinService();
        $virtualCoinService->deleteUserContent($this->convertNumberTypeToString(), $item['user_id'], $item['id'], $count);
    }

    protected function getRepositoryByType()
    {
        switch ($this->type)
        {
            case 9:
                return new PostRepository();
                break;
            case 10:
                return new ImageRepository();
                break;
            case 11:
                return new ScoreRepository();
                break;
            case 12:
                return new AnswerRepository();
                break;
            case 14:
                return new VideoRewardService();
            default:
                return null;
                break;
        }
    }

    protected function convertNumberTypeToString()
    {
        switch ($this->type)
        {
            case 9:
                return 'post';
                break;
            case 10:
                return 'image';
                break;
            case 11:
                return 'score';
                break;
            case 12:
                return 'answer';
                break;
            case 14:
                return 'video';
            default:
                return '';
                break;
        }
    }
}