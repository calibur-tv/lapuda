<?php

namespace App\Api\V1\Controllers;

use App\Api\v1\Repositories\VoteRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VoteController extends Controller
{
    /**
     * 投票
     *
     * @Post("/vote/{$voteId}/user")
     *
     * @Parameters({
     *      @Parameter("vote_item_id", description="选项 (在 query 中)", type="int", required=false)
     * })
     *
     * @Transaction({
     *      @Request(headers={"Authorization": "Bearer JWT-Token"}),
     *      @Response(201, body={"code": 0, "message": "投票成功"}),
     *      @Response(409, body={"code": 40901, "message": "已经投票过了"})
     *      @Response(404, body={"code": 40401, "message": "选项不存在"})
     * })
     */
    public function up($voteId, Request $request)
    {
        $repository = new VoteRepository();

        $voted = $repository->voted($voteId, $this->getAuthUserId());

        if ($voted) {
            return response([
                'code' => 40901,
                'message' => config('error.40901')
            ], Response::HTTP_CONFLICT);
        }

        $voteItemId = $request->get('vote_item_id');

        $voteItem = $repository->getItemByIdAndItemId($voteItemId, $voteId);

        if (is_null($voteItem)) {
            return $this->resErrNotFound('选项不存在');
        }

        \DB::beginTransaction();

        try {
            $repository->createVoteUser($voteId, $voteItemId, $this->getAuthUserId());

            $repository->riseAmountOfVoteItem($voteId, $voteItemId);

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();

            \Log::warning($e);

            return response([
                'code' => 500,
                'message' => '投票失败',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->resCreated('投票成功');
    }
}
