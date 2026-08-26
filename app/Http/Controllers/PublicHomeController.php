<?php

namespace App\Http\Controllers;

use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class PublicHomeController extends Controller
{
    public function __construct(private readonly HomePresentationResolver $home) {}

    public function show(): View|RedirectResponse
    {
        $presentation = $this->home->presentation();
        if (($presentation['template'] ?? null) === HomeTemplate::SkipHome
            && is_string($presentation['targetUrl'] ?? null)
            && $presentation['targetUrl'] !== '') {
            return redirect()->to($presentation['targetUrl']);
        }

        return view('pages.home', $presentation);
    }
}
