<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/21
 * Time: 下午8:36
 */

namespace App\Jobs\Search;

use App\Api\V1\Services\Comment\ImageCommentService;
use App\Api\V1\Services\Comment\PostCommentService;
use App\Api\V1\Services\Comment\ScoreCommentService;
use App\Api\V1\Services\Comment\VideoCommentService;
use App\Api\V1\Services\Counter\ImageViewCounter;
use App\Api\V1\Services\Counter\PostViewCounter;
use App\Api\V1\Services\Counter\ScoreViewCounter;
use App\Api\V1\Services\Toggle\Bangumi\BangumiFollowService;
use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Services\Toggle\Image\ImageMarkService;
use App\Api\V1\Services\Toggle\Image\ImageRewardService;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Post\PostMarkService;
use App\Api\V1\Services\Toggle\Post\PostRewardService;
use App\Api\V1\Services\Toggle\Score\ScoreLikeService;
use App\Api\V1\Services\Toggle\Score\ScoreMarkService;
use App\Api\V1\Services\Toggle\Score\ScoreRewardService;
use App\Models\AlbumImage;
use App\Models\CartoonRole;
use App\Models\Image;
use App\Models\Post;
use App\Models\Score;
use App\Models\Video;
use App\Services\OpenSearch\Search;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class UpdateWeight implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $model;
    protected $id;

    public function __construct($model, $id)
    {
        $this->model = $model;
        $this->id = $id;
    }

    public function handle()
    {
        $search = new Search();
        $searchType = $search->computeModalIdByStr($this->model);
        if (!$searchType)
        {
            return;
        }

        $hasData = DB
            ::table('search_v3')
            ->whereRaw('type_id = ? and modal_id = ?', [$this->id, $searchType])
            ->count();

        if (!$hasData)
        {
            $repository = $search->getRepositoryByType($searchType);
            $repository->migrateSearchIndex('C', $this->id, false);
        }

        $weight = 0;
        if ($this->model === 'user')
        {
            $postCount = Post::where('user_id', $this->id)->count();
            $albumCount = Image::where('user_id', $this->id)->count();
            $imageCount = AlbumImage::where('user_id', $this->id)->count();
            $scoreCount = Score::where('user_id', $this->id)->count();

            $weight = $postCount * 2 + $albumCount + $imageCount + $scoreCount * 3;
        }
        else if ($this->model === 'bangumi')
        {
            $bangumiFollowService = new BangumiFollowService();
            $followCount = $bangumiFollowService->total($this->id);
            $postCount = Post::where('bangumi_id', $this->id)->count();
            $scoreCount = Score::where('bangumi_id', $this->id)->count();
            $imageCount = Image::where('bangumi_id', $this->id)->count();
            $videoCount = Video::where('bangumi_id', $this->id)->count();

            $weight = 10000 + $followCount + $postCount * 2 + $scoreCount * 4 + $imageCount * 3 + $videoCount;
        }
        else if ($this->model === 'video')
        {
            $playCount = Video::where('id', $this->id)->pluck('count_played')->first();
            $videoCommentService = new VideoCommentService();
            $commentCount = $videoCommentService->getCommentCount($this->id);

            $weight = intval($playCount / 10) + $commentCount * 2;
        }
        else if ($this->model === 'post')
        {
            $postViewCounter = new PostViewCounter();
            $viewCount = $postViewCounter->get($this->id);
            $postCommentService = new PostCommentService();
            $commentCount = $postCommentService->getCommentCount($this->id);
            $postLikeService = new PostLikeService();
            $likeCount = $postLikeService->total($this->id);
            $postMarkService = new PostMarkService();
            $markCount = $postMarkService->total($this->id);
            $postRewardService = new PostRewardService();
            $rewardCount = $postRewardService->total($this->id);

            $weight = $viewCount + $commentCount * 2 + $likeCount * 2 + $markCount * 3 + $rewardCount * 5;
        }
        else if ($this->model === 'role')
        {
            $weight = CartoonRole::where('id', $this->id)->pluck('star_count')->first();
        }
        else if ($this->model === 'image')
        {
            $imageViewCounter = new ImageViewCounter();
            $viewCount = $imageViewCounter->get($this->id);
            $imageCommentService = new ImageCommentService();
            $commentCount = $imageCommentService->getCommentCount($this->id);
            $imageLikeService = new ImageLikeService();
            $likeCount = $imageLikeService->total($this->id);
            $imageMarkService = new ImageMarkService();
            $markCount = $imageMarkService->total($this->id);
            $imageRewardService = new ImageRewardService();
            $rewardCount = $imageRewardService->total($this->id);

            $weight = $viewCount + $commentCount * 2 + $likeCount * 2 + $markCount * 3 + $rewardCount * 5;
        }
        else if ($this->model === 'score')
        {
            $scoreViewCounter = new ScoreViewCounter();
            $viewCount = $scoreViewCounter->get($this->id);
            $scoreCommentService = new ScoreCommentService();
            $commentCount = $scoreCommentService->getCommentCount($this->id);
            $scoreLikeService = new ScoreLikeService();
            $likeCount = $scoreLikeService->total($this->id);
            $scoreMarkService = new ScoreMarkService();
            $markCount = $scoreMarkService->total($this->id);
            $scoreRewardService = new ScoreRewardService();
            $rewardCount = $scoreRewardService->total($this->id);

            $weight = $viewCount + $commentCount * 2 + $likeCount * 2 + $markCount * 3 + $rewardCount * 5;
        }

        $search->weight($this->id, $this->model, $weight);
    }
}