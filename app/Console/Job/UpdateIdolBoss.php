<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Models\CartoonRole;
use Illuminate\Console\Command;

class UpdateIdolBoss extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UpdateIdolBoss';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'update idol boss';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $ids = CartoonRole
            ::where('fans_count', '>=', 20)
            ->pluck('id')
            ->toArray();

        $cartoonRoleRepository = new CartoonRoleRepository();
        foreach ($ids as $idolId)
        {
            $cartoonRoleRepository->setIdolBiggestBoss($idolId);
        }

        return true;
    }
}