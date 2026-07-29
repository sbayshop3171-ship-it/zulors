<?php

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LinkerController extends Controller
{
    public function index()
    {
        return view('auth::linker.index');
    }
}
