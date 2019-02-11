<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Tag\Base\UserBadgeService;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use Illuminate\Console\Command;

class SetPortecterBadge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SetPortecterBadge';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'set protecter badge';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $list = CartoonRoleFans
            ::select(DB::raw('SUM(cartoon_role_fans.star_count) as count, role_id'))
            ->orderBy('count', 'DESC')
            ->groupBy('role_id')
            ->havingRaw('count > 100')
            ->pluck('count', 'role_id')
            ->toArray();

        $i = 0;
        $userRepository = new UserRepository();
        $cartoonRoleRepository = new CartoonRoleRepository();
        $userBadgeService = new UserBadgeService();
        $lovers = [];
        foreach ($list as $idol_id => $star_count)
        {
            $i++;
            $idol = $cartoonRoleRepository->item($idol_id);
            if (is_null($idol))
            {
                continue;
            }
            $data = CartoonRoleFans
                ::where('role_id', $idol_id)
                ->orderBy('star_count', 'DESC')
                ->select('user_id', 'star_count')
                ->first();
            $lover_id = $data->user_id;
            $star = $data->star_count;
            $lover = $userRepository->item($lover_id);
            if (is_null($lover))
            {
                continue;
            }
            if (!in_array($lover_id, $lovers))
            {
                $userBadgeService->setUserBadge(
                    $lover_id,
                    6,
                    "在初代守护者争夺战中，该用户为「{$idol['name']}」应援了「{$star}」个团子，使其位居偶像排行榜第「{$i}」位。此荣耀将永存于世，感谢有你，尊敬的守护者！"
                );
                $lovers[] = $lover_id;
            }
            $userIds = CartoonRole
                ::where('role_id', $idol_id)
                ->where('user_id', '<>', $lover_id)
                ->where('star_count', '>=', 30)
                ->orderBy('star_count', 'DESC')
                ->pluck('user_id')
                ->toArray();
            foreach ($userIds as $uid)
            {
                $userBadgeService->setUserBadge($uid, 7);
            }
        }

        return true;
    }
}