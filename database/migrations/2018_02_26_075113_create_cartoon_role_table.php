<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCartoonRoleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cartoon_role', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('bangumi_id');
            $table->string('avatar');
            $table->string('name');
            $table->string('intro');
            $table->text('summary');
            $table->unsignedInteger('star_count');
            $table->unsignedInteger('fans_count');
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
        Schema::dropIfExists('cartoon_role');
    }
}
