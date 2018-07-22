<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/19
 * Time: 上午6:27
 */

namespace App\Api\V1\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    public function show()
    {

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

        return $this->resOK('success');
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