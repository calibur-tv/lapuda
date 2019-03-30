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

/**
 * @Resource("审核相关接口")
 */
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
            'answer' => Answer::withTrashed()->where('state', '<>', 0)->count(),
            'report' => Redis::ZCARD('user-report-trending-ids')
        ];

        return $this->resOK($result);
    }

    public function consoleTodo()
    {
        $result = [
            'feedback' => Feedback::where('stage', 0)->count()
        ];

        return $this->resOK($result);
    }

    public function trialTodo()
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
            'users' => User::withTrashed()->where('state', '<>', 0)->count(),
            'posts' => Post::withTrashed()->where('state', '<>', 0)->count(),
            'images' => $images,
            'comments' => $comments,
            'bangumi' => Bangumi::withTrashed()->where('state', '<>', 0)->count(),
            'role' => CartoonRole::withTrashed()->where('state', '<>', 0)->count(),
            'score' => Score::withTrashed()->where('state', '<>', 0)->count(),
            'question' => Question::withTrashed()->where('state', '<>', 0)->count(),
            'answer' => Answer::withTrashed()->where('state', '<>', 0)->count(),
            'report' => Redis::ZCARD('user-report-trending-ids')
        ];

        return $this->resOK($result);
    }

    // 删除敏感词
    public function deleteWords(Request $request)
    {
        $words = $request->get('words');
        foreach ($words as $item)
        {
            Redis::LREM('blackwords', 1, $item);
        }
        $this->changeBlackWordsFile();

        return $this->resNoContent();
    }

    // 添加敏感词
    public function addWords(Request $request)
    {
        Redis::LPUSH('blackwords', $request->get('words'));

        $this->changeBlackWordsFile();

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

    public function words()
    {
        $data = Redis::LRANGE('blackwords', 0, -1);
        if (empty($data))
        {
            $path = base_path() . '/storage/app/words.txt';
            $data = $this->readKeysFromFile($path);
            Redis::RPUSH('blackwords', $data);
        }

        return $this->resOK($data);
    }

    // 修改敏感词库的文件
    protected function changeBlackWordsFile()
    {
        $path = base_path() . '/storage/app/words.txt';
        $data = Redis::LRANGE('blackwords', 0, -1);

        if (empty($data))
        {
            $keys = $this->readKeysFromFile($path);
            Redis::RPUSH('blackwords', $keys);
        }
        else
        {
            $this->syncWordsFromCache($data, $path);
        }

        $resTrie = trie_filter_new();
        $fp = fopen($path, 'r');
        if ( ! $fp)
        {
            return;
        }

        while ( ! feof($fp))
        {
            $word = fgets($fp, 1024);
            if ( ! empty($word))
            {
                trie_filter_store($resTrie, $word);
            }
        }

        trie_filter_save($resTrie,  base_path() . '/app/Services/Trial/' . 'blackword.tree');
    }

    protected function readKeysFromFile($path)
    {
        if (!file_exists($path))
        {
            return [];
        }

        $fp = fopen($path, 'r');
        $words = [];
        while( ! feof($fp))
        {
            if ($line = rtrim(fgets($fp))) {
                $words[] = $line;
            }
        }
        fclose($fp);

        return $words;
    }

    protected function syncWordsFromCache($keys, $path)
    {
        $fp = fopen($path, 'w');

        if ( ! $fp)
        {
            return;
        }

        foreach ($keys as $v)
        {
            fwrite($fp, "$v\r\n");
        }
        fclose($fp);
    }
}