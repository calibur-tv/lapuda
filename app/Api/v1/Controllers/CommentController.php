<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/2
 * Time: 上午9:45
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Toggle\Comment\PostCommentLikeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function list(Request $request, $type, $id)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'required|Integer|min:0'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams('没有页码');
        }

        $commentService = $this->getServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrParams('错误的类型');
        }

        $comment = $commentService->getMainCommentItem($id);
        if (is_null($comment))
        {
            return $this->resErrNotFound('不存在的评论');
        }

        $ids = $commentService->getSubCommentIds($id, $request->get('page'));
        $comments = $commentService->subCommentList($ids);

        return $this->resOK($comments);
    }

    public function reply(Request $request, $type, $id)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|max:100',
            'targetUserId' => 'required|Integer'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator->errors());
        }

        $commentService = $this->getServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrParams('错误的类型');
        }

        $comment = $commentService->getMainCommentItem($id);
        if (is_null($comment))
        {
            return $this->resErrNotFound('内容已删除');
        }

        $content = $request->get('content');
        $targetUserId = $request->get('targetUserId');

        $newComment = $commentService->create([
            'content' => $content,
            'to_user_id' => $targetUserId,
            'user_id' => $this->getAuthUserId(),
            'parent_id' => $id
        ]);

        if (is_null($newComment))
        {
            return $this->resErrServiceUnavailable();
        }

        // 发通知
        if ($targetUserId)
        {
            // TODO：优化成多态，并在通知里展示content
            if ($type === 'post')
            {
                $job = (new \App\Jobs\Notification\Post\Comment($newComment['id']));
                dispatch($job);
            }
        }

        // 更新百度索引
        $job = (new \App\Jobs\Push\Baidu($type . '/' . $comment['modal_id'], 'update'));
        dispatch($job);

        return $this->resCreated($newComment);
    }

    public function delete($type, $id)
    {
        $commentService = $this->getServiceByType($type);
        if (is_null($commentService))
        {
            return $this->resErrParams('错误的类型');
        }

        $result = $commentService->deleteSubComment($id, $this->getAuthUserId());

        if (is_null($result))
        {
            return $this->resErrNotFound('该评论已被删除');
        }

        if (false === $result)
        {
            return $this->resErrRole('继续操作前请先登录');
        }

        return $this->resNoContent();
    }

    public function toggleLike($type, $id)
    {
        if ($type === 'post')
        {
            $commentLikeService = new PostCommentLikeService();
        }
        else
        {
            return $this->resErrParams('错误的类型');
        }

        $result = $commentLikeService->toggle($this->getAuthUserId(), $id);

        if ($result)
        {
            if ($type === 'post')
            {
                $job = (new \App\Jobs\Notification\Post\Agree($result));
                dispatch($job);
            }
        }

        // TODO：dispatch job to update open search weight

        return $this->resCreated((boolean)$result);
    }

    protected function getServiceByType($type)
    {
        if ($type === 'post')
        {
            return new PostCommentService();
        }
        else
        {
            return null;
        }
    }
}