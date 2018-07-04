<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 下午5:55
 */

namespace App\Api\V1\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

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

    public function edit(Request $request)
    {
        $id = $request->get('id');

        Tag::where('id', $id)
            ->update([
                'name' => $request->get('name')
            ]);

        Redis::DEL('tag_all');

        return $this->resNoContent();
    }

    public function create(Request $request)
    {
        $id = Tag::insertGetId([
            'name' => $request->get('name'),
            'model' => $request->get('model')
        ]);

        Redis::DEL('tag_all');

        return $this->resCreated($id);
    }
}