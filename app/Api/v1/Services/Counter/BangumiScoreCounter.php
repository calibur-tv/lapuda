<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/11
 * Time: 下午5:17
 */

namespace App\Api\V1\Services\Counter;

use App\Api\V1\Services\Counter\Base\MigrateCounterService;
use App\Models\Score;

class BangumiScoreCounter extends MigrateCounterService
{
    public function __construct()
    {
        parent::__construct('bangumis', 'scores');
    }

    protected function migration($id)
    {
        $total = Score::where('bangumi_id', $id)
            ->pluck('total');

        if (!count($total))
        {
            return 0;
        }

        $result = 0;
        foreach ($total as $score)
        {
            $result += $score;
        }

        return $result / count($total);
    }
}