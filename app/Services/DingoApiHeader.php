<?php

namespace App\Services;


use Dingo\Api\Http\Response\Format\Json;

class DingoApiHeader extends Json
{
    public function getContentType()
    {
        return 'application/json; charset=utf-8';
    }
}