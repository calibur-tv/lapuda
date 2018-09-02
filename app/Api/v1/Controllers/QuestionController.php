<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 上午6:27
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\QuestionRepository;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    public function show($id)
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

        return $this->resOK($questionRepository->show($id, $this->getAuthUserId()));
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|Array',
            'title' => 'required|string|max:30',
            'images' => 'Array',
            'intro' => 'required|max:120',
            'content' => 'required|string|max:1000'
        ]);

        if ($validator->fails())
        {
            return $this->resErrParams($validator);
        }

        $images = $request->get('images') ?: [];
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

    public function trials()
    {
        $ids = Question
            ::withTrashed()
            ->where('state', '<>', 0)
            ->pluck('id')
            ->toArray();

        if (empty($ids))
        {
            return $this->resOK([]);
        }

        $questionRepository = new QuestionRepository();
        $list = $questionRepository->list($ids, true);

        return $this->resOK($list);
    }

    public function ban(Request $request)
    {
        $id = $request->get('id');

        $questionRepository = new QuestionRepository();
        $questionRepository->deleteProcess($id);

        return $this->resNoContent();
    }

    public function pass(Request $request)
    {
        $id = $request->get('id');

        $questionRepository = new QuestionRepository();
        $questionRepository->recoverProcess($id);

        return $this->resNoContent();
    }
}