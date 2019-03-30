<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Services\Vote\IdolVoteService;
use App\Models\CartoonRole;
use App\Models\VirtualIdolOwner;
use App\Models\VirtualIdolPriceDraft;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class AutoChangeMarketStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AutoChangeMarketStock';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'auto change market stock';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $drafts = VirtualIdolPriceDraft
            ::where('result', 0)
            ->get()
            ->toArray();

        $cartoonRoleRepository = new CartoonRoleRepository();
        $idolVoteService = new IdolVoteService();
        $PASS_LINE = 0.5;
        foreach ($drafts as $item)
        {
            $idol = $cartoonRoleRepository->item($item['idol_id']);
            if (is_null($idol))
            {
                continue;
            }

            $starCount = $idol['star_count'];
            $agreeUsersId = $idolVoteService->agreeUsersId($item['id']);
            $bannedUsersId = $idolVoteService->bannedUsersId($item['id']);
            $agreeAmount = 0;
            $bannedAmount = 0;
            $pass = false;
            $ban = false;

            if (count($agreeUsersId))
            {
                // 赞同票数
                $agreeAmount = VirtualIdolOwner
                    ::where('idol_id', $item['idol_id'])
                    ->whereIn('user_id', $agreeUsersId)
                    ->sum('stock_count');

                if ($agreeAmount / $starCount > $PASS_LINE)
                {
                    $pass = true;
                }
            }

            if (!$pass)
            {
                if (count($bannedUsersId))
                {
                    // 反对票数
                    $bannedAmount = VirtualIdolOwner
                        ::where('idol_id', $item['idol_id'])
                        ->whereIn('user_id', $bannedUsersId)
                        ->sum('stock_count');

                    if ($bannedAmount / $starCount > $PASS_LINE)
                    {
                        $ban = true;
                    }
                }
            }

            // 超过两天了
            if (!$ban && !$pass && strtotime($item['created_at']) < strtotime('2 day ago'))
            {
                // 有人投票
                if (count($agreeUsersId) || count($bannedUsersId))
                {
                    if ($agreeAmount / ($agreeAmount + $bannedAmount) > $PASS_LINE)
                    {
                        $pass = true;
                    }
                    if ($bannedAmount / ($agreeAmount + $bannedAmount) > $PASS_LINE)
                    {
                        $ban = true;
                    }
                }
            }

            if ($pass)
            {
                VirtualIdolPriceDraft
                    ::where('id', $item['id'])
                    ->update([
                        'result' => 1
                    ]);

                $oldPrice = $idol['max_stock_count'];
                if (intval($oldPrice) == 0)
                {
                    $oldPrice = $idol['star_count'];
                }

                CartoonRole
                    ::where('id', $item['idol_id'])
                    ->update([
                        'stock_price' => $item['stock_price'],
                        'max_stock_count' => floatval($oldPrice + $item['add_stock_count'])
                    ]);

                Redis::DEL($cartoonRoleRepository->idolItemCacheKey($item['idol_id']));
                Redis::DEL($cartoonRoleRepository->lastIdolMarketPriceDraftCacheKey($item['idol_id']));
            }
            if ($ban)
            {
                VirtualIdolPriceDraft
                    ::where('id', $item['id'])
                    ->update([
                        'result' => 2
                    ]);

                Redis::DEL($cartoonRoleRepository->lastIdolMarketPriceDraftCacheKey($item['idol_id']));
            }
        }

        return true;
    }
}