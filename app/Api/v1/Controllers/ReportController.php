<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/2
 * Time: 上午7:08
 */

namespace App\Api\V1\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * @Resource("举报相关接口")
 */
class ReportController extends Controller
{
    public function __construct()
    {
        $this->types = [
            'user',
            'bangumi',
            'video',
            'role',
            'post',
            'image',
            'score',
            'question',
            'answer',
            'post_comment',
            'image_comment',
            'score_comment',
            'video_comment',
            'question_comment',
            'answer_comment'
        ];
    }

    /**
     * 举报内容
     *
     * > 目前支持的 type：
     *   user,
     *   bangumi,
     *   video,
     *   role,
     *   post,
     *   image,
     *   score,
     *   question,
     *   answer,
     *   post_comment,
     *   image_comment,
     *   score_comment,
     *   video_comment,
     *   question_comment,
     *   answer_comment
     *
     * @Post("/report/send")
     *
     * @Parameters({
     *      @Parameter("id", description="举报的 id", type="integer", required=true),
     *      @Parameter("type", description="举报的类型", type="string", required=true),
     *      @Parameter("message", description="举报的留言", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Response(204)
     * })
     */
    public function send(Request $request)
    {
        $type = $request->get('type');
        $id = $request->get('id');
        $message = $request->get('message');
        $userId = $this->getAuthUserId();

        if (!in_array($type, $this->types))
        {
            return $this->resErrBad();
        }

        $listCacheKey = $this->getReportListKeyByType($type);
        Redis::ZINCRBY($listCacheKey, 1, $id);

        $itemCacheKey = $this->getReportItemDetailKey($type, $id);
        Redis::RPUSH($itemCacheKey, $userId . ':' . $message);

        return $this->resNoContent();
    }

    protected function getReportListKeyByType($type)
    {
        return 'user_report_' . $type . '_trending_ids';
    }

    protected function getReportItemDetailKey($type, $id)
    {
        return 'user_report_' . $type . '_' . $id . '_detail';
    }
}