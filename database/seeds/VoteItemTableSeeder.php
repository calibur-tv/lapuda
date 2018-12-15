<?php

use Illuminate\Database\Seeder;

class VoteItemTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $voteItem = new \App\Models\VoteItem([
            'title' => Faker\Factory::create()->sentence,
            'vote_id' => 1,
        ]);

        $voteItem->save();

        $voteItem = new \App\Models\VoteItem([
            'title' => Faker\Factory::create()->sentence,
            'vote_id' => 1,
        ]);

        $voteItem->save();
    }
}
