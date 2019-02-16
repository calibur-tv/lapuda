<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterImagesTransferColumnsIsAlbumAndIsCartonToAlbumType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->tinyInteger('album_type')->default(1)->comment('相册类型 1-相册，3-漫画');
            //$table->dropColumn('is_album');
            //$table->dropColumn('is_cartoon');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('images', function (Blueprint $table) {
            //$table->boolean('is_cartoon')->default(false)->comment('是否是漫画');
            //able->boolean('is_album')->default(false)->comment('是否是相册');
            $table->dropColumn('album_type');
        });
    }
}
