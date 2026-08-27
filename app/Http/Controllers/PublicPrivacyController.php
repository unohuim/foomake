<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Render the public privacy policy page.
 */
final class PublicPrivacyController extends Controller
{
    /**
     * Display the public privacy policy.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Privacy');
    }
}
