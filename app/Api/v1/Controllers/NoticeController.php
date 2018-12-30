<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/23
 * Time: 下午10:39
 */

namespace App\Api\V1\Controllers;


use App\Api\V1\Repositories\Repository;
use App\Api\V1\Repositories\UserRepository;
use App\Models\Score;
use App\Models\SystemNotice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class NoticeController extends Controller
{
    // 展示
    public function show($id)
    {
        $repository = new Repository();
        $result = $repository->Cache('system_notice_item_' . $id, function () use ($id)
        {
            $notice = SystemNotice
                ::where('id', $id)
                ->first();

            if (!$notice)
            {
                return null;
            }

            $notice['content'] = $this->formatJsonContent($notice['content']);

            $banner = $notice['banner'];
            $banner = json_decode($banner, true);
            $banner['url'] = config('website.image') . $banner['url'];

            $notice['banner'] = $banner;

            return $notice;
        });

        return $this->resOK($result);
    }

    // 列表
    public function list()
    {
        $userRepository = new UserRepository();
        $result = $userRepository->Cache('system_notice_list', function () use ($userRepository)
        {
            $list = SystemNotice
                ::orderBy('id', 'DESC')
                ->select('id', 'title', 'banner', 'created_at')
                ->get()
                ->toArray();

            if (!$list)
            {
                return [];
            }

            $user = $userRepository->item(2);
            $formatUser = [
                'id' => $user['id'],
                'zone' => $user['zone'],
                'avatar' => $user['avatar'],
                'nickname' => $user['nickname']
            ];

            $result = [];
            foreach ($list as $item)
            {
                $banner = json_decode($item['banner'], true);

                $result[] = [
                    'user' => $formatUser,
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'banner' => config('website.image') . $banner['url'],
                    'created_at' => $item['created_at']
                ];
            }

            return $result;
        });

        return $this->resOK($result);
    }

    // 设置最后已读的消息id
    public function mark(Request $request)
    {
        $userId = $this->getAuthUserId();
        $lastId = $request->get('id');

        User::where('id', $userId)
            ->update([
                'last_notice_read_id' => $lastId
            ]);

        return $this->resOK('success');
    }

    // 创建【借用漫评的编辑器，生成一个草稿漫评，然后转化为消息通知】
    public function create(Request $request)
    {
        $id = $request->get('id');
        $score = Score::where('id', $id)->first();
        $repository = new Repository();
        if ($score['user_id'] != 1)
        {
            return $this->resErrRole();
        }

        $notice = SystemNotice::create([
            'title' => $score['title'],
            'banner' => $score['banner'],
            'content' => $score['content']
        ]);

        Redis::DEL('system_notice_list');
        Redis::DEL('system_notice_id_list');
        $repository->Cache('system_notice_lastest_item', function () use ($notice)
        {
            return [
                'id' => $notice['id'],
                'title' => $notice['title'],
                'created_at' => $notice['created_at']
            ];
        });

        return $this->resOK();
    }

    // 更新
    public function update(Request $request)
    {
        $scoreId = $request->get('score_id');
        $noticeId = $request->get('notice_id');
        $score = Score::where('id', $scoreId)->first();
        if ($score['user_id'] != 1)
        {
            return $this->resErrRole();
        }

        SystemNotice
            ::where('id', $noticeId)
            ->update([
                'title' => $score['title'],
                'banner' => $score['banner'],
                'content' => $score['content']
            ]);

        Redis::DEL('system_notice_list');

        return $this->resOK();
    }

    // 删除
    public function delete(Request $request)
    {
        $id = $request->get('id');
        $userId = $this->getAuthUserId();
        if ($userId !== 1)
        {
            return $this->resErrRole();
        }

        SystemNotice::where('id', $id)->delete();

        Redis::DEL('system_notice_list');
        Redis::DEL('system_notice_id_list');

        return $this->resOK();
    }
}