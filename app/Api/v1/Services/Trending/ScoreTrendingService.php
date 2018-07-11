<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午4:48
 */

namespace App\Api\V1\Services\Trending;


class ScoreTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($visitorId = 0, $bangumiId = 0)
    {
        parent::__construct('scores', $bangumiId);

        $this->visitorId = $visitorId;
        $this->bangumiId = $bangumiId;
    }
}