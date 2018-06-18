<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/18
 * Time: 下午9:40
 */

namespace App\Api\V1\Services\Counter;


use Illuminate\Support\Facades\DB;

class CommentCounterService extends CounterService
{
    protected $comment_table;

    public function __construct($comment_table, $comment_field, $modal_table)
    {
        parent::__construct($modal_table, $comment_field);
        $this->comment_table = $comment_table;
    }

    public function migrate($modalId)
    {
        if (!$modalId)
        {
            return false;
        }

        return DB::table($this->comment_table)
            ->where('modal_id', $modalId)
            ->whereNull('deleted_at')
            ->count();
    }
}