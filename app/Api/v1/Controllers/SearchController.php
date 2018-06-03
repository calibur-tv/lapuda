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
        $end1 = DB::table('post_comments_v3')->count();

        if (!$end1)
        {
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
                            'key' => $image->src,
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

                DB::table('post_comments_v3')->insert([
                    'id' => $reply->id,
                    'modal_id' => $reply->parent_id,
                    'comment_count' => $reply->comment_count,
                    'user_id' => $reply->user_id,
                    'created_at' => $reply->created_at,
                    'updated_at' => $reply->updated_at,
                    'deleted_at' => $reply->deleted_at,
                    'state' => $newState,
                    'floor_count' => $reply->floor_count,
                    'like_count' => $reply->like_count,
                    'content' => json_encode($content)
                ]);
            }

            $oldComment = DB::table('post_comments')->get();
            foreach ($oldComment as $comment)
            {
                DB::table('post_comments_v3')->insert([
                    'user_id' => $comment->user_id,
                    'to_user_id' => $comment->to_user_id,
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'deleted_at' => $comment->deleted_at,
                    'comment_count' => $comment->comment_count,
                    'parent_id' => $comment->parent_id,
                    'modal_id' => 0,
                    'content' => $comment->content,
                    'state' => $comment->state
                ]);
            }
        }

        $end2 = DB::table('post_comment_like')->count();
        if (!$end2)
        {
            $likes = DB::table('post_like')->get();
            foreach ($likes as $item)
            {
                $count = DB::table('posts')
                    ->whereRaw('id = ? and parent_id = 0', [$item->modal_id])
                    ->count();

                if (!$count)
                {
                    DB::table('post_comment_like')
                        ->insert([
                            'user_id' => $item->user_id,
                            'modal_id' => $item->modal_id,
                            'created_at' => $item->created_at
                        ]);
                }
            }
        }

        return response()->json(['data' => 'success'], 200);
    }
}
