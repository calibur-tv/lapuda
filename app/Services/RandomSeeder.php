<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/12/28
 * Time: 上午8:49
 */

namespace App\Services;


class RandomSeeder
{
    public function seed($average, $max)
    {
        $delta = $max - $average;
        $result = $this->purebell(0, 2 * $delta);

        return abs($result - $delta) + $average * 2;
    }

    // 正态分布
    // https://natedenlinger.com/php-random-number-generator-with-normal-distribution-bell-curve/
    /**
     * $min 最小值
     * $max 最大值
     * $std_deviation 标准差
     * $step int 步长？
     * @return float
     */
    private function purebell($min, $max, $std_deviation = 1, $step = 1)
    {
        $rand1 = (float)mt_rand() / (float)mt_getrandmax();
        $rand2 = (float)mt_rand() / (float)mt_getrandmax();
        $gaussian_number = sqrt(-2 * log($rand1)) * cos(2 * M_PI * $rand2);
        $mean = ($max + $min) / 2;
        $random_number = ($gaussian_number * $std_deviation) + $mean;
        $random_number = round($random_number / $step) * $step;
        if ($random_number < $min || $random_number > $max)
        {
            $random_number = $this->purebell($min, $max, $std_deviation);
        }
        return $random_number;
    }
}
