<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\Repository;
use App\Api\V1\Services\Counter\CmLoopClickCounter;
use App\Api\V1\Services\Counter\CmLoopViewCounter;
use App\Models\Looper;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * @Resource("营销相关接口")
 */
class CmController extends Controller
{
    /**
     * 获取营销轮播图
     *
     * @Get("/cm/loop/list")
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "图片列表"})
     * })
     */
    public function cmLoop()
    {
        $repository = new Repository();
        $result = $repository->Cache('cm_loopers', function ()
        {
            $now = Carbon::now();

            return Looper
                ::where('begin_at', '<=', $now)
                ->where('end_at', '>', $now)
                ->orderBy('updated_at', 'DESC')
                ->select('id', 'title', 'poster', 'desc', 'link')
                ->get()
                ->toArray();

        }, 'm');

        return $this->resOK($result);
    }

    /**
     * 用户查看轮播图的上报统计
     *
     * @Post("/cm/loop/view")
     *
     * @Parameters({
     *      @Parameter("id", description="id", type="integer", required=true),
     * })
     *
     * @Transaction({
     *      @Response(204)
     * })
     */
    public function cmView(Request $request)
    {
        $id = $request->get('id');
        $cmLoopViewCounter = new CmLoopViewCounter();
        $cmLoopViewCounter->add($id);

        return $this->resNoContent();
    }

    /**
     * 用户点击轮播图的上报统计
     *
     * @Post("/cm/loop/click")
     *
     * @Parameters({
     *      @Parameter("id", description="id", type="integer", required=true),
     * })
     *
     * @Transaction({
     *      @Response(204)
     * })
     */
    public function cmClick(Request $request)
    {
        $id = $request->get('id');
        $cmLoopClickCounter = new CmLoopClickCounter();
        $cmLoopClickCounter->add($id);

        return $this->resNoContent();
    }
}
