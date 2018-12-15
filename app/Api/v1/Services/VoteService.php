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
    public function create($postId, $title, $description, $items)
    {
        $repository = new VoteRepository;

        $vote = $repository->createVote($title, $description, $postId);

        $repository->createVoteItem($items, $vote->id);
    }
}