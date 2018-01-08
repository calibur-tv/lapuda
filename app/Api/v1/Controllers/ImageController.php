<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\ImageRepository;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function token()
    {
        $repository = new ImageRepository();

        return $this->resOK($repository->uptoken());
    }
}
