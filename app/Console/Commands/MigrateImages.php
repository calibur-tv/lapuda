<?php

namespace App\Console\Commands;

use App\Models\AlbumImage;
use App\Models\Bangumi;
use App\Models\Image;
use App\Models\ImageAlbum;
use App\Models\PostImages;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MigrateImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'image:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '将 album_images 中的数据迁移到 post_images 中';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $bangumis = Bangumi::withTrashed()->get();
        $albumBangumis = [];
        foreach ($bangumis as $bangumi) {
            $type = pathinfo($bangumi->banner, PATHINFO_EXTENSION);

            $albumBangumis[$bangumi->id] = Image::insertGetId([
                'user_id' => 0,
                'bangumi_id' => $bangumi->id,
                'name' => "{$bangumi->name}默认相册",
                'url' => $bangumi->banner,
                'width' => 1920,
                'height' => 1080,
                'size' => 0,
                'type' => "image/{$type}",
                'part' => 0,
                'state' => 0,
                'is_cartoon' => 0,
                'is_album' => 0,
                'is_creator' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'album_type' => 1,
            ]);
        }

        $images = [];
        $lastId = 1;
        do {
            $images = AlbumImage::where('id', '>', $lastId)->limit(10)->get();
            $images = $images->toArray();

            foreach ($images as $image) {
                $type = pathinfo($image['url'], PATHINFO_EXTENSION);
                $lastId = PostImages::insertGetId([
                    'post_id' => 0,
                    'comment_id' => 0,
                    'url' => $image['url'],
                    'created_at' => $image['created_at'],
                    'updated_at' => Carbon::now(),
                    'size' => $image['size'],
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'type' => $image["image/{$type}"],
                ]);

                ImageAlbum::insert([
                    'album_id' => $albumBangumis[$image['bangumi_id']],
                    'image_id' => $lastId,
                    'rank' => microtime(true) * 10000,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        } while (!empty($images));
    }
}
