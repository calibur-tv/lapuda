<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/23
 * Time: 上午6:53
 */

namespace App\Jobs\Search;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class Delete implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ids;
    protected $modelId;

    public function __construct($ids, $modelId)
    {
        $this->ids = $ids;
        $this->modelId = $modelId;
    }

    public function handle()
    {
        DB
            ::table('search_v3')
            ->where('modal_id', $this->modelId)
            ->whereIn('type_id', $this->ids)
            ->delete();
    }
}