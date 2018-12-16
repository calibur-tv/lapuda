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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Redis;

class VoteRepository extends Repository
{
    public function createVote($title, $description, $postId, $expiredAt, $multiple)
    {
        if (!empty($expiredAt)) {
            $expiredAt = Carbon::createFromTimestamp($expiredAt);
            if (!is_null($expiredAt)) {
                $expiredAt = $expiredAt->format('Y-m-d H:i:s');
            }
        }

        $vote = new Vote([
            'post_id' => $postId,
            'title' => $title,
            'description' => $description,
            'expired_at' => $expiredAt,
            'multiple' => $multiple,
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

    public function createVoteUser($voteId, $voteItemIds, $userId)
    {
        $votes = new Collection();
        foreach ($voteItemIds as $voteItemId) {
            $vote = new VoteItemUser([
                'user_id' => $userId,
                'vote_id' => $voteId,
                'vote_item_id' => $voteItemId,
            ]);

            $vote->saveOrFail();

            $votes->add($vote);
        }

        return $votes;
    }

    public function riseAmountOfVoteItem($voteId, $voteItemIds)
    {
        $items = new Collection();

        foreach ($voteItemIds as $voteItemId) {
            $voteItem = VoteItem::where('vote_id', $voteId)->lockForUpdate()->findOrFail($voteItemId);

            $voteItem->amount += 1;
            $voteItem->saveOrFail();

            if (Redis::EXISTS('vote:item:' . $voteItemId)) {
                Redis::HINCRBY('vote:item:' . $voteItemId, 'amount', 1);
            }

            $items->add($voteItem);
        }

        return $items;
    }

    public function getVoteByPostId($postId)
    {
        $voteId = $this->RedisItem('vote:post:' . $postId, function () use ($postId) {
            $vote = Vote::where('post_id', $postId)->with('items')->first();

            return $vote->id ?? null;
        });

        if (is_null($voteId)) {
            return null;
        }

        $vote = $this->getVoteByVoteId($voteId);

        return $vote;
    }

    public function getVoteByVoteId($voteId)
    {
        $vote = $this->RedisHash('vote:' . $voteId, function () use ($voteId) {
            try {
                $vote = Vote::findOrFail($voteId);
            } catch (\Exception $e) {
                return null;
            }

            return $vote->toArray();
        });

        if (!empty($vote)) {
            $vote['items'] = $this->getVoteItemsByVoteId($voteId);
        }

        return $vote;
    }

    public function getVoteItemsByVoteId($voteId)
    {
        $items = [];

        $itemIds = $this->redisSet('vote:items:' . $voteId, function () use ($voteId) {
            $itemIds = [];

            $items = VoteItem::where('vote_id', $voteId)->get()->toArray();

            foreach ($items as $item) {
                $itemIds[] = $item['id'];
            }

            return $itemIds;
        });

        foreach ($itemIds as $itemId) {
            $item = $this->getVoteItemByItemId($itemId);
            if (!empty($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function getVoteItemByItemId($itemId)
    {
        $item = $this->RedisHash('vote:item:' . $itemId, function () use ($itemId) {
            try {
                $item = VoteItem::findOrFail($itemId);
            } catch (\Exception $e) {
                return null;
            }

            return $item->toArray();
        });

        return $item;
    }
}