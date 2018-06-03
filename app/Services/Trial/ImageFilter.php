<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/4
 * Time: 上午6:58
 */

namespace App\Services\Trial;


class ImageFilter
{
    public function exec($url)
    {
        $badCount = 0;
        // 色情
        try
        {
            $respSex = json_decode(file_get_contents($url . '?qpulp'), true);
            if (intval($respSex['code']) !== 0)
            {
                $badCount++;
            }
            else
            {
                $label = intval($respSex['result']['label']);
                $review = (boolean)$respSex['result']['review'];
                if ($label === 0 || $review === true)
                {
                    $badCount++;
                }
            }
        }
        catch (\Exception $e)
        {
            $badCount++;
        }
        // 暴恐
        try
        {
            $respWarn = json_decode(file_get_contents($url . '?qterror'), true);
            if (intval($respWarn['code']) !== 0)
            {
                $badCount++;
            }
            else
            {
                $label = intval($respWarn['result']['label']);
                $review = (boolean)$respWarn['result']['review'];
                if ($label === 1 || $review)
                {
                    $badCount++;
                }
            }
        }
        catch (\Exception $e)
        {
            $badCount++;
        }
        // 政治敏感
        try
        {
            $respDaddy = json_decode(file_get_contents($url . '?qpolitician'), true);
            if (intval($respDaddy['code']) !== 0)
            {
                $badCount++;
            }
            else if ((boolean)$respDaddy['result']['review'] === true)
            {
                $badCount++;
            }
        }
        catch (\Exception $e)
        {
            $badCount++;
        }

        return $badCount;
    }

    public function list($images)
    {
        $result = 0;

        foreach ($images as $img)
        {
            $result += $this->exec($img);
        }

        return $result;
    }
}