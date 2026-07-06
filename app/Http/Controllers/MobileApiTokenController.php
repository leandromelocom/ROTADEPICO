<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileApiTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $plainToken = 'rtp_'.Str::random(48);

        $request->user()->forceFill([
            'mobile_api_token_hash' => hash('sha256', $plainToken),
            'mobile_api_token_created_at' => now(),
            'mobile_api_token_last_used_at' => null,
        ])->save();

        return redirect()
            ->route('profile.edit')
            ->with('mobile_api_token', $plainToken)
            ->with('mobile_api_status', 'Token mobile gerado. Guarde este valor no app Android.');
    }
}
