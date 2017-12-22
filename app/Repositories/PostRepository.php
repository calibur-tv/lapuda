<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/21
 * Time: 下午8:50
 */

namespace App\Repositories;


use App\Models\Post;
use App\Models\PostImages;
use Illuminate\Support\Facades\Cache;

class PostRepository
{
    private $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    public function item($id)
    {
        $post = Cache::remember('post_'.$id, config('cache.ttl'), function () use ($id)
        {
            $data = Post::where('id', $id)->first();
            $data['images'] = PostImages::where('post_id', $id)
                ->orderBy('created_at', 'asc')
                ->pluck('src');

            return $data;
        });

        $post['user'] = $this->userRepository->item($post['user_id']);

        return $post;
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $result[] = $this->item($id);
        }
        return $result;
    }
}