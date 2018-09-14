<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/14
 * Time: 下午9:25
 */

namespace App\Jobs\Pfop;

use App\Models\Video;
use App\Services\Qiniu\Config;
use App\Services\Qiniu\Processing\PersistentFop;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class Avthumb implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function handle()
    {
        if (config('app.env') !== 'production')
        {
            return true;
        }

        $key = $this->getVideoUrl();

        if (!$key)
        {
            return true;
        }

        $auth = new \App\Services\Qiniu\Auth();
        $config = new Config();
        $pfop = new PersistentFop($auth, $config);

        $pipeline = 'avthumb';
        $force = false;
        $bucket = config('filesystems.qiniu.bucket');
        $notifyUrl = "https://api.calibur.tv/callback/qiniu/avthumb";

        $fops = "avthumb/mp4/vcodec/libx265/" . $this->base64_urlSafeEncode($bucket . '/' . explode('.', $key)[0] . '-h265.mp4');
        list($id, $err) = $pfop->execute($bucket, $key, $fops, $pipeline, $notifyUrl, $force);

        Video::where('id', $this->id)
            ->update([
                'process' => $id
            ]);

        return true;
    }

    protected function getVideoUrl()
    {
        $video = Video::where('id', $this->id)->first();

        if (is_null($video))
        {
            return '';
        }

        $resource = $video['resource'] === 'null' ? null : json_decode($video['resource'], true);

        if (isset($resource['video'][720]) && isset($resource['video'][720]['src']) && $resource['video'][720]['src'])
        {
            $src = $resource['video'][720]['src'];
            $other_site = 0;
        }
        else if (isset($resource['video'][1080]) && isset($resource['video'][1080]['src']) && $resource['video'][1080]['src'])
        {
            $src = $resource['video'][1080]['src'];
            $other_site = 0;
        }
        else
        {
            $src = $video['url'];
            $other_site = 1;
        }

        if ($other_site)
        {
            return '';
        }

        return $src;
    }

    protected function base64_urlSafeEncode($data)
    {
        $find = array('+', '/');
        $replace = array('-', '_');
        return str_replace($find, $replace, base64_encode($data));
    }
}