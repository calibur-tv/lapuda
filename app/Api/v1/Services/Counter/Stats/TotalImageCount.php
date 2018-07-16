<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 上午6:45
 */

namespace App\Api\V1\Services\Counter\Stats;

use App\Api\V1\Services\Counter\Base\TotalCounterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TotalImageCount extends TotalCounterService
{
    public function __construct()
    {
        parent::__construct('images', 'image');
    }

    protected function computeTotal($table)
    {
        $normalImages = DB::table('images')
            ->where('is_album', 0)
            ->count();

        $albumImage = DB::table('album_images')
            ->count();

        return $normalImages + $albumImage;
    }

    protected function computeToday($table)
    {
        $normalImages = DB::table('images')
            ->where('is_album', 0)
            ->where('created_at', '>', Carbon::now()->today())
            ->count();

        $albumImage = DB::table('album_images')
            ->where('created_at', '>', Carbon::now()->today())
            ->count();

        return $normalImages + $albumImage;
    }
}