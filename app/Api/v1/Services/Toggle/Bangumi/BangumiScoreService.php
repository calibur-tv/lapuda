<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午4:07
 */

namespace App\Api\V1\Services\Toggle\Bangumi;

use App\Api\V1\Services\Toggle\ToggleService;

class BangumiScoreService extends ToggleService
{
    public function __construct()
    {
        parent::__construct('scores', 'bangumi_score_users');
    }
}