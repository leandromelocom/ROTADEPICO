<?php

namespace App\Http\Controllers;

use App\Support\MobileApiTokenIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MobileApiTokenController extends Controller
{
    public function store(Request $request, MobileApiTokenIssuer $issuer): RedirectResponse
    {
        $plainToken = $issuer->issue($request->user());

        return redirect()
            ->route('profile.edit')
            ->with('mobile_api_token', $plainToken)
            ->with('mobile_api_status', 'Token mobile gerado. Guarde este valor no app Android.');
    }
}
