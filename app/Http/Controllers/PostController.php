<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PostController extends Controller
{
    public function create(Request $request)
    {
        // 只有新建帖子才走 create，其它都走 reply
        // 创建一个 PostRepository，有一个 create 方法
        // 新建一个 post-${pid} 格式做缓存
        // 使用 Purifier 过滤用户传输
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
