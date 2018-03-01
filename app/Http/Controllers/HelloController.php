<?php

namespace App\Http\Controllers;

use App\Menu;
use App\Models\Bangumi;
use App\Models\CartoonRole;
use App\Models\Post;
use App\Models\PostImages;
use App\Models\Tag;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class HelloController extends Controller
{
    public function index()
    {
        return view('welcome');
    }
}
