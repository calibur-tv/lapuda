<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Models\Bangumi;
use App\Models\Image;
use App\Models\Post;
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
     * 重置密码
     *
     * @Post("/search/index")
     *
     * @Parameters({
     *      @Parameter("q", description="查询关键字", required=true),
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "番剧相对链接或空字符串"}),
     * })
     */
    public function index(Request $request)
    {
        $key = Purifier::clean($request->get('q'));
        if (!$key)
        {
            return $this->resOK();
        }

        $search = new Search();
        $result = $search->index($key);

        return $this->resOK(empty($result) ? '' : $result[0]['fields']['url']);
    }

    public function migration()
    {
        $ids = Image::whereRaw('is_cartoon = ? and album_id = ? and image_count <> ?', [1, 0, 0])
            ->select('id', 'bangumi_id')
            ->get();

        $keys = [];
        foreach ($ids as $val)
        {
            if (!in_array($val['bangumi_id'], $keys))
            {
                $keys[] = $val['bangumi_id'];
            }
        }

        $vals = [];

        foreach ($keys as $i => $bangumiId)
        {
            foreach ($ids as $val)
            {
                if ($val['bangumi_id'] === $bangumiId)
                {
                    $vals[$i] = isset($vals[$i]) ? ($vals[$i] . ',' . $val['id']) : (String)$val['id'];
                }
            }
        }

        if (count($keys) !== count($vals))
        {
            return $this->resErrBad('key != val');
        }

        foreach ($keys as $i => $bangumiId)
        {
            Bangumi::where('id', $bangumiId)->update([
                'cartoon' => $vals[$i]
            ]);
        }

        return response()->json([
            'data' => $ids,
            'keys' => $keys,
            'vals' => $vals
        ], 200);
    }
}
