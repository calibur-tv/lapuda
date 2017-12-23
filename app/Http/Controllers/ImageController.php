<?php

namespace App\Http\Controllers;

use App\Repositories\ImageRepository;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function token()
    {
        $repository = new ImageRepository();

        return $this->resOK($repository->uptoken());
    }
}
