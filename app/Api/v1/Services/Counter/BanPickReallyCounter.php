<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 上午9:37
 */

namespace App\Api\V1\Services\Counter;


use App\Api\V1\Services\Counter\Base\RelationCounterService;
use Illuminate\Support\Facades\DB;

class BanPickReallyCounter extends RelationCounterService
{
    public function __construct($table)
    {
        parent::__construct($table, 'really');
    }

    protected function migration($id)
    {
        $count = DB::table($this->table)
            ->where('modal_id', $id)
            ->pluck('score');

        if (!$count)
        {
            return 0;
        }

        $result = 0;
        foreach ($count as $item)
        {
            $result += $item;
        }

        return $result;
    }
}