<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/2
 * Time: 下午9:41
 */

namespace App\Api\V1\Services\Toggle\Image;


use App\Api\V1\Services\Toggle\Base\RewardService;
use App\Api\V1\Services\Counter\Base\RelationCounterService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImageRewardService extends RewardService
{
    public function __construct()
    {
        parent::__construct('image_reward', 10);
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