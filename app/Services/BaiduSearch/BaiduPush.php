<?php

namespace App\Services\BaiduSearch;

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/18
 * Time: 下午1:22
 */
class BaiduPush
{
    public function create($id, $modal)
    {
        $pushUrl = $this->computePushUrl('urls');
        $pushContent = $this->computeContentUrl($id, $modal);

        $this->push($pushUrl, $pushContent);
    }

    public function update($id, $modal)
    {
        $pushUrl = $this->computePushUrl('update');
        $pushContent = $this->computeContentUrl($id, $modal);

        $this->push($pushUrl, $pushContent);
    }

    public function delete($id, $modal)
    {
        $pushUrl = $this->computePushUrl('del');
        $pushContent = $this->computeContentUrl($id, $modal);

        $this->push($pushUrl, $pushContent);
    }

    public function trending($table)
    {
        if (config('app.env') !== 'production')
        {
            return;
        }

        if (!in_array($table, ['post', 'image', 'score', 'question']))
        {
            return;
        }

        $prefix = '';
        if ($table === 'post')
        {
            $prefix = 'post';
        }
        else if ($table === 'image')
        {
            $prefix = 'pins';
        }
        else if ($table === 'score')
        {
            $prefix = 'review';
        }
        else if ($table === 'question')
        {
            $prefix = 'qaq';
        }

        $urls = [
            'https://m.calibur.tv/world/' . $prefix,
            'https://www.calibur.tv/world/' . $prefix
        ];

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->computePushUrl('update'),
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => implode("\n", $urls),
            CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
        ));
        curl_exec($ch);
    }

    public function bangumi($id, $type = '')
    {
        if (config('app.env') !== 'production')
        {
            return;
        }

        $urls = [
            'https://m.calibur.tv/bangumi/' . $id . '/' . $type,
            'https://www.calibur.tv/bangumi/' . $id . '/' . $type
        ];

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->computePushUrl('update'),
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => implode("\n", $urls),
            CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
        ));
        curl_exec($ch);
    }

    protected function computePushUrl($method)
    {
        $type = in_array($method, ['urls', 'update', 'del']) ? $method : 'urls';
        return 'http://data.zz.baidu.com/' . $type . '?site=https://www.calibur.tv&token=' . config('website.push_baidu_token');
    }

    protected function computeContentUrl($id, $model)
    {
        $prefix = '';
        if ($model === 'post')
        {
            $prefix = 'post';
        }
        else if ($model === 'image')
        {
            $prefix = 'pin';
        }
        else if ($model === 'score')
        {
            $prefix = 'review';
        }
        else if ($model === 'video')
        {
            $prefix = 'video';
        }
        else if ($model === 'bangumi')
        {
            $prefix = 'bangumi';
        }
        else if ($model === 'user')
        {
            $prefix = 'user';
        }
        else if ($model === 'role')
        {
            $prefix = 'role';
        }
        else if ($model === 'question')
        {
            $prefix = 'qaq';
        }
        else if ($model === 'answer')
        {
            $prefix = 'soga';
        }

        $url = '/' . $prefix . '/' . $id;

        $result = [
            'https://m.calibur.tv' . $url,
            'https://www.calibur.tv' . $url
        ];

        return implode("\n", $result);
    }

    protected function push($url, $content)
    {
        if (config('app.env') !== 'production')
        {
            return;
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_HTTPHEADER => array('Content-Type: text/plain'),
        ));
        curl_exec($ch);
    }
}