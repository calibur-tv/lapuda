<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/5
 * Time: 上午10:11
 */

namespace App\Api\V1\Services\Counter;

use Illuminate\Support\Facades\DB;

class VoteCountService extends CounterService
{
    protected $voteTable;

    protected $modalId;

    public function __construct($modalTable, $modalField, $voteTable)
    {
        parent::__construct($modalTable, $modalField);

        $this->voteTable = $voteTable;
    }

    public function migrate($modalId)
    {
        if (!$modalId)
        {
            return false;
        }

        return DB::table($this->voteTable)
            ->where('modal_id', $modalId)
            ->sum($this->field);
    }
}