<?php

namespace App\Api\V1\Services\Toggle\Base;
use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Services\Toggle\Post\PostRewardService;
use App\Api\V1\Services\Toggle\ToggleService;
use App\Models\User;
use App\Models\UserCoin;

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

        UserCoin::create([
            'type_id' => $id,
            'from_user_id' => 0,
            'user_id' => $item['user_id'],
            'type' => $this->type,
            'count' => $count
        ]);

        User::where('id', $item['user_id'])->increment('coin_count', -$count);
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
            default:
                return null;
                break;
        }
    }
}