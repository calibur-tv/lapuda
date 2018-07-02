<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 下午5:55
 */

namespace App\Api\V1\Controllers;

use App\Models\Tag;

use Illuminate\Support\Facades\Cache;

class TagController extends Controller
{
    public function all()
    {
        $result = Cache::remember('tag_all', 60, function ()
        {
            return Tag::select('id', 'name', 'model')
                ->orderBy('id', 'DESC')
                ->get()
                ->toArray();
        });

        return $this->resOK($result);
    }
}