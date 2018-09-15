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
        $queues = ['avthumb', 'avthumb-0', 'avthumb-1', 'avthumb-2'];

        $pipeline = $queues[rand(0, 3)];
        $force = false;
        $bucket = config('filesystems.qiniu.bucket');
        $notifyUrl = "https://api.calibur.tv/callback/qiniu/avthumb?id=" . $this->id;
        $namePrefix = $bucket . ':' . str_replace(' ', '-', strtolower(explode('.', $key)[0]));

        $fops = array(
            "avthumb/mp4/acodec/aac/ar/44100/ab/128k/vcodec/libx264/vb/3.2m/s/1280x720/autoscale/1|saveas/" . $this->base64_urlSafeEncode($namePrefix . '-720.mp4'),
            "avthumb/mp4/acodec/aac/ar/44100/ab/64k/vcodec/libx264/vb/1.6m/s/848x480/autoscale/1|saveas/" . $this->base64_urlSafeEncode($namePrefix . '-480.mp4')
        );

        list($id, $err) = $pfop->execute($bucket, $key, $fops, $pipeline, $notifyUrl, $force);

        if ($err == null)
        {
            Video
                ::where('id', $this->id)
                ->update([
                    'process' => $id
                ]);
        }

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
        if (is_null($resource))
        {
            return '';
        }

        if (isset($resource['video']['0']))
        {
            $src = $resource['video']['0']['src'];
            $other_site = 0;
        }
        else if (isset($resource['video']['720']))
        {
            $src = $resource['video']['720']['src'];
            $other_site = 0;
        }
        else if (isset($resource['video']['1080']))
        {
            $src = $resource['video']['1080']['src'];
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