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

class CartoonController extends Controller
{
    public function bangumis()
    {
        $bangumis = Bangumi::where('cartoon', '<>', '')
            ->select('id', 'name')
            ->get();

        return $this->resOK($bangumis);
    }

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

    public function sortOfBangumi(Request $request)
    {
        $bangumiId = $request->get('id');

        Bangumi::where('id', $bangumiId)
            ->update([
                'cartoon' => $request->get('cartoon')
            ]);

        return $this->resNoContent();
    }

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