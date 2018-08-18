<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/21
 * Time: 下午8:50
 */

namespace App\Api\V1\Repositories;


use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Trending\PostTrendingService;
use App\Models\Post;
use App\Models\PostImages;
use App\Services\OpenSearch\Search;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class PostRepository extends Repository
{
    public function item($id, $isShow = false)
    {
        if (!$id)
        {
            return null;
        }

        $result = $this->Cache('post_' . $id, function () use ($id)
        {
            $post = Post
                ::withTrashed()
                ->where('id', $id)
                ->first();

            if (is_null($post))
            {
                return null;
            }

            $post = $post->toArray();

            $images = PostImages::where('post_id', $id)
                ->orderBy('created_at', 'ASC')
                ->select('src AS url', 'width', 'height', 'size', 'type')
                ->get()
                ->toArray();

            $post['images'] = $images;

            return $post;
        });

        if (!$result || ($result['deleted_at'] && !$isShow))
        {
            return null;
        }

        return $result;
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->item($id);
            if ($item)
            {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function create($data, $images)
    {
        $newId = Post::insertGetId($data);
        $this->savePostImage($newId, $newId, $images);
        return $newId;
    }

    public function savePostImage($postId, $commentId, $images)
    {
        if (!empty($images))
        {
            $arr = [];
            $now = Carbon::now();

            foreach ($images as $item)
            {
                $arr[] = [
                    'post_id' => $commentId,
                    'src' => $item['key'],
                    'size' => intval($item['size']),
                    'width' => intval($item['width']),
                    'height' => intval($item['height']),
                    'origin_url' => '',
                    'type' => $item['type'],
                    'created_at' => $now,
                    'updated_at' => $now
                ];
            }

            PostImages::insert($arr);

            // 更新帖子图片预览的缓存
            if (Redis::EXISTS('post_'.$postId.'_preview_images') && !empty($images))
            {
                foreach ($images as $i => $val)
                {
                    $images[$i]['url'] = config('website.image') . $val['key'];
                    $images[$i] = json_encode($images[$i]);
                }
                Redis::RPUSH('post_'.$postId.'_preview_images', $images);
            }
        }
    }

    public function applyAddComment($userId, $post, $images, $newComment)
    {
        $id = $post['id'];
        $newId = $newComment['id'];
        $this->savePostImage($id, $newId, $images);
        $now = Carbon::now();

        Post::where('id', $id)->update([
            'updated_at' => $now
        ]);

        $trendingService = new PostTrendingService();
        $trendingService->update($id);
        Redis::LPUSHX('user_'.$userId.'_replyPostIds', $newId);
    }

    public function previewImages($id, $masterId, $onlySeeMaster)
    {
        $list = $this->RedisList('post_'.$id.'_preview_images', function () use ($id, $masterId, $onlySeeMaster)
        {
            $postCommentService = new PostCommentService();
            $ids = $onlySeeMaster
                ? $postCommentService->getAuthorMainCommentIds($id, $masterId)
                : $postCommentService->getMainCommentIds($id);

            $ids[] = $id;

            $images = PostImages::whereIn('post_id', $ids)
                ->orderBy('created_at', 'asc')
                ->select('src AS url', 'width', 'height', 'size', 'type')
                ->get()
                ->toArray();

            foreach ($images as $i => $img)
            {
                $images[$i] = json_encode($img);
            }

            return $images;
        });

        $result = [];
        foreach ($list as $item)
        {
            $result[] = json_decode($item, true);
        }

        return $result;
    }

    public function trialPass($post)
    {
        $searchService = new Search();
        $searchService->create(
            $post['id'],
            $post['title'] . ',' . $post['desc'],
            'post'
        );

        $trendingService = new PostTrendingService();
        $trendingService->create($post['id']);

        $job = (new \App\Jobs\Push\Baidu('post/trending/new', 'update'));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('post/' . $post['id']));
        dispatch($job);

        $job = (new \App\Jobs\Push\Baidu('bangumi/' . $post['bangumi_id'], 'update'));
        dispatch($job);
    }
}