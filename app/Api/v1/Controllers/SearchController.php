<?php

namespace App\Api\V1\Controllers;

use Illuminate\Http\Request;
use App\Services\OpenSearch\Search;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $key = Purifier::clean($request->get('q'));
        if (!$key)
        {
            return $this->resOK();
        }

        $search = new Search();
        $result = $search->index($key);

        return $this->resOK(empty($result) ? '' : $result[0]['fields']['url']);
    }
}
