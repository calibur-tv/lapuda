<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/2
 * Time: 上午7:08
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\Comment\AnswerCommentService;
use App\Api\V1\Services\Comment\CartoonRoleCommentService;
use App\Api\V1\Services\Comment\ImageCommentService;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Comment\QuestionCommentService;
use App\Api\V1\Services\Comment\ScoreCommentService;
use App\Api\V1\Services\Comment\VideoCommentService;
use App\Api\V1\Services\Owner\BangumiManager;
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
     *   answer_comment,
     *   role_comment,
     *   post_reply,
     *   image_reply,
     *   score_reply,
     *   video_reply,
     *   question_reply,
     *   answer_reply,
     *   role_reply
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

        if ($userId)
        {
            if (preg_match('/_/', $model))
            {
                // 是 reply
                $arr = explode('_', $model);
                $commentService = $this->getCommentRepositoryByType($arr[0]);

                $subComment = $commentService->getSubCommentItem($arr[1]);
                if (is_null($subComment))
                {
                    return $this->resNoContent();
                }

                $mainComment = $commentService->getMainCommentItem($subComment['parent_id']);
                if (is_null($mainComment))
                {
                    return $this->resNoContent();
                }

                $repository = $this->getRepositoryByType($arr[0]);
                if (is_null($repository))
                {
                    return $this->resNoContent();
                }

                $item = $repository->item($mainComment['modal_id']);
                if (is_null($item))
                {
                    return $this->resNoContent();
                }

                if (!isset($item['bangumi_id']))
                {
                    return $this->resNoContent();
                }

                $bangumiManager = new BangumiManager();
                if ($bangumiManager->isOwner($item['bangumi_id'], $userId))
                {
                    $commentService->deleteSubComment($subComment['id'], $mainComment['id'], $userId);
                }
            }
            else if (preg_match('/-/', $model))
            {
                // 是 comment
                $arr = explode('_', $model);
                $commentService = $this->getCommentRepositoryByType($arr[0]);

                $mainComment = $commentService->getMainCommentItem($arr[1]);
                if (is_null($mainComment))
                {
                    return $this->resNoContent();
                }

                $repository = $this->getRepositoryByType($arr[0]);
                if (is_null($repository))
                {
                    return $this->resNoContent();
                }

                $item = $repository->item($mainComment['modal_id']);
                if (is_null($item))
                {
                    return $this->resNoContent();
                }

                if (!isset($item['bangumi_id']))
                {
                    return $this->resNoContent();
                }

                $bangumiManager = new BangumiManager();
                if ($bangumiManager->isOwner($item['bangumi_id'], $userId))
                {
                    $commentService->deleteMainComment(
                        $mainComment['id'],
                        $mainComment['modal_id'],
                        $mainComment['from_user_id'],
                        false,
                        $userId
                    );
                }
            }
            else if (in_array($model, ['post', 'image', 'score', 'question', 'answer']))
            {
                $repository = $this->getRepositoryByType($model);

                $item = $repository->item($id);
                if (is_null($item))
                {
                    return $this->resNoContent();
                }

                $bangumiManager = new BangumiManager();
                if ($bangumiManager->isOwner($item['bangumi_id'], $userId))
                {
                    $repository->deleteProcess($id, $item['user_id']);
                }
            }
        }

        return $this->resNoContent();
    }

    public function list()
    {
        $repository = new Repository();
        $list = $repository->RedisSort('user-report-trending-ids', function ()
        {
            return [];
        });
        foreach ($list as $i => $item)
        {
            if (preg_match('/_/', $item))
            {
                $one = explode('_', $item);
                $two = explode('-', $one[1]);
                $key = $two[0];
                $type = $one[0];
                $id = $two[1];
                $repository = $this->getCommentRepositoryByType($type);
                if (is_null($repository))
                {
                    continue;
                }
                if ($key === 'reply')
                {
                    $subComment = $repository->getSubCommentItem($id);
                    if (is_null($subComment))
                    {
                        continue;
                    }
                    $commentId = $subComment['parent_id'];
                }
                else
                {
                    $commentId = $id;
                }
                $mainComment = $repository->getMainCommentItem($commentId);
                if (is_null($mainComment))
                {
                    continue;
                }
                $item = $item . '-' . $mainComment['modal_id'];
                if ($key === 'reply')
                {
                    $item = $item . '-' . $commentId;
                }

                $list[$i] = $item;
            }
        }

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

    protected function getCommentRepositoryByType($type)
    {
        if ($type === 'post')
        {
            return new PostCommentService();
        }
        else if ($type === 'image')
        {
            return new ImageCommentService();
        }
        else if ($type === 'score')
        {
            return new ScoreCommentService();
        }
        else if ($type === 'question')
        {
            return new QuestionCommentService();
        }
        else if ($type === 'answer')
        {
            return new AnswerCommentService();
        }
        else if ($type === 'role')
        {
            return new CartoonRoleCommentService();
        }
        else if ($type === 'video')
        {
            return new VideoCommentService();
        }
        else
        {
            return null;
        }
    }

    protected function getRepositoryByType($type)
    {
        if ($type === 'post')
        {
            return new PostRepository();
        }
        else if ($type === 'image')
        {
            return new ImageRepository();
        }
        else if ($type === 'score')
        {
            return new ScoreRepository();
        }
        else if ($type === 'question')
        {
            return new QuestionRepository();
        }
        else if ($type === 'answer')
        {
            return new AnswerRepository();
        }
        else if ($type === 'role')
        {
            return new CartoonRoleRepository();
        }
        else if ($type === 'video')
        {
            return new VideoRepository();
        }
        else
        {
            return null;
        }
    }
}