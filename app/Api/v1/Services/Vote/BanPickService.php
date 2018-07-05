<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/5
 * Time: 下午5:13
 */

namespace App\Api\V1\Services\Vote;


class BanPickService
{
    /**
     * BanPickService 非ban即选服务，如知乎的点赞.
     * 用 1 和 -1 标记 pick 和 ban，代表 赞同 和 不赞同
     * 但是显示的时候，只显示加的分数总和，不显示真实分数，因此需要两个 field
     */
    protected $table;
    protected $show_field;
    protected $really_field;
    protected $count;

    public function __construct($table, $showField, $reallyField, $count = 1)
    {
        $this->table = $table;
        $this->show_field = $showField;
        $this->really_field = $reallyField;
        $this->count = $count;
    }

    public function pick()
    {

    }

    public function ban()
    {

    }
}