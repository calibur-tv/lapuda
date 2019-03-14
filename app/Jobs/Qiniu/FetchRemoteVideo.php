<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-03-14
 * Time: 21:39
 */

namespace App\Jobs\Qiniu;

use App\Models\Video;
use App\Services\Qiniu\Qshell;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Redis;

class FetchRemoteVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $videoId;
    protected $src;

    public function __construct($id, $src)
    {
        $this->videoId = $id;
        $this->src = $src;
    }

    public function handle()
    {
        $qshell = new Qshell();
        $filename = $qshell->sync($this->src);

        Video
            ::withTrashed()
            ->where('id', $this->videoId)
            ->update([
                'src_v2' => $filename
            ]);

        Redis::DEL("video-{$this->videoId}");
    }
}