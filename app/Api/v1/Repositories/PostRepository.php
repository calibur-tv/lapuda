<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/21
 * Time: 下午8:50
 */

namespace App\Api\V1\Repositories;


use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Trending\TrendingService;
use App\Models\Post;
use App\Models\PostImages;
use App\Models\PostLike;
use App\Models\PostMark;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class PostRepository extends Repository
{
    private $userRepository;
    private $bangumiRepository;

    public function bangumiListCacheKey($bangumiId, $listType = 'new')
    {
        return 'bangumi_'.$bangumiId.'_posts_'.$listType.'_ids';
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

        Post::where('id', $id)->increment('comment_count');

        $trendingService = new TrendingService('posts');
        $trendingService->update($id);

        // 更新番剧帖子列表的缓存
        $this->SortAdd($this->bangumiListCacheKey($post['bangumi_id']), $id);

        Redis::pipeline(function ($pipe) use ($id, $newId, $userId)
        {
            // 更新用户回复帖子列表的缓存
            $pipe->LPUSHX('user_'.$userId.'_replyPostIds', $newId);
            // 更新帖子楼层的
            $pipe->RPUSHX('post_'.$id.'_ids', $newId);
        });
    }

    public function item($id)
    {
        if (!$id)
        {
            return null;
        }

        return $this->Cache('post_' . $id, function () use ($id)
        {
            $post = Post::find($id);

            if (is_null($post))
            {
                return null;
            }
            $post = $post->toArray();

            if (is_null($this->userRepository))
            {
                $this->userRepository = new UserRepository();
            }

            $user = $this->userRepository->item($post['user_id']);
            if (is_null($user))
            {
                return null;
            }

            if (is_null($this->bangumiRepository))
            {
                $this->bangumiRepository = new BangumiRepository();
            }

            $bangumi = $this->bangumiRepository->item($post['bangumi_id']);
            if (is_null($bangumi))
            {
                return null;
            }

            $images = PostImages::where('post_id', $id)
                ->orderBy('created_at', 'ASC')
                ->select('src AS url', 'width', 'height', 'size', 'type')
                ->get()
                ->toArray();

            foreach ($images as $i => $img)
            {
                $images[$i]['url'] = config('website.image') . $img['url'];
            }

            $post['images'] = $images;

            return $post;
        });
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->item($id);
            if ($item) {
                $result[] = $item;
            }
        }
        return $result;
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
                $img['url'] = config('website.image') . $img['url'];
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
}