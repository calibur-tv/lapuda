<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Transformers\PostTransformer;
use Illuminate\Http\Request;

class TrendingController extends Controller
{
    public function postNew(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $repository = new PostRepository();
        $ids = $repository->getNewIds();

        if (empty($ids))
        {
            $list = [];
        }
        else
        {
            $list = $repository->list(array_slice(array_diff($ids, $seen), 0, $take));
        }

        $transformer = new PostTransformer();

        return $this->resOK($transformer->trending($list));
    }

    public function postHot(Request $request)
    {
        $seen = $request->get('seenIds') ? explode(',', $request->get('seenIds')) : [];
        $take = intval($request->get('take')) ?: 10;

        $repository = new PostRepository();
        $ids = $repository->getHotIds();

        if (empty($ids))
        {
            $list = [];
        }
        else
        {
            $list = $repository->list(array_slice(array_diff($ids, $seen), 0, $take));
        }

        $transformer = new PostTransformer();

        return $this->resOK($transformer->trending($list));
    }
}
