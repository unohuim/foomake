<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Inertia\AdminHubPayloadBuilder;
use App\Support\Inertia\AuthShellPayloadBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Display tenant admin settings tabs.
 */
class AdminHubController extends Controller
{
    /**
     * Show the admin hub.
     */
    public function __invoke(
        Request $request,
        AuthShellPayloadBuilder $authShellPayloadBuilder,
        AdminHubPayloadBuilder $adminHubPayloadBuilder
    ): Response {
        abort_unless($request->user()->can('billing-subscription-manage')
            || $request->user()->can('system-users-manage')
            || $request->user()->can('workflow-manage')
            || $request->user()->can('admin-users-view'), 403);

        return Inertia::render('Admin/Index', [
            'shell' => $authShellPayloadBuilder->build($request),
            'adminHub' => $adminHubPayloadBuilder->build($request),
        ]);
    }
}
