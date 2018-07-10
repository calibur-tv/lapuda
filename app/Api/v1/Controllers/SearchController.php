<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\UserRepository;
use Illuminate\Http\Request;
use App\Services\OpenSearch\Search;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("搜索相关接口")
 */
class SearchController extends Controller
{
    /**
     * 根据关键字搜索番剧
     *
     * @Get("/search/index")
     *
     * @Parameters({
     *      @Parameter("q", description="关键字", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧的 url"})
     * })
     */
    // TODO：支持更多类型的搜索
    // TODO：番剧不要只返回 url，还要返回其它信息
    public function index(Request $request)
    {
        $key = $request->get('q');
        if (!$key)
        {
            return $this->resOK();
        }

        $search = new Search();
        $result = $search->index($request->get('q'));

        return $this->resOK(empty($result) ? '' : '/bangumi/' . $result[0]['fields']['type_id']);
    }

    public function search(Request $request)
    {
        $key = Purifier::clean($request->get('q'));

        if (!$key)
        {
            return $this->resOK();
        }

        $type = intval($request->get('type')) ?: 0;
        $page = intval($request->get('page')) ?: 0;

        $search = new Search();
        $result = $search->retrieve($key, $type, $page);

        return $this->resOK($result);
    }

    public function bangumis()
    {
        $bangumiRepository = new BangumiRepository();

        return $this->resOK($bangumiRepository->searchAll());
    }

    public function getUserInfo(Request $request)
    {
        $zone = $request->get('zone');
        $userId = $request->get('id');
        if (!$zone && !$userId)
        {
            return $this->resErrBad();
        }

        $userRepository = new UserRepository();
        if (!$userId)
        {
            $userId = $userRepository->getUserIdByZone($zone, true);
        }

        if (!$userId)
        {
            return $this->resErrNotFound();
        }

        return $this->resOK($userRepository->item($userId, true));
    }

    public function migration(Request $request)
    {
        $id = $request->get('id');

        if ($id == 1)
        {
            $imageTags = DB::table('tags')
                ->where('model', '<>', 0)
                ->get()
                ->toArray();

            foreach ($imageTags as $tag)
            {
                DB::table('image_v2_tags')
                    ->insert([
                        'id' => $tag->id,
                        'name' => $tag->name
                    ]);
            }
        }

        if ($id == 2)
        {
            $tagRelations = DB::table('image_tags')
                ->get()
                ->toArray();

            foreach ($tagRelations as $tag)
            {
                DB::table('image_tag_relations')
                    ->insert([
                        'model_id' => $tag->image_id,
                        'tag_id' => $tag->tag_id
                    ]);
            }
        }

        if ($id == 3)
        {
            $normalImages = DB::table('images')
                ->whereRaw('album_id = 0 and image_count = 0', [])
                ->get()
                ->toArray();

            foreach ($normalImages as $image)
            {
                DB::table('images_v2')
                    ->insert([
                        'id' => $image->id,
                        'user_id' => $image->user_id,
                        'bangumi_id' => $image->bangumi_id,
                        'url' => $image->url,
                        'width' => $image->width,
                        'height' => $image->height,
                        'name' => $image->name ?: '',
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                        'deleted_at' => $image->deleted_at,
                        'is_creator' => $image->creator,
                        'is_album' => 0,
                        'is_cartoon' => $image->is_cartoon,
                        'size' => 0,
                        'type' => ''
                    ]);
            }
        }

        if ($id == 4)
        {
            $albumPosters = DB::table('images')
                ->whereRaw('album_id = 0 and image_count > 0', [])
                ->get()
                ->toArray();

            foreach ($albumPosters as $image)
            {
                DB::table('images_v2')
                    ->insert([
                        'id' => $image->id,
                        'user_id' => $image->user_id,
                        'bangumi_id' => $image->bangumi_id,
                        'url' => $image->url,
                        'width' => $image->width,
                        'height' => $image->height,
                        'name' => $image->name,
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                        'deleted_at' => $image->deleted_at,
                        'is_creator' => $image->creator,
                        'is_album' => 1,
                        'is_cartoon' => $image->is_cartoon,
                        'image_ids' => $image->images,
                        'size' => 0,
                        'type' => ''
                    ]);
            }
        }

        if ($id == 5)
        {
            $albumImages = DB::table('images')
                ->where('album_id', '<>', 0)
                ->get()
                ->toArray();

            foreach ($albumImages as $image)
            {
                DB::table('album_images')
                    ->insert([
                        'id' => $image->id,
                        'user_id' => $image->user_id,
                        'album_id' => $image->album_id,
                        'url' => $image->url,
                        'width' => $image->width,
                        'height' => $image->height,
                        'created_at' => $image->created_at,
                        'updated_at' => $image->updated_at,
                        'deleted_at' => $image->deleted_at,
                        'size' => 0,
                        'type' => ''
                    ]);
            }
        }

        return $this->resOK('success');
    }
}
