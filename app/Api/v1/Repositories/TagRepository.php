<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/11/18
 * Time: 上午8:09
 */

namespace App\Api\V1\Repositories;

use App\Models\Tag;
use Illuminate\Support\Facades\Cache;

class TagRepository extends Repository
{
    public function all($modal)
    {
        return $this->Cache('bangumi_tags_all', function () use ($modal)
        {
            return Tag::where('model', $modal)
                ->select('id', 'name')
                ->get()
                ->toArray();
        });
    }
}