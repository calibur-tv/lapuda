<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 下午8:27
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Models\Bangumi;
use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * @Resource("漫画相关接口")
 */
class CartoonController extends Controller
{
    // 后台展示有漫画的番剧列表
    public function bangumis()
    {
        $ids = Image
            ::where('is_cartoon', 1)
            ->groupBy('bangumi_id')
            ->pluck('bangumi_id')
            ->toArray();

        $bangumiRepository = new BangumiRepository();
        $list = $bangumiRepository->list($ids);

        return $this->resOK($list);
    }

    // 后台编辑漫画
    public function edit(Request $request)
    {
        $id = $request->get('id');

        Image::where('id', $id)
            ->update([
                'name' => $request->get('name')
            ]);

        Redis::DEL('image_' . $id);

        return $this->resOK();
    }
}