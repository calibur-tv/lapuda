<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 下午5:55
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Services\Tag\BangumiTagService;
use Illuminate\Http\Request;

class TagController extends Controller
{
    // 后台获取所有的 tag
    public function all(Request $request)
    {
        $type = $request->get('type');
        $result = [];
        if ($type === 'bangumi')
        {
            $tagService = new BangumiTagService();
            $result = $tagService->all();
        }

        return $this->resOK($result);
    }

    // 后台编辑 tag
    public function edit(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $name = $request->get('name');

        if ($type === 'bangumi')
        {
            $tagService = new BangumiTagService();
            $tagService->updateTag($id, $name);
        }

        return $this->resNoContent();
    }

    // 后台创建 tag
    public function create(Request $request)
    {
        $type = $request->get('type');
        $name = $request->get('name');
        $newId = 0;

        if ($type === 'bangumi')
        {
            $tagService = new BangumiTagService();
            $newId = $tagService->createTag($name);
        }

        return $this->resCreated($newId);
    }
}