<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateImageAlbumsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('image_albums', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('album_id')->unsigned()->comment('相册 id');
            $table->integer('image_id')->unsigned()->comment('图片 id');
            $table->integer('rank')->unsigned()->comment('图片顺序');
            $table->timestamps();
            $table->unique(['album_id', 'image_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('image_albums');
    }
}
