<?php

namespace App\Http\Controllers;

use App\Models\AlbionCraft;
use Inertia\Inertia;



class IndexController extends Controller
{
    public function index()
    {
        return Inertia::render('welcome');
    }
}