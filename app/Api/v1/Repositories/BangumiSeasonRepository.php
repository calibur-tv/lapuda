<?php
/**
 * file description
 *
 * @version
 * @author daryl
 * @date 2019-01-03
 * @since 2019-01-03 description
 */

namespace App\Api\V1\Repositories;

use App\Models\BangumiSeason;
use Illuminate\Support\Facades\Redis;

class BangumiSeasonRepository extends Repository
{
    public function listByBangumiId($bangumiId)
    {
        return $this->Cache('bangumi_season:bangumi:' . $bangumiId, function () use ($bangumiId)
        {
            return BangumiSeason
                ::where('bangumi_id', $bangumiId)
                ->orderBy('rank', 'ASC')
                ->get()
                ->toArray();
        });
    }

    public function updateVideoBySeasonId($seasonId, $useOtherSiteVideo)
    {
        $videos = BangumiSeason
            ::where('id', $seasonId)
            ->pluck('videos')
            ->first();

        BangumiSeason
            ::where('id', $seasonId)
            ->update([
                'other_site_video' => $useOtherSiteVideo
            ]);

        $videoIds = $videos ? explode(',', $videos) : [];
        if (!empty($videoIds))
        {
            Redis::pipeline(function ($pipe) use ($videoIds)
            {
                foreach ($videoIds as $id)
                {
                    $pipe->DEL("video_{$id}");
                }
            });
        }
    }
}