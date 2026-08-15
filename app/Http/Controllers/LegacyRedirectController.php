<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegacyRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $site = $request->query('site');
        if ($site === null) {
            return redirect('/', 301);
        }

        $targets = [
            'paintings' => '/paintings', 'prints' => '/prints', 'drawings' => '/drawings',
            'cyanotype' => '/cyanotype', 'bichromate' => '/bichromate', 'litho' => '/litho',
            'photo' => '/photo', 'ignis' => '/ignis', 'other' => '/other',
            'vita' => '/cv', 'contact' => '/contact',
        ];

        abort_unless(is_string($site) && array_key_exists($site, $targets), 404);

        return redirect($targets[$site], 301);
    }
}
