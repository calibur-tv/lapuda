<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/3
 * Time: 下午8:14
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Services\Counter\CmLoopClickCounter;
use App\Api\V1\Services\Counter\CmLoopViewCounter;
use App\Models\Looper;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LooperController extends Controller
{
    // 显示所有的轮播
    public function list()
    {
        $list = Looper
            ::withTrashed()
            ->orderBy('updated_at', 'DESC')
            ->get();

        $cmLoopViewCounter = new CmLoopViewCounter();
        $cmLoopClickCounter = new CmLoopClickCounter();

        $list = $cmLoopViewCounter->batchGet($list, 'view_count');
        $list = $cmLoopClickCounter->batchGet($list, 'click_count');

        return $this->resOK($list);
    }

    // 更新某个轮播
    public function update(Request $request)
    {
        $id = $request->get('id');
        Looper
            ::where('id', $id)
            ->update([
                'title' => $request->get('title'),
                'desc' => $request->get('desc'),
                'poster' => $request->get('poster'),
                'link' => $request->get('link'),
                'begin_at' => date('Y-m-d H:m:s', $request->get('begin_at')),
                'end_at' => date('Y-m-d H:m:s', $request->get('end_at'))
            ]);

        return $this->resNoContent();
    }

    // 把某个轮播放到最前面
    public function sort(Request $request)
    {
        $id = $request->get('id');
        Looper
            ::where('id', $id)
            ->update([
                'updated_at' => Carbon::now()
            ]);

        return $this->resNoContent();
    }

    // 下架某个轮播
    public function delete(Request $request)
    {
        $id = $request->get('id');
        Looper::where('id', $id)->delete();

        return $this->resNoContent();
    }

    // 创建某个轮播
    public function add(Request $request)
    {
        $new = Looper
            ::create([
                'title' => $request->get('title'),
                'desc' => $request->get('desc'),
                'poster' => $request->get('poster'),
                'link' => $request->get('link'),
                'begin_at' => date('Y-m-d H:m:s', $request->get('begin_at')),
                'end_at' => date('Y-m-d H:m:s', $request->get('end_at'))
            ]);

        return $this->resOK($new);
    }
}