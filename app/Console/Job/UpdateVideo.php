<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateVideo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateVideo';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update video';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Video
            ::where('resource', '<>', '')
            ->where('src_v2', '')
            ->chunk(100, function ($list)
            {
                foreach ($list as $video)
                {
                    $video = $video->toArray();
                    $src_v2 = "";
                    $delete_src = "";
                    $resource = $video['resource'] === 'null' ? null : json_decode($video['resource'], true);

                    if (isset($resource['video'][720]) && isset($resource['video'][720]['src']) && $resource['video'][720]['src'])
                    {
                        $src_v2 = $resource['video'][720]['src'];
                    }
                    if (isset($resource['video'][1080]) && isset($resource['video'][1080]['src']) && $resource['video'][1080]['src'])
                    {
                        $src_v2 = $resource['video'][1080]['src'];
                    }
                    if (isset($resource['video'][480]) && isset($resource['video'][480]['src']) && $resource['video'][480]['src'])
                    {
                        $delete_src = $resource['video'][480]['src'];
                    }

                    Video
                        ::where('id', $video['id'])
                        ->update([
                            'src_v2' => $src_v2,
                            'delete_src' => $delete_src,
                        ]);
                }
            });

        return true;
    }
}