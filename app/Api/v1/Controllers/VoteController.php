<?php

namespace App\Api\V1\Controllers;

use App\Api\v1\Repositories\VoteRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VoteController extends Controller
{
    public function up($voteId, Request $request)
    {
        $repository = new VoteRepository();

        $voted = $repository->voted($voteId, $this->getAuthUserId());

        if ($voted) {
            return response([
                'code' => 40901,
                'message' => '你已经投过票啦',
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
