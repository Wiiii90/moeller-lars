<?php

namespace App\Http\Controllers;

use App\Domain\Content\HomePresentationResolver;
use Illuminate\Contracts\View\View;

final class PublicHomeController extends Controller
{
    public function __construct(private readonly HomePresentationResolver $home) {}

    public function show(): View
    {
        return view('pages.home', $this->home->presentation());
    }
}
