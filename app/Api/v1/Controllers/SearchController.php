<?php

namespace App\Api\V1\Controllers;

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

    public function migrate()
    {
//        $oldComments = DB::table('post_comments')
//            ->get();
//
//        foreach ($oldComments as $comment)
//        {
//            DB::table('post_comments_v2')->insert([
//                'content' => $comment->content,
//                'modal_id' => 0,
//                'parent_id' => $comment->parent_id,
//                'user_id' => $comment->user_id,
//                'to_user_id' => $comment->to_user_id,
//                'state' => $comment->state,
//                'created_at' => $comment->created_at,
//                'updated_at' => $comment->updated_at,
//                'deleted_at' => $comment->deleted_at,
//                'comment_count' => 0
//            ]);
//        }

        $oldReply = DB::table('posts')
            ->where('parent_id', '<>', 0)
            ->where('floor_count', '<>', 0)
            ->get();

        foreach ($oldReply as $reply)
        {
            $state = $reply->state;
            if ($state === 0) {
                $newState = 0;
            } else if ($state === 1) {
                $newState = 4;
            } else if ($state === 2) {
                $newState = 1;
            } else if ($state === 3) {
                $newState = 1;
            } else if ($state === 4) {
                $newState = 2;
            } else if ($state === 5) {
                $newState = 3;
            } else if ($state === 6) {
                $newState = 5;
            } else if ($state === 7) {
                $newState = 1;
            } else {
                $newState = 1;
            }

            $images = DB::table('post_images')
                ->where('post_id', $reply->id)
                ->get();

            $content = [];

            foreach ($images as $image)
            {
                $content[] = [
                    'type' => 'img',
                    'data' => [
                        'url' => $image->src,
                        'width' => $image->width,
                        'height' => $image->height,
                        'type' => $image->type,
                        'size' => $image->size
                    ]
                ];
            }

            $content[] = [
                'type' => 'txt',
                'data' => $reply->content
            ];

            DB::table('post_comments_v2')->insert([
                'id' => $reply->floor_count - 1,
                'modal_id' => $reply->parent_id,
                'comment_count' => $reply->comment_count,
                'user_id' => $reply->user_id,
                'created_at' => $reply->created_at,
                'updated_at' => $reply->updated_at,
                'deleted_at' => $reply->deleted_at,
                'state' => $newState,
                'content' => json_encode($content)
            ]);
        }

        return response()->json(['data' => 'success'], 200);
    }
}
