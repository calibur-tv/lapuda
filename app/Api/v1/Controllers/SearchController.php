<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
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
     * 搜索接口
     *
     * > 目前支持的参数格式：
     * type：all, user, bangumi, video，post，role，image，score，question，answer
     * 返回的数据与 flow/list 返回的相同
     *
     * @Get("/search/new")
     *
     * @Parameters({
     *      @Parameter("type", description="要检测的类型", type="string", required=true),
     *      @Parameter("key", description="搜索的关键词", type="string", required=true),
     *      @Parameter("page", description="搜索的页码", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body="数据列表")
     * })
     */
    public function search(Request $request)
    {
        $key = Purifier::clean($request->get('q'));

        if (!$key)
        {
            return $this->resOK();
        }

        $type = $request->get('type') ?: 'all';
        $page = intval($request->get('page')) ?: 0;

        $search = new Search();
        $result = $search->retrieve($key, $type, $page);

        return $this->resOK($result);
    }

    /**
     * 获取所有番剧列表
     *
     * > 返回所有的番剧列表，用户搜索提示，可以有效减少请求数
     *
     * @Get("/search/bangumis")
     *
     * @Transaction({
     *      @Response(200, body="番剧列表")
     * })
     */
    public function bangumis()
    {
        $bangumiRepository = new BangumiRepository();

        return $this->resOK($bangumiRepository->searchAll());
    }

    public function migrate(Request $request)
    {
        $begin = intval($request->get('begin')) ?: 0;
        $end = ($begin + 1) * 1000;
        $begin = $begin * 1000;
        for ($i = $begin; $i < $end; $i++)
        {
            $userId = $i;

            $mainCommentCount = 0;
            $subCommentCount = 0;
            $postCount = 0;
            $imageCount = 0;
            $questionCount = 0;
            $answerCount = 0;
            $scoreCount = 0;
            $signCount = 0;

            $mainCommentCount += DB
                ::table('post_comments')
                ->where('user_id', $userId)
                ->where('modal_id', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $mainCommentCount += DB
                ::table('image_comments')
                ->where('user_id', $userId)
                ->where('modal_id', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $mainCommentCount += DB
                ::table('video_comments')
                ->where('user_id', $userId)
                ->where('modal_id', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $mainCommentCount += DB
                ::table('score_comments')
                ->where('user_id', $userId)
                ->where('modal_id', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $mainCommentCount += DB
                ::table('question_comments')
                ->where('user_id', $userId)
                ->where('modal_id', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $mainCommentCount += DB
                ::table('answer_comments')
                ->where('user_id', $userId)
                ->where('modal_id', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $mainCommentCount += DB
                ::table('cartoon_role_comments')
                ->where('user_id', $userId)
                ->where('modal_id', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();

            $subCommentCount += DB
                ::table('post_comments')
                ->where('user_id', $userId)
                ->where('modal_id', '<>', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $subCommentCount += DB
                ::table('image_comments')
                ->where('user_id', $userId)
                ->where('modal_id', '<>', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $subCommentCount += DB
                ::table('video_comments')
                ->where('user_id', $userId)
                ->where('modal_id', '<>', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $subCommentCount += DB
                ::table('score_comments')
                ->where('user_id', $userId)
                ->where('modal_id', '<>', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $subCommentCount += DB
                ::table('question_comments')
                ->where('user_id', $userId)
                ->where('modal_id', '<>', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $subCommentCount += DB
                ::table('answer_comments')
                ->where('user_id', $userId)
                ->where('modal_id', '<>', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();
            $subCommentCount += DB
                ::table('cartoon_role_comments')
                ->where('user_id', $userId)
                ->where('modal_id', '<>', 0)
                ->where('to_user_id', '<>', 0)
                ->whereNull('deleted_at')
                ->count();

            $postCount = DB
                ::table('posts')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->count();

            $imageCount = DB
                ::table('images')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->count();

            $questionCount = DB
                ::table('questions')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->count();

            $answerCount = DB
                ::table('question_answers')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->count();

            $scoreCount = DB
                ::table('scores')
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->count();

            $signCount = DB
                ::table('user_signs')
                ->where('user_id', $userId)
                ->count();

            $exp = $mainCommentCount * 2 + $subCommentCount + $signCount * 2 + $answerCount * 4 + $questionCount * 3 + $scoreCount * 5 + $imageCount * 3 + $postCount * 4;

            DB
                ::table('users')
                ->where('id', $userId)
                ->update([
                    'exp' => $exp
                ]);
        }

        return $this->resOK('success');
    }
}
