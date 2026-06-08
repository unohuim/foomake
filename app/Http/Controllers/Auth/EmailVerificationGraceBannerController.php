<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dismiss the soft email-verification banner for the current session.
 */
class EmailVerificationGraceBannerController extends Controller
{
    public const DISMISSED_SESSION_KEY = 'email_verification_grace_banner_dismissed';

    /**
     * Hide the banner for the current session only.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->put(self::DISMISSED_SESSION_KEY, true);

        return back();
    }
}
