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
                'delete' => false,
                'review' => true,
                'label' => '未知', // 0 色情，1 性感，2 正常
            ],
            'warn' => [
                'delete' => false,
                'review' => true,
                'label' => '未知' // 0 正常，1 暴恐
            ],
            'daddy' => [
                'delete' => false,
                'review' => true,
                'message' => ''
            ]
        ];
        // 色情
        try
        {
            $respSex = json_decode(file_get_contents($url . '?qpulp'), true);
            if (intval($respSex['code']) === 0)
            {
                $label = $respSex['result']['label'];
                $score = $respSex['result']['score'];
                $review = $respSex['result']['review'];
                // 不是色情，但是准确率小于 50%，也进入审核
                if ($label !== 0 && $score < 0.5)
                {
                    $review = true;
                }
                if ($label === 0)
                {
                    $review = true;
                }
                $result['sex'] = [
                    'label' => $label === 0 ? '色情' : ($label === 1 ? '性感' : '正常'),
                    'delete' => $label === 0 && $score >= 0.9,
                    'review' => $review
                ];
            }
        } catch (\Exception $e) {}
        // 暴恐
        try
        {
            $respWarn = json_decode(file_get_contents($url . '?qterror'), true);
            if (intval($respWarn['code']) === 0)
            {
                $label = $respWarn['result']['label'];
                $score = $respWarn['result']['score'];
                $review = $respWarn['result']['review'];
                if ($label === 0 && $score < 0.5)
                {
                    $review = true;
                }
                if ($label === 1)
                {
                    $review = true;
                }
                $result['warn'] = [
                    'label' => $label === 0 ? '正常' : '暴恐',
                    'review' => $review,
                    'delete' => $label === 1 && $score > 0.9
                ];
            }
        } catch (\Exception $e) {}
        // 政治敏感
        try
        {
            $respDaddy = json_decode(file_get_contents($url . '?qpolitician'), true);
            if (intval($respDaddy['code']) === 0)
            {
                $review = $respDaddy['result']['review'];
                $delete = false;
                $detections = $respDaddy['result']['detections'][0];
                if (count($detections) > 0)
                {
                    $review = true;
                    $delete = true;
                }
                $result['daddy'] = [
                    'delete' => $delete,
                    'review' => $review,
                    'label' => $respDaddy['message']
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