<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 下午4:20
 */

namespace App\Api\V1\Services\Trending;


use App\Api\V1\Services\Trending\Base\TrendingService;

class QuestionTrendingService extends TrendingService
{
    protected $visitorId;
    protected $bangumiId;

    public function __construct($bangumiId = 0, $userId = 0)
    {
        parent::__construct('questions', $bangumiId, $userId);
    }
}