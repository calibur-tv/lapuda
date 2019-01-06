<?php
/**
 * file description
 *
 * @version
 * @author daryl
 * @date 2019-01-03
 * @since 2019-01-03 description
 */

namespace App\Api\v1\Repositories;

use App\Models\BangumiSeason;

class BangumiSeasonRepository extends Repository
{
    public function listByBangumiId($bangumiId)
    {
        return $this->Cache('bangumi_season:bangumi:' . $bangumiId, function () use ($bangumiId) {
            return BangumiSeason::where('bangumi_id', $bangumiId)->orderBy('rank')->get()->toArray();
        });
    }
}