<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Models\Post;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;

class PostController extends Controller
{
    public function __construct()
    {
        $this->middleware('throttle:5,10')->only([
            'create'
        ]);

        $this->middleware('geetest')->only([
            'create'
        ]);
    }

    public function create(CreateRequest $request)
    {
        $user = $this->getAuthUser();
        if (is_null($user))
        {
            return $this->resErr(['未登录的用户'], 401);
        }

        $id = Post::insertGetId([
            'title' => Purifier::clean($request->get('title')),
            'content' => Purifier::clean($request->get('content')),
            'bangumi_id' => $request->get('bangumi_id'),
            'user_id' => $user->id
        ]);

        return $this->resOK($id);
    }

    public function show(Request $request, $id)
    {
        // 使用 seen_ids 做分页
        // 应该 new 一个 PostRepository，有一个 list 的方法，接收 ids 为参数
        // 根据用户，判读该帖子是否是自己的，判断该楼层是否是自己的，判断该楼层是否有赞等
    }

    public function reply(Request $request, $id)
    {
        // 要写入主题帖的缓存
    }

    public function nice($id)
    {
        // toggle 操作
    }

    public function delete($id)
    {
        // 软删除，并删除缓存中的 item
    }
}
