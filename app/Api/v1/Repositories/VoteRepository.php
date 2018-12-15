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
use Illuminate\Database\Eloquent\Collection;

class VoteRepository extends Repository
{
    public function createVote($title, $description, $postId)
    {
        $vote = new Vote([
            'post_id' => $postId,
            'title' => $title,
            'description' => $description,
        ]);

        $vote->saveOrFail();

        return $vote;
    }

    public function createVoteItem($items, $voteId)
    {
        array_walk($items, function (&$item) use ($voteId) {
            $item = new VoteItem([
                'title' => $item['title'],
                'vote_id' => $voteId,
            ]);

            $item->saveOrFail();
        });

        return $items;
    }

    public function voted($voteId, $userId)
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
        $voteItem = VoteItem::where('vote_id', $voteId)->lockForUpdate()->findOrFail($voteItemId);

        $voteItem->amount += 1;
        $voteItem->saveOrFail();

        return $voteItem;
    }

    public function getVoteByPostId($postId)
    {
        $vote = Vote::where('post_id', $postId)->with('items')->first();

        return $vote->toArray();
    }
}