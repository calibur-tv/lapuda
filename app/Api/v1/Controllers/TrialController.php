<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/3
 * Time: 下午8:14
 */

namespace App\Api\V1\Controllers;

use App\Models\AlbumImage;
use App\Models\Answer;
use App\Models\Bangumi;
use App\Models\CartoonRole;
use App\Models\Feedback;
use App\Models\Image;
use App\Models\Post;
use App\Models\Question;
use App\Models\Score;
use App\Models\User;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TrialController extends Controller
{
    // TODO：counter-cache
    public function todo()
    {
        $comments = 0;
        $comments = $comments + DB::table('post_comments')->where('state', '<>', 0)->count();
        $comments = $comments + DB::table('image_comments')->where('state', '<>', 0)->count();
        $comments = $comments + DB::table('video_comments')->where('state', '<>', 0)->count();
        $comments = $comments + DB::table('score_comments')->where('state', '<>', 0)->count();
        $comments = $comments + DB::table('question_comments')->where('state', '<>', 0)->count();
        $comments = $comments + DB::table('answer_comments')->where('state', '<>', 0)->count();

        $images = Image::withTrashed()->where('state', '<>', 0)->count() + AlbumImage::withTrashed()->where('state', '<>', 0)->count();

        $result = [
            'feedback' => Feedback::where('stage', 0)->count(),
            'users' => User::withTrashed()->where('state', '<>', 0)->count(),
            'posts' => Post::withTrashed()->where('state', '<>', 0)->count(),
            'images' => $images,
            'comments' => $comments,
            'bangumi' => Bangumi::withTrashed()->where('state', '<>', 0)->count(),
            'role' => CartoonRole::withTrashed()->where('state', '<>', 0)->count(),
            'score' => Score::withTrashed()->where('state', '<>', 0)->count(),
            'question' => Question::withTrashed()->where('state', '<>', 0)->count(),
            'answer' => Answer::withTrashed()->where('state', '<>', 0)->count()
        ];

        return $this->resOK($result);
    }

    // 敏感词列表
    public function words()
    {
        $words = Redis::LRANGE('blackwords', 0, -1);

        return $this->resOK($words);
    }

    // 删除敏感词
    public function deleteWords(Request $request)
    {
        $words = $request->get('words');
        foreach ($words as $item)
        {
            Redis::LREM('blackwords', 1, $item);
        }

        return $this->resNoContent();
    }

    // 添加敏感词
    public function addWords(Request $request)
    {
        Redis::LPUSH('blackwords', $request->get('words'));

        return $this->resNoContent();
    }

    // 图片测试
    public function imageTest(Request $request)
    {
        $imageUrl = $request->get('url');
        if (!$imageUrl)
        {
            return $this->resErrBad();
        }

        $imageFilter = new ImageFilter();

        return $this->resOK($imageFilter->test($imageUrl));
    }

    // 文字测试
    public function textTest(Request $request)
    {
        $content = $request->get('text');
        if (!$content)
        {
            return $this->resErrBad();
        }

        $wordFilter = new WordsFilter();

        return $this->resOK($wordFilter->filter($content));
    }
}