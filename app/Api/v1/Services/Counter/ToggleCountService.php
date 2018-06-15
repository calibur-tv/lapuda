<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 下午4:22
 */

namespace App\Api\V1\Services\Counter;


use Illuminate\Support\Facades\DB;

class ToggleCountService extends CounterService
{
    protected $toggleTable;

    protected $modalId;

    public function __construct($modalTable, $modalField, $toggleTable)
    {
        parent::__construct($modalTable, $modalField);

        $this->toggleTable = $toggleTable;
    }

    public function migrate($modalId)
    {
        if (!$modalId)
        {
            return false;
        }
        return DB::table($this->toggleTable)
            ->where('modal_id', $modalId)
            ->count();
    }
}