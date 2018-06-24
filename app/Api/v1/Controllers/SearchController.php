<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Models\Bangumi;
use App\Models\CartoonRole;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
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
        $key = Purifier::clean($request->get('q'));

        if (!$key)
        {
            return $this->resOK();
        }

        $type = $request->get('type') ?: 0;
        $page = $request->get('page') ?: 0;

        $search = new Search();
        $result = $search->index($key, $type, $page);

        return $this->resOK($result);
    }

    public function migrate(Request $request)
    {
        $type = intval($request->get('type')) ?: 0;
        $search = new Search();

        if ($type === 1)
        {
            $users = User::select('id', 'nickname', 'zone', 'created_at')->get();
            foreach ($users as $user)
            {
                $search->create(
                    $user['id'],
                    $user['nickname'] . ',' . $user['zone'],
                    'user',
                    strtotime($user['created_at'])
                );
            }
        }

        if ($type === 2)
        {
            $bangumis = Bangumi::pluck('id');
            $bangumiRepository = new BangumiRepository();
            foreach ($bangumis as $bangumiId)
            {
                $bangumi = $bangumiRepository->item($bangumiId);
                $search->create(
                    $bangumi['id'],
                    $bangumi['name'] . ',' . $bangumi['alias'],
                    'bangumi',
                    strtotime($bangumi['created_at'])
                );
            }
        }

        if ($type === 3)
        {
            $videos = Video::pluck('id');
            $videoRepository = new VideoRepository();
            foreach ($videos as $videoId)
            {
                $video = $videoRepository->item($videoId);
                $search->create(
                    $video['id'],
                    $video['name'],
                    'video',
                    strtotime($video['created_at'])
                );
            }
        }

        if ($type === 4)
        {
            $posts = Post::select('id', 'desc', 'title', 'created_at')->get();
            foreach ($posts as $post)
            {
                $search->create(
                    $post['id'],
                    $post['title'] . ',' . $post['desc'],
                    'post',
                    strtotime($post['created_at'])
                );
            }
        }

        if ($type === 5)
        {
            $roles = CartoonRole::pluck('id');
            $cartoonRoleRepository = new CartoonRoleRepository();
            foreach ($roles as $roleId)
            {
                $role = $cartoonRoleRepository->item($roleId);
                $search->create(
                    $role['id'],
                    $role['name'] . ',' . $role['alias'],
                    'role',
                    strtotime($role['created_at'])
                );
            }
        }

        // TODO image search

        return 'success';
    }
}
