<?php

namespace App\Http\Controllers\Admin\Authorship;

use Illuminate\Http\Request;
use App\Models\AuthorshipRequest;
use App\Http\Controllers\Controller;

class AuthorshipController extends Controller
{
    public function index()
    {
        $authorshipRequests = AuthorshipRequest::with(['user'])->latest()->paginate(10);

        return view('admin::authorship.index.index', [
            'authorshipRequests' => $authorshipRequests
        ]);
    }
}
