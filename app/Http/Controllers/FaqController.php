<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(): View
    {
        return view('faq.index');
    }
}
