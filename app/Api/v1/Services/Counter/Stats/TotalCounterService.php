<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 上午6:31
 */

namespace App\Api\V1\Services\Counter\Stats;

use App\Api\V1\Repositories\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TotalCounterService extends Repository
{
    protected $cacheKey;
    protected $table;
    protected $todayCacheKey;

    public function __construct($table, $modal)
    {
        $this->table = $table;
        $this->cacheKey = 'total_' . $modal . '_stats';
        $this->todayCacheKey = 'total_' . $modal . '_stats_' . strtotime(date('Y-m-d', time()));
    }

    public function get()
    {
        return (int)$this->RedisItem($this->cacheKey, function ()
        {
            if (gettype($this->table) === 'array')
            {
                $result = 0;
                foreach ($this->table as $table)
                {
                    $result += DB::table($table)->count();
                }

                return $result;
            }
            return DB::table($this->table)->count();
        });
    }

    public function today()
    {
        return (int)$this->RedisItem($this->todayCacheKey, function ()
        {
            if (gettype($this->table) === 'array')
            {
                $result = 0;
                foreach ($this->table as $table)
                {
                    $result += DB::table($table)
                        ->where('created_at', '>', Carbon::now()->today())
                        ->count();
                }

                return $result;
            }

            return DB::table($this->table)
                ->where('created_at', '>', Carbon::now()->today())
                ->count();
        });
    }

    public function add($num = 1)
    {
        if (Redis::EXISTS($this->cacheKey))
        {
            Redis::INCRBYFLOAT($this->cacheKey, $num);
        }
        if (Redis::EXISTS($this->todayCacheKey))
        {
            Redis::INCRBYFLOAT($this->todayCacheKey, $num);
        }
    }

    public function remove()
    {
        $this->add(-1);
    }
}