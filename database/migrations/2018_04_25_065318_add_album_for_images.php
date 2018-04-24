<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAlbumForImages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->boolean('is_cartoon')->default(false);
            $table->unsignedInteger('album_id')->default(0);
            $table->unsignedInteger('image_count')->deault(0);
            $table->string('name')->nullable();
            $table->text('images')->nullable();
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
            $table->dropColumn('is_cartoon');
            $table->dropColumn('album_id');
            $table->dropColumn('image_count');
            $table->dropColumn('images');
            $table->dropColumn('name');
        });
    }
}
