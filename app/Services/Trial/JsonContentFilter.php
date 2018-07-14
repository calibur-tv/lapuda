<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/14
 * Time: 上午10:18
 */

namespace App\Services\Trial;


class JsonContentFilter
{
    public function exec($content)
    {
        $wordsFilter = new WordsFilter();
        $imageFilter = new ImageFilter();
        $result = [
            'review' => true,
            'delete' => false
        ];
        $badWordsCount = 0;
        $badImageCount = 0;
        foreach ($content as $row)
        {
            if ($row['type'] == 'txt')
            {
                $badWordsCount += $wordsFilter->count($row['text']);
            }
            else if ($row['type'] == 'img')
            {
                $imageResult = $imageFilter->check($row['url']);
                if ($imageResult['delete'])
                {
                    $result['delete'] = true;
                }
                if ($imageResult['review'])
                {
                    $badImageCount++;
                }
            }
        }
        if ($badWordsCount > 3 || $badWordsCount + $badImageCount > 3)
        {
            $result['review'] = true;
        }

        return $result;
    }
}