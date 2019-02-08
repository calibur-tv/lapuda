<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\LightCoinService;
use App\Api\V1\Services\VirtualCoinService;
use App\Models\CartoonRole;
use App\Models\CartoonRoleFans;
use App\Models\LightCoin;
use App\Models\LightCoinRecord;
use App\Models\User;
use App\Models\UserSign;
use Illuminate\Http\Request;
use App\Services\OpenSearch\Search;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("搜索相关接口")
 */
class SearchController extends Controller
{
    /**
     * 搜索接口
     *
     * > 目前支持的参数格式：
     * type：all, user, bangumi, video，post，role，image，score，question，answer
     * 返回的数据与 flow/list 返回的相同
     *
     * @Get("/search/new")
     *
     * @Parameters({
     *      @Parameter("type", description="要检测的类型", type="string", required=true),
     *      @Parameter("q", description="搜索的关键词", type="string", required=true),
     *      @Parameter("page", description="搜索的页码", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body="数据列表")
     * })
     */
    public function search(Request $request)
    {
        $key = Purifier::clean($request->get('q'));

        if (!$key)
        {
            return $this->resOK();
        }

        $type = $request->get('type') ?: 'all';
        $page = intval($request->get('page')) ?: 0;

        $search = new Search();
        $result = $search->retrieve(strtolower($key), $type, $page);

        return $this->resOK($result);
    }

    /**
     * 获取所有番剧列表
     *
     * > 返回所有的番剧列表，用户搜索提示，可以有效减少请求数
     *
     * @Get("/search/bangumis")
     *
     * @Transaction({
     *      @Response(200, body="番剧列表")
     * })
     */
    public function bangumis()
    {
        $bangumiRepository = new BangumiRepository();

        return $this->resOK($bangumiRepository->searchAll());
    }

    // 同步签到
    public function migration_step_1()
    {
        $sign = UserSign
            ::where('migration_state', 0)
            ->take(10000)
            ->get()
            ->toArray();

        return $this->resOK('what\'s wrong?');

        if (empty($sign))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($sign as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->daySign($item['user_id']);

            UserSign
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // 同步邀请
    public function migration_step_2()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 1)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->get()
            ->toArray();

        if (empty($records))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->inviteUser($item['to_user_id'], $item['from_user_id'], $item['order_amount']);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // 同步被邀请
    public function migration_step_3()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 17)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->get()
            ->toArray();

        if (empty($records))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->invitedNewbieCoinGift($item['from_user_id'], $item['to_user_id'], $item['order_amount']);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // 同步普通用户活跃送团子
    public function migration_step_4()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 2)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->get()
            ->toArray();

        if (empty($records))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->userActivityReward($item['to_user_id']);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // 同步版主活跃送光玉
    public function migration_step_5()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->whereIn('to_product_type', [3, 16])
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->get()
            ->toArray();

        if (empty($records))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->masterActiveReward($item['to_user_id'], $item['to_product_type'] == 16);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // 同步赠送团子给用户
    public function migration_step_6()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 13)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->get()
            ->toArray();

        if (empty($records))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->coinGift($item['to_user_id'], $item['order_amount']);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // 同步赠送光玉给用户
    public function migration_step_7()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 14)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->get()
            ->toArray();

        if (empty($records))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->lightGift($item['to_user_id'], $item['order_amount']);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // 同步打赏内容
    public function migration_step_8()
    {
        $table = 'post_reward';
        $list = DB
            ::table($table)
            ->where('migration_state', 0)
            ->get()
            ->toArray();

        if (empty($list))
        {
            $table = 'video_reward';
            $list = DB
                ::table($table)
                ->where('migration_state', 0)
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            $table = 'score_reward';
            $list = DB
                ::table($table)
                ->where('migration_state', 0)
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            $table = 'image_reward';
            $list = DB
                ::table($table)
                ->where('migration_state', 0)
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            $table = 'answer_reward';
            $list = DB
                ::table($table)
                ->where('migration_state', 0)
                ->get()
                ->toArray();
        }

        if (empty($list))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($list as $item)
        {
            $coinService->setTime($item->created_at);
            $modelType = explode('_', $table)[0];
            $repository = $this->getRepositoryByType($modelType);
            $model = $repository->item($item->modal_id, true);
            $coinService->rewardUserContent($modelType, $item->user_id, $model->user_id, $item->modal_id);

            DB
                ::table($table)
                ->where('id', $item->id)
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // TODO：同步原创内容被删除
    public function migration_step_9()
    {
        return $this->resOK('todo');
    }

    // 同步应援偶像
    public function migration_step_10()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 9)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->take(1000)
            ->get()
            ->toArray();

        if (empty($records))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->cheerForIdol($item['from_user_id'], $item['to_product_id']);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // 同步提现
    public function migration_step_11()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 10)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->get()
            ->toArray();

        if (empty($records))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->withdraw($item['from_user_id'], $item['order_amount'] < 100 ? 100 : $item['order_amount']);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // 同步承包视频
    public function migration_step_12()
    {
        $records = LightCoinRecord
            ::where('migration_state', 0)
            ->where('to_product_type', 18)
            ->orderBy('created_at', 'DESC')
            ->groupBy('order_id')
            ->get()
            ->toArray();

        if (empty($records))
        {
            return $this->resOK('done');
        }

        $coinService = new VirtualCoinService();
        foreach ($records as $item)
        {
            $coinService->setTime($item['created_at']);
            $coinService->buyVideoPackage($item['from_user_id'], $item['to_product_id'], 10);

            LightCoinRecord
                ::where('id', $item['id'])
                ->update([
                    'migration_state' => 1
                ]);
        }

        return $this->resOK('success');
    }

    // TODO：同步撤销应援
    public function migration_step_13()
    {
        return $this->resOK('todo');
    }

    private function getRepositoryByType($type)
    {
        switch ($type)
        {
            case 'post':
                return new PostRepository();
                break;
            case 'image':
                return new ImageRepository();
                break;
            case 'score':
                return new ScoreRepository();
                break;
            case 'answer':
                return new AnswerRepository();
                break;
            case 'video':
                return new VideoRepository();
                break;
            default:
                return null;
        }
    }
}
