<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PostController extends Controller
{
    // 一共有4个缓存
    // user-{$uid}-post     用户页列表
    // bangumi-${bid}-post  番剧页列表
    // post-${pid}          帖子楼层列表
    // post-${pid}-image    帖子内所有图片列表
    // 都是 list 数据类型
    public function create(Request $request)
    {
        // 只有新建帖子才走 create，其它都走 reply
        // 创建一个 PostRepository，有一个 create 方法
        // 要将创建好的帖子进行缓存，写入 user-{$uid}-post 和 bangumi-${bid}-post 里（更新列表缓存））
        // 帖子也要新建一个 post-${pid} 格式做缓存
        // 使用 Purifier 过滤用户传输
        // 将帖子中的图片提取出来做缓存 post-${pid}-image
    }

    public function show(Request $request, $id)
    {
        // 使用 Redis list 做缓存
        // 使用 seen_ids 做分页
        // 应该 new 一个 PostRepository，有一个 list 的方法
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
        // 如果是主题帖，从 user-{$uid}-post 和 bangumi-${bid}-post 中删除
        // 如果是回帖，删除 postList 的缓存，并修改 user-{$uid}-post 和 bangumi-${bid}-post 的信息
    }
}
