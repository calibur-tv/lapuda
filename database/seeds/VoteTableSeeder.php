<?php

use Illuminate\Database\Seeder;

class VoteTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $vote = new \App\Models\Vote([
            'title' => Faker\Factory::create()->sentence,
            'description' => Faker\Factory::create()->sentence,
            'post_id' => 1,
        ]);

        $vote->save();
    }
}
