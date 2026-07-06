<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class MobileApiTokenIssuer
{
    public function issue(User $user): string
    {
        $plainToken = 'rtp_'.Str::random(48);

        $user->forceFill([
            'mobile_api_token_hash' => hash('sha256', $plainToken),
            'mobile_api_token_created_at' => now(),
            'mobile_api_token_last_used_at' => null,
        ])->save();

        return $plainToken;
    }

    public function revoke(User $user): void
    {
        $user->forceFill([
            'mobile_api_token_hash' => null,
            'mobile_api_token_created_at' => null,
            'mobile_api_token_last_used_at' => null,
        ])->save();
    }
}
