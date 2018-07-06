<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/3
 * Time: 下午8:14
 */

namespace App\Api\V1\Controllers;

use App\Models\Bangumi;
use App\Models\Feedback;
use App\Models\Image;
use App\Models\Post;
use App\Models\User;
use App\Services\Trial\ImageFilter;
use App\Services\Trial\WordsFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TrialController extends Controller
{
    public function todo()
    {
        $comments = 0;
        $comments = $comments + DB::table('post_comments')->where('state', 2)->count();
        $comments = $comments + DB::table('image_comments')->where('state', 2)->count();
        $comments = $comments + DB::table('video_comments')->where('state', 2)->count();

        $result = [
            'users' => User::where('state', '<>', 0)->count(),
            'posts' => Post::withTrashed()->whereIn('state', [4, 5])->count(),
            'images' => Image::withTrashed()->where('state', 2)->count(),
            'feedback' => Feedback::where('stage', 0)->count(),
            'comments' => $comments,
            'bangumi' => Bangumi::where('state', '<>', 9)->count()
        ];

        return $this->resOK($result);
    }

    public function words()
    {
        $words = Redis::LRANGE('blackwords', 0, -1);

        return $this->resOK($words);
    }

    public function deleteWords(Request $request)
    {
        $words = $request->get('words');
        foreach ($words as $item)
        {
            Redis::LREM('blackwords', 1, $item);
        }

        return $this->resNoContent();
    }

    public function addWords(Request $request)
    {
        Redis::LPUSH('blackwords', $request->get('words'));

        return $this->resNoContent();
    }

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