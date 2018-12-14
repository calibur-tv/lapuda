<?php
/**
 * file description
 *
 * @version
 * @author daryl
 * @date 2018-12-14
 * @since 2018-12-14 description
 */

namespace App\Api\v1\Repositories;

use App\Models\Vote;
use App\Models\VoteItem;
use App\Models\VoteItemUser;

class VoteRepository extends Repository
{
    public function voted($userId, $voteId)
    {
        $vote = VoteItemUser::where('user_id', $userId)->where('vote_id', $voteId)->first();

        return !is_null($vote);
    }

    public function getItemByIdAndItemId($id, $voteId)
    {
        $voteItem = VoteItem::where('vote_id', $voteId)->find($id);

        return $voteItem;
    }

    public function createVoteUser($voteId, $voteItemId, $userId)
    {
        $vote = new VoteItemUser([
            'user_id' => $userId,
            'vote_id' => $voteId,
            'vote_item_id' => $voteItemId,
        ]);

        $vote->saveOrFail();

        return $vote;
    }

    public function riseAmountOfVoteItem($voteId, $voteItemId)
    {
        $voteItem = VoteItem::where('vote_id', $voteId)->findOrFail($voteItemId);

        $voteItem->amount += 1;
        $voteItem->saveOrFail();

        return $voteItem;
    }
}