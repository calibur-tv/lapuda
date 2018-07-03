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
    public function test($url)
    {
        $result = [
            'sex' => [
                'success' => false,
                'review' => false,
                'label' => '未知', // 0 色情，1 性感，2 正常
            ],
            'warn' => [
                'success' => false,
                'review' => false,
                'label' => '未知' // 0 正常，1 暴恐
            ],
            'daddy' => [
                'success' => false,
                'review' => false,
                'message' => 'qpolitician test failed'
            ]
        ];

        try
        {
            $respSex = json_decode(file_get_contents($url . '?qpulp'), true);
            if (intval($respSex['code']) === 0)
            {
                $label = $respSex['result']['label'];
                $result['sex'] = [
                    'success' => true,
                    'label' => $label === 0 ? '色情' : ($label === 1 ? '性感' : '正常'),
                    'review' => $respSex['result']['review']
                ];
            }
        } catch (\Exception $e) {}

        try
        {
            $respWarn = json_decode(file_get_contents($url . '?qterror'), true);
            if (intval($respWarn['code']) === 0)
            {
                $label = $respWarn['result']['label'];
                $result['daddy'] = [
                    'success' => true,
                    'label' => $label === 0 ? '正常' : '暴恐',
                    'review' => $respWarn['result']['review']
                ];
            }
        } catch (\Exception $e) {}

        try
        {
            $respDaddy = json_decode(file_get_contents($url . '?qpolitician'), true);
            if (intval($respDaddy['code']) === 0)
            {
                $result['warn'] = [
                    'success' => true,
                    'message' => $respDaddy['message'],
                    'review' => $respDaddy['result']['review']
                ];
            }
        } catch (\Exception $e) {}

        return $result;
    }

    public function bad($url)
    {
        $result = $this->test($url);

        if ($result['sex']['review'] || $result['warn']['review'] || $result['daddy']['review'])
        {
            return true;
        }
        return false;
    }

    public function exec($url)
    {
        return $this->bad($url) ? 1 : 0;
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