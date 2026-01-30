<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NoticeLevel;
class NoticeLevelController extends Controller
{
    public function index()
    {
        return response()->json(
            NoticeLevel::orderBy('id')->get()
        );
    }
}
