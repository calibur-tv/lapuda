<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearSearch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ClearSearch';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'clear job';


    protected $ids;
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ids = $this->getDeleteIds();

        if (empty($ids))
        {
            return true;
        }

        $this->ids = $ids;

        while (!empty($this->ids))
        {
            DB::table('search_v3')
                ->whereIn('id', $this->ids)
                ->delete();

            $this->ids = $this->getDeleteIds();
        }

        return true;
    }

    protected function getDeleteIds()
    {
        $ids = DB
            ::table('search_v3')
            ->select(DB::raw('MIN(id) AS id'))
            ->groupBy(['type_id', 'modal_id'])
            ->havingRaw('COUNT(id) > 1')
            ->pluck('id')
            ->toArray();

        return $ids;
    }
}