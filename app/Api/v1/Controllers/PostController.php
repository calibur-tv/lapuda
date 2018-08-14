<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Counter\PostViewCounter;
use App\Api\V1\Services\Counter\Stats\TotalPostCount;
use App\Api\V1\Services\Owner\BangumiManager;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Services\Toggle\Post\PostRewardService;
use App\Api\V1\Services\Trending\PostTrendingService;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Models\Post;
use App\Models\PostImages;
use App\Services\OpenSearch\Search;
use App\Services\Trial\WordsFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("帖子相关接口")
 */
class PostController extends Controller
{
    /**
     * 新建帖子
     *
     * > 图片对象示例：
     * 1. `key` 七牛传图后得到的 key，不包含图片地址的 host，如一张图片 image.calibur.tv/user/1/avatar.png，七牛返回的 key 是：user/1/avatar.png，将这个 key 传到后端
     * 2. `width` 图片的宽度，七牛上传图片后得到
     * 3. `height` 图片的高度，七牛上传图片后得到
     * 4. `size` 图片的尺寸，七牛上传图片后得到
     * 5. `type` 图片的类型，七牛上传图片后得到
     *
     * @Post("/post/create")
     *
     * @Parameters({
     *      @Parameter("bangumiId", description="所选的番剧 id", type="integer", required=true),
     *      @Parameter("title", description="标题`40字以内`", type="string", required=true),
     *      @Parameter("desc", description="content可能是富文本，desc是`120字以内的纯文本`", type="string", required=true),
     *      @Parameter("content", description="内容，`1000字以内`", type="string", required=true),
     *      @Parameter("images", description="图片对象数组", type="array", required=true)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "data": "帖子id"}),
     *      @Response(400, body={"code": 40003, "message": "请求参数错误"})
     * })
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|max:40',
            'bangumiId' => 'required|integer',
            'desc' => 'required|max:120',
            'content' => 'required|max:1200',
            'images' => 'array',
            'is_creator' => 'required|boolean'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $bangumiId = $request->get('bangumiId');
        $userId = $this->getAuthUserId();
        $postRepository = new PostRepository();

        $bangumiFollowService = new BangumiFollowService();
        if (!$bangumiFollowService->check($userId, $bangumiId))
        {
            $bangumiFollowService->do($userId, $bangumiId);
            // return $this->resErrRole('关注番剧后才能发帖');
        }

        $now = Carbon::now();

        $id = $postRepository->create([
            'title' => Purifier::clean($request->get('title')),
            'content' => Purifier::clean($request->get('content')),
            'desc' => Purifier::clean($request->get('desc')),
            'bangumi_id' => $bangumiId,
            'user_id' => $userId,
            'is_creator' => $request->get('is_creator'),
            'created_at' => $now,
            'updated_at' => $now
        ], $request->get('images'));

        $postTrendingService = new PostTrendingService($bangumiId, $userId);
        $postTrendingService->create($id);

        $job = (new \App\Jobs\Trial\Post\Create($id));
        dispatch($job);

        return $this->resCreated($id);
    }

    /**
     * 帖子详情
     *
     * @Get("/post/`postId`/show")
     *
     * @Parameters({
     *      @Parameter("only", description="是否只看楼主", type="integer", default="0", required=false)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(200, body={"code": 0, "data": {"bangumi":"番剧信息", "user": "作者信息", "post": "帖子信息"}}),
     *      @Response(404, body={"code": 40401, "message": "帖子不存在/番剧不存在/作者不存在"})
     * })
     */
    public function show(Request $request, $id)
    {
        $postRepository = new PostRepository();
        $post = $postRepository->item($id);
        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $userRepository = new UserRepository();
        $author = $userRepository->item($post['user_id']);
        if (is_null($author))
        {
            return $this->resErrNotFound('不存在的用户');
        }

        $userId = $this->getAuthUserId();
        $bangumiRepository = new BangumiRepository();
        $bangumi = $bangumiRepository->panel($post['bangumi_id'], $userId);
        if (is_null($bangumi))
        {
            return $this->resErrNotFound('不存在的番剧');
        }

        $postCommentService = new PostCommentService();
        $post['commented'] = $postCommentService->checkCommented($userId, $id);

        $post['comment_count'] = $postCommentService->getCommentCount($id);

        if ($post['is_creator'])
        {
            $postRewardService = new PostRewardService();
            $post['rewarded'] = $postRewardService->check($userId, $id);
            $post['reward_count'] = $postRewardService->total($id);
            $post['reward_users'] = $postRewardService->users($id);
            $post['liked'] = false;
            $post['like_count'] = 0;
            $post['like_users'] = [];
        }
        else
        {
            $postLikeService = new PostLikeService();
            $post['liked'] = $postLikeService->check($userId, $id);
            $post['like_count'] = $postLikeService->total($id);
            $post['like_users'] = $postLikeService->users($id);
            $post['rewarded'] = false;
            $post['reward_count'] = 0;
            $post['reward_users'] = [];
        }

        $postMarkService = new PostMarkService();
        $post['marked'] = $postMarkService->check($userId, $id);
        $post['mark_count'] = $postMarkService->total($id);

        $post['preview_images'] = $postRepository->previewImages(
            $id,
            $post['user_id'],
            (boolean)(intval($request->get('only')) ?: 0)
        );

        $viewCounter = new PostViewCounter();
        $post['view_count'] = $viewCounter->add($id);

        $postTransformer = new PostTransformer();
        $userTransformer = new UserTransformer();

        return $this->resOK([
            'bangumi' => $bangumi,
            'post' => $postTransformer->show($post),
            'user' => $userTransformer->item($author)
        ]);
    }

    public function bangumiTops(Request $request, $id)
    {
        $bangumiRepository = new BangumiRepository();
        $ids = $bangumiRepository->getTopPostIds($id);

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $postRepository = new PostRepository();
        $list = $postRepository->bangumiFlow($ids);

        $postCommentService = new PostCommentService();
        $postLikeService = new PostLikeService();
        $postMarkService = new PostMarkService();
        $postRewardService = new PostRewardService();

        foreach ($list as $i => $item)
        {
            if ($item['is_creator'])
            {
                $list[$i]['like_count'] = 0;
                $list[$i]['reward_count'] = $postRewardService->total($item['id']);
            }
            else
            {
                $list[$i]['like_count'] = $postLikeService->total($item['id']);
                $list[$i]['reward_count'] = 0;
            }
        }

        $list = $postMarkService->batchTotal($list, 'mark_count');
        $list = $postCommentService->batchGetCommentCount($list);

        $transformer = new PostTransformer();

        return $this->resOK($transformer->bangumiFlow($list));
    }

    /**
     * 删除帖子
     *
     * @Post("/post/`postId`/deletePost")
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(204),
     *      @Response(401, body={"code": 40104, "message": "未登录的用户"}),
     *      @Response(403, body={"code": 40301, "message": "权限不足"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的帖子"})
     * })
     */
    public function deletePost($postId)
    {
        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);
        $userId = $this->getAuthUserId();

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        if (intval($post['user_id']) !== $userId)
        {
            return $this->resErrRole('权限不足');
        }

        DB::table('posts')
            ->where('id', $postId)
            ->update([
                'deleted_at' => Carbon::now()
            ]);
        /*
         * 删除主题帖
         * 删除 bangumi-cache-ids-list 中的这个帖子 id
         * 删除用户帖子列表的id
         * 删除最新和热门帖子下该帖子的缓存
         * 删掉主题帖的缓存
         */
        Redis::DEL('post_'.$postId);
        $postTrendingService = new PostTrendingService($post['bangumi_id'], $post['user_id']);
        $postTrendingService->delete($postId);

        $job = (new \App\Jobs\Search\Post\Delete($postId));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('post/' . $postId, 'del'));
        dispatch($job);

        $totalPostCount = new TotalPostCount();
        $totalPostCount->add(-1);

        return $this->resNoContent();
    }

    public function trialList()
    {
        $list = Post::withTrashed()
            ->where('state', '<>', 0)
            ->get();

        if (empty($list))
        {
            return $this->resOK([]);
        }

        $filter = new WordsFilter();
        $userRepository = new UserRepository();

        foreach ($list as $i =>$row)
        {
            $list[$i]['f_title'] = $filter->filter($row['title']);
            $list[$i]['f_content'] = $filter->filter($row['content']);
            $list[$i]['words'] = $filter->filter($row['title'] . $row['content']);
            $list[$i]['images'] = PostImages::where('post_id', $row['id'])->get();
            $list[$i]['user'] = $userRepository->item($row['user_id']);
        }

        return $this->resOK($list);
    }

    public function deletePostImage(Request $request)
    {
        $id = $request->get('id');
        $postId = PostImages::where('id', $id)->pluck('post_id')->first();
        PostImages::where('id', $id)
            ->update([
                'src' => '',
                'origin_url' => $request->get('src')
            ]);

        Redis::DEL('post_'.$postId.'_images');

        return $this->resNoContent();
    }

    public function trialDelete(Request $request)
    {
        $id = $request->get('id');
        $post = Post::withTrashed()
            ->where('id', $id)
            ->first();

        if (is_null($post))
        {
            return $this->resErrNotFound();
        }

        Redis::DEL('post_'.$id);
        Redis::ZREM('post_how_ids', $id);
        Redis::ZREM('post_new_ids', $id);
        Redis::ZREM('bangumi_'.$post->bangumi_id.'_posts_new_ids', $id);

        $post->update([
            'state' => 0
        ]);
        $post->delete();

        $searchService = new Search();
        $searchService->delete($id, 'post');
        // TODO：百度
        return $this->resNoContent();
    }

    public function trialPass(Request $request)
    {
        $postId = $request->get('id');

        $post = Post::withTrashed()
            ->where('id', $postId)
            ->first();

        if (is_null($post))
        {
            return $this->resErrNotFound();
        }

        if ($post->deleted_at)
        {
            $post->restore();
            $postRepository = new PostRepository();
            $postRepository->trialPass($post);
        }
        $post->update([
            'state' => 0
        ]);

        $searchService = new Search();
        $searchService->create(
            $postId,
            $post->title . ',' . $post->desc,
            'post',
            strtotime($post->created_at)
        );

        return $this->resNoContent();
    }

    // TODO：doc
    public function setNice(Request $request)
    {
        $postId = $request->get('id');
        $userId = $this->getAuthUserId();

        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $bangumiManager = new BangumiManager();
        if (!$bangumiManager->isOwner($post['bangumi_id'], $userId))
        {
            return $this->resErrRole('这个帖子不由你管理');
        }

        if ($post['is_nice'])
        {
            return $this->resErrBad('已经是精品贴了');
        }

        Post::where('id', $postId)
            ->update([
                'is_nice' => $userId,
                'state' => $userId
            ]);

        Redis::DEL('post_' . $postId);

        return $this->resNoContent();
    }

    // TODO：doc
    public function removeNice(Request $request)
    {
        $postId = $request->get('id');
        $userId = $this->getAuthUserId();

        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $bangumiManager = new BangumiManager();
        if (!$bangumiManager->isOwner($post['bangumi_id'], $userId))
        {
            return $this->resErrRole('这个帖子不由你管理');
        }

        if (!$post['is_nice'])
        {
            return $this->resErrBad('不是精品贴');
        }

        Post::where('id', $postId)
            ->update([
                'is_nice' => 0
            ]);

        Redis::DEL('post_' . $postId);

        return $this->resNoContent();
    }

    // TODO：doc
    public function setTop(Request $request)
    {
        $postId = $request->get('id');
        $userId = $this->getAuthUserId();

        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $bangumiManager = new BangumiManager();
        if (!$bangumiManager->isOwner($post['bangumi_id'], $userId))
        {
            return $this->resErrRole('这个帖子不由你管理');
        }

        $topCount = Post::where('bangumi_id', $post['bangumi_id'])
            ->whereNotNull('top_at')
            ->count();

        if ($topCount >= 3)
        {
            return $this->resErrBad('已经有' . $topCount . '个置顶帖了');
        }

        Post::where('id', $postId)
            ->update([
                'top_at' => Carbon::now(),
                'state' => $userId
            ]);

        Redis::DEL('post_' . $postId);

        return $this->resNoContent();
    }

    // TODO：doc
    public function removeTop(Request $request)
    {
        $postId = $request->get('id');
        $userId = $this->getAuthUserId();

        $postRepository = new PostRepository();
        $post = $postRepository->item($postId);

        if (is_null($post))
        {
            return $this->resErrNotFound('不存在的帖子');
        }

        $bangumiManager = new BangumiManager();
        if (!$bangumiManager->isOwner($post['bangumi_id'], $userId))
        {
            return $this->resErrRole('这个帖子不由你管理');
        }

        if (!$post['top_at'])
        {
            return $this->resErrBad('不是置顶贴');
        }

        Post::where('id', $postId)
            ->update([
                'top_at' => null
            ]);

        Redis::DEL('post_' . $postId);

        return $this->resNoContent();
    }
}
