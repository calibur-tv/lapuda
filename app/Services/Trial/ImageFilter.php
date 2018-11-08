<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/6/4
 * Time: 上午6:58
 */

namespace App\Services\Trial;


use App\Services\Qiniu\Auth;
use App\Services\Qiniu\Http\Client;

class ImageFilter
{
    protected $errorLine = 0.9;
    protected $warningLine = 0.3;

    public function test($url)
    {
        $result = [
            'sex' => [
                'delete' => false,
                'review' => true,
                'label' => '未知', // 0 色情，1 性感，2 正常
                'score' => 0
            ],
            'warn' => [
                'delete' => false,
                'review' => false, // 默认设置成 false，不审核暴恐
                'label' => '未知', // 0 正常，1 暴恐
                'score' => 0
            ],
            'daddy' => [
                'delete' => false,
                'review' => true,
                'detail' => 'qpolitician test failed'
            ]
        ];
        $response = $this->validateImage($url);
        try
        {
            if ($response->statusCode !== 200)
            {
                return $result;
            }
        }
        catch (\Exception $e)
        {
            return $result;
        }

        $response = json_decode($response->body);
        if ($response->code !== 0)
        {
            return $result;
        }
        $resp = $response->result->details;
        // 色情
        foreach ($resp as $item)
        {
            if ($item->type === 'pulp')
            {
                $respSex = $item;
                $label = $respSex->label;
                $score = $respSex->score;
                $review = $respSex->review;

                if ($label !== 0 && $score < $this->warningLine)
                {
                    $review = true;
                }
                if ($label === 0)
                {
                    $review = true;
                }
                $result['sex'] = [
                    'label' => $label === 0 ? '色情' : ($label === 1 ? '性感' : '正常'),
                    'delete' => $label === 0 && $score >= $this->errorLine,
                    'review' => $review,
                    'score' => $score
                ];
            }
            else if ($item->type === 'terror')
            {
                // 暴恐
                $respWarn = $item;
                $label = $respWarn->label;
                $score = $respWarn->score;
                $review = $respWarn->review;
                if ($label === 0 && $score < $this->warningLine)
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
                    'delete' => $label === 1 && $score > $this->errorLine,
                    'score' => $score
                ];
            }
            else if ($item->type === 'politician')
            {
                $respDaddy = $item;
                $review = $respDaddy->review;
                $delete = false;
                $more = $respDaddy->more;
                if (count($more) > 0)
                {
                    foreach ($more as $face)
                    {
                        if ((isset($face->name)))
                        {
                            $review = true;
                            $delete = true;
                        }
                    }
                }
                $result['daddy'] = [
                    'delete' => $delete,
                    'review' => $review,
                    'detail' => $review ? $more : ''
                ];
            }
        }

        return $result;
    }

    public function bad($url)
    {
        $result = $this->check($url);

        return $result['review'];
    }

    public function check($url)
    {
        $defaultResult = [
            'delete' => false,
            'review' => true
        ];

        $response = $this->validateImage($url);
        try
        {
            if ($response->statusCode !== 200)
            {
                return $defaultResult;
            }
        }
        catch (\Exception $e)
        {
            return $defaultResult;
        }

        $response = json_decode($response->body);
        if ($response->code !== 0)
        {
            return $defaultResult;
        }

        $resp = $response->result;
        /* 使用宽松策略，不删除图片，只进入审核，注意政治敏感
        if ($resp->label === 1 && $resp->score > $this->errorLine)
        {
            return [
                'delete' => true,
                'review' => true
            ];
        }
        if ($resp->label === 0 && $resp->score < $this->warningLine)
        {
            return $defaultResult;
        }
        */
        return [
            'delete' => false,
            'review' => $resp->label === 1
        ];
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

    private function validateImage($src)
    {
        $host = 'argus.atlab.ai';
        $request_method = 'POST';
        $request_url = 'http://argus.atlab.ai/v1/image/censor';
        $content_type = 'application/json';
        $regex = '/^(http|https):\/\//';
        if (!preg_match($regex, $src))
        {
            $src = config('website.image') . $src;
        }

        $body = json_encode([
            'data' => [
                'uri' => $src
            ],
            'params' => [
                'type' => ['pulp', 'politician']
            ]
        ]);

        $auth = new Auth();
        $authHeaderArray = $auth->authorizationV2($request_url, $request_method, $body, $content_type);
        $authHeader = $authHeaderArray['Authorization'];
        $contentType = "application/json";
        $header = array(
            "Host" => $host ,
            "Authorization" => $authHeader,
            "Content-Type" => $contentType,
        );

        try {
            $response = Client::post($request_url, $body, $header);
        } catch (\Exception $e) {
            $response = [
                'statusCode' => 0
            ];
        }

        return $response;
    }
}