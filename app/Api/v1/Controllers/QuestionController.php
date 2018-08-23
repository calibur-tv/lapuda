<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 上午6:27
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Services\Comment\QuestionCommentService;
use App\Api\V1\Services\Counter\QuestionViewCounter;
use App\Api\V1\Services\Tag\QuestionTagService;
use App\Api\V1\Services\Toggle\Question\QuestionFollowService;
use App\Api\V1\Transformers\QuestionTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    public function showQuestion($id)
    {
        $questionRepository = new QuestionRepository();
        $question = $questionRepository->item($id, true);
        if (is_null($question))
        {
            return $this->resErrNotFound();
        }

        if ($question['deleted_at'])
        {
            if ($question['state'])
            {
                return $this->resErrLocked();
            }

            return $this->resErrNotFound();
        }

        $userId = $this->getAuthUserId();

        $questionCommentService = new QuestionCommentService();
        $question['commented'] = $questionCommentService->checkCommented($userId, $id);
        $question['comment_count'] = $questionCommentService->getCommentCount($id);

        $questionFollowService = new QuestionFollowService();
        $question['follow_users'] = $questionFollowService->users($id);
        $question['followed'] = $questionFollowService->check($userId, $id);

        $questionTagService = new QuestionTagService();
        $question['tags'] = $questionTagService->tags($id);

        $questionViewCounter = new QuestionViewCounter();
        $question['view_count'] = $questionViewCounter->add($id);

        $questionTransformer = new QuestionTransformer();

        return $this->resOK($questionTransformer->show($question));
    }

    public function createQuestion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|Array',
            'title' => 'required|string|max:30',
            'images' => 'required|Array',
            'intro' => 'required|max:120',
            'content' => 'required|string|max:1000'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $images = $request->get('images');
        foreach ($images as $i => $image)
        {
            $validator = Validator::make($image, [
                'url' => 'required|string',
                'width' => 'required|integer',
                'height' => 'required|integer',
                'size' => 'required|integer',
                'type' => 'required|string',
            ]);

            if ($validator->fails())
            {
                return $this->resErrParams($validator);
            }
        }

        $questionRepository = new QuestionRepository();
        $newId = $questionRepository->create([
            'tags' => $request->get('tags'),
            'title' => $request->get('title'),
            'text' => $request->get('content'),
            'intro' => $request->get('intro'),
            'images' => $request->get('images'),
            'user_id' => $this->getAuthUserId()
        ]);

        return $this->resCreated($newId);
    }

    public function update()
    {

    }

    public function delete()
    {

    }

    public function usersQuestion()
    {

    }

    public function usersAnswer()
    {

    }

    public function trials()
    {

    }

    public function ban()
    {

    }

    public function pass()
    {

    }
}