<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/23
 * Time: 下午10:41
 */

namespace App\Api\V1\Services\Toggle\Question;


use App\Api\V1\Services\Toggle\Base\RewardService;
use App\Api\V1\Services\Counter\Base\RelationCounterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnswerRewardService extends RewardService
{
    public function __construct()
    {
        parent::__construct('answer_reward', 12);
    }

    public function do($userId, $modalId, $count = 1)
    {
        $id = DB::table($this->table)
            ->insertGetId([
                'user_id' => $userId,
                'modal_id' => $modalId,
                'created_at' => Carbon::now(),
                'migration_state' => 2
            ]);

        $relationCounterService = new RelationCounterService($this->table);
        $relationCounterService->add($modalId, $count);

        $this->SortAdd($this->doUsersCacheKey($modalId), $userId);
        $this->SortAdd($this->usersDoCacheKey($userId), $modalId);

        return $id;
    }
}