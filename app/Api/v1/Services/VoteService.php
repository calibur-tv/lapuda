<?php
/**
 * file description
 *
 * @version
 * @author daryl
 * @date 2018-12-15
 * @since 2018-12-15 description
 */

namespace App\Api\v1\Services;


use App\Api\v1\Repositories\VoteRepository;

class VoteService
{
    public function create($postId, $title, $description, $items, $expiredAt, $multiple)
    {
        $repository = new VoteRepository;

        $vote = $repository->createVote($title, $description, $postId, $expiredAt, $multiple);

        $repository->createVoteItem($items, $vote->id);
    }

    public function up($voteId, $voteItemIds, $userId)
    {
        $repository = new VoteRepository;

        \DB::beginTransaction();

        try {
            $repository->createVoteUser($voteId, $voteItemIds, $userId);

            $repository->riseAmountOfVoteItem($voteId, $voteItemIds);

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();

            throw $e;
        }
    }
}