<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/16
 * Time: 下午9:51
 */

namespace App\Api\V1\Services\Counter\Stats;


use App\Api\V1\Services\Counter\Base\TotalCounterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TotalImageAlbumCount extends TotalCounterService
{
    public function __construct()
    {
        parent::__construct('images', 'image_album');
    }

    protected function computeTotal($table)
    {
        return DB::table($table)
            ->where('is_cartoon', 0)
            ->where('is_album', 1)
            ->count();
    }

    protected function computeToday($table)
    {
        return DB::table($table)
            ->where('is_cartoon', 0)
            ->where('is_album', 1)
            ->where('created_at', '>', Carbon::now()->today())
            ->count();
    }
}