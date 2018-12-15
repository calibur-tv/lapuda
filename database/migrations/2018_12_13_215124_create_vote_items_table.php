<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVoteItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vote_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->comment('投票选项标题');
            $table->integer('amount')->comment('投票数')->default(0);
            $table->unsignedInteger('vote_id')->comment('投票 id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vote_items');
    }
}
