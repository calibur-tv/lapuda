<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\PostTransformer;
use Illuminate\Http\Request;

/**
 * @Resource("排行相关接口")
 */
class TrendingController extends Controller
{
    public function cartoonRole(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $repository = new CartoonRoleRepository();

        $ids = array_slice(array_diff($repository->trendingIds(), $seen), 0, config('website.list_count'));

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $repository->trendingItem($id);
        }

        $transformer = new CartoonRoleTransformer();

        return $this->resOK($transformer->trending($result));
    }
}
