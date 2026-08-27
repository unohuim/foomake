<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Render the public home page through the first Inertia guest shell.
 */
final class PublicHomeController extends Controller
{
    /**
     * Display the public home page.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'logo' => [
                'src' => null,
                'alt' => config('app.name', 'Factory Manager'),
            ],
            'hero' => [
                'eyebrow' => 'For bakeries, farm kitchens, roasters, and fermenters',
                'headline' => 'Keep the day\'s batches moving without another clipboard.',
                'body' => 'Track ingredients, supplier packs, inventory counts, prep work, make orders, and market orders in one place built for small-batch food production.',
                'primaryCtaLabel' => 'Enter the kitchen board',
                'authenticatedCtaLabel' => 'Open today\'s board',
                'authenticatedCtaUrl' => '/dashboard',
            ],
            'workExamples' => [
                'Receive oats from a supplier.',
                'Count flour before a run.',
                'Prep tomorrow\'s granola batch.',
                'Pack the Saturday market order.',
            ],
            'learnLinks' => [
                ['label' => 'Food manufacturing MRP', 'url' => route('marketing.pages.show', ['slug' => 'food-manufacturing-mrp'], false)],
                ['label' => 'Inventory management', 'url' => route('marketing.pages.show', ['slug' => 'inventory-management-for-food-manufacturers'], false)],
                ['label' => 'Recipe management', 'url' => route('marketing.pages.show', ['slug' => 'recipe-management-software'], false)],
                ['label' => 'Purchase orders', 'url' => route('marketing.pages.show', ['slug' => 'purchase-order-software-for-food-manufacturers'], false)],
                ['label' => 'Production planning', 'url' => route('marketing.pages.show', ['slug' => 'production-planning-for-small-food-manufacturers'], false)],
                ['label' => 'MRP for small manufacturers', 'url' => route('marketing.pages.show', ['slug' => 'mrp-for-small-manufacturers'], false)],
                ['label' => 'Privacy', 'url' => route('privacy', absolute: false)],
            ],
            'authRoutes' => [
                'loginUrl' => '/login',
                'registerUrl' => '/register',
                'passwordEmailUrl' => route('password.email', absolute: false),
                'dashboardUrl' => '/dashboard',
                'canRegister' => true,
            ],
        ]);
    }
}
