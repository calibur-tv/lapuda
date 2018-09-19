<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/2
 * Time: 上午7:08
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\Repository;
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
            'answer_comment',
            'role_comment',
            'post_reply',
            'image_reply',
            'score_reply',
            'video_reply',
            'question_reply',
            'answer_reply',
            'role_reply'
        ];
    }

    /**
     * 举报内容
     *
     * > 目前支持的 model：
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
     * > 目前支持的 type：
     * 0：其它
     * 1：违法违规
     * 2：色情低俗
     * 3：赌博诈骗
     * 4：人身攻击
     * 5：侵犯隐私
     * 6：内容抄袭
     * 7：垃圾广告
     * 8：恶意引战
     * 9：重复内容/刷屏
     * 10：内容不相关
     * 11：互刷金币
     *
     * @Post("/report/send")
     *
     * @Parameters({
     *      @Parameter("id", description="举报的 id", type="integer", required=true),
     *      @Parameter("model", description="举报的模型", type="string", required=true),
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
        $model = $request->get('model');
        $id = $request->get('id');
        $message = $request->get('message');
        $userId = $this->getAuthUserId();

        if (!in_array($model, $this->types))
        {
            return $this->resErrBad();
        }

        Redis::ZINCRBY('user-report-trending-ids', 1, $model . '-' . $id);

        Redis::RPUSH('user-report-item' . '-' . $model . '-' . $id, $userId . '|' . $type . '|' . $message);

        return $this->resNoContent();
    }

    public function list()
    {
        $repository = new Repository();
        $list = $repository->RedisSort('user-report-trending-ids', function ()
        {
            return [];
        });

        return $this->resOK($list);
    }

    public function item(Request $request)
    {
        $tail = $request->get('tail');
        $repository = new Repository();
        $list = $repository->RedisList('user-report-item' . '-' . $tail, function ()
        {
            return [];
        });

        return $this->resOK($list);
    }

    public function remove(Request $request)
    {
        $tail = $request->get('tail');

        Redis::ZREM('user-report-trending-ids', $tail);
        Redis::DEL('user-report-item' . '-' . $tail);

        return $this->resNoContent();
    }
}