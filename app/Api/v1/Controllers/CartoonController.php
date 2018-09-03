<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 下午8:27
 */

namespace App\Api\V1\Controllers;

use App\Models\Bangumi;
use App\Models\Image;
use Illuminate\Http\Request;

/**
 * @Resource("漫画相关接口")
 */
class CartoonController extends Controller
{
    // 后台展示有漫画的番剧列表
    public function bangumis()
    {
        $bangumis = Bangumi::where('cartoon', '<>', '')
            ->select('id', 'name')
            ->get();

        return $this->resOK($bangumis);
    }

    // 后台展示番剧的漫画列表
    public function listOfBangumi(Request $request)
    {
        $bangumiId = $request->get('id');

        $ids = Bangumi::where('id', $bangumiId)
            ->pluck('cartoon')
            ->first();
        $ids = explode(',', $ids);

        $result = [];
        foreach ($ids as $id)
        {
            $image = Image::where('id', $id)->first();
            if (is_null($image))
            {
                continue;
            }
            $result[] = $image;
        }

        return $this->resOK($result);
    }

    // 后台对番剧的漫画进行排序
    public function sortOfBangumi(Request $request)
    {
        $bangumiId = $request->get('id');

        Bangumi::where('id', $bangumiId)
            ->update([
                'cartoon' => $request->get('cartoon')
            ]);

        return $this->resNoContent();
    }

    // 后台编辑漫画
    public function edit(Request $request)
    {
        Image::where('id', $request->get('id'))
            ->update([
                'name' => $request->get('name'),
                'url' => $request->get('url')
            ]);

        return $this->resOK();
    }
}