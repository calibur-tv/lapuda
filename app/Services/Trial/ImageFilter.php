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
            if (intval($respSex['code']) === 0)
            {
                $badCount++;
            }
        }
        catch (\Exception $e)
        {
        }
        // 暴恐
        try
        {
            $respWarn = json_decode(file_get_contents($url . '?qterror'), true);
            if (intval($respWarn['code']) === 1 && (boolean)$respWarn['result']['review'])
            {
                $badCount++;
            }
        }
        catch (\Exception $e)
        {
        }
        // 政治敏感
        try
        {
            $respDaddy = json_decode(file_get_contents($url . '?qpolitician'), true);
            if ((boolean)$respDaddy['result']['review'])
            {
                $badCount++;
            }
        }
        catch (\Exception $e)
        {
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