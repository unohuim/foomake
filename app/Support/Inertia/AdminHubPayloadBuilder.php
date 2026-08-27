<?php

namespace App\Support\Inertia;

use App\Actions\Workflows\EnsureWorkflowDomainsSeededAction;
use App\Actions\Workflows\SeedDefaultWorkflowStagesForTenantAction;
use App\Models\ExternalProductSourceConnection;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\Role;
use App\Models\TenantUserInvitation;
use App\Models\User;
use App\Models\WordPressPluginConnection;
use App\Models\WorkflowDomain;
use App\Models\WorkflowStage;
use App\Models\WorkflowTaskTemplate;
use App\Support\Billing\StripeBillingService;
use App\Support\Billing\TenantBillingEntitlement;
use App\Support\Workflows\WorkflowAssignmentPermissions;
use App\Support\Workflows\WorkflowStatusOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Build the admin hub payload for authenticated Inertia admin tabs.
 */
class AdminHubPayloadBuilder
{
    public function __construct(
        private readonly TenantBillingEntitlement $billingEntitlement,
        private readonly StripeBillingService $billingService
    ) {
    }

    /**
     * Build the admin hub payload for the current request.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $tabs = $this->tabs($request, $user);
        $activeTab = $this->activeTab($request, $tabs);

        return [
            'activeTab' => $activeTab,
            'tabs' => $tabs,
            'notice' => $this->noticePayload($request),
            'billing' => $this->activePayload($tabs, $activeTab, 'billing', fn (): array => $this->billingPayload($request)),
            'connectors' => $this->activePayload($tabs, $activeTab, 'connectors', fn (): array => $this->connectorsPayload($user)),
            'workflows' => $this->activePayload($tabs, $activeTab, 'workflows', fn (): array => $this->workflowsPayload($request)),
            'users' => $this->activePayload($tabs, $activeTab, 'users', fn (): array => $this->usersPayload($request)),
        ];
    }

    /**
     * Build an optional admin notice from server-approved request state.
     *
     * @return array<string, string>|null
     */
    private function noticePayload(Request $request): ?array
    {
        if ($request->query('notice') !== 'marketing_google_required') {
            return null;
        }

        return [
            'tone' => 'info',
            'message' => 'Connect your Google property to use marketing features.',
        ];
    }

    /**
     * Build admin tabs allowed for the user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tabs(Request $request, User $user): array
    {
        return array_values(array_filter([
            $user->can('billing-subscription-manage') ? $this->tab('billing', 'Billing', $request) : null,
            $user->can('system-users-manage') ? $this->tab('connectors', 'Connectors', $request) : null,
            $user->can('workflow-manage') ? $this->tab('workflows', 'Workflows', $request) : null,
            $user->can('admin-users-view') ? $this->tab('users', 'Users', $request) : null,
        ]));
    }

    /**
     * Build an admin tab link.
     *
     * @return array<string, mixed>
     */
    private function tab(string $key, string $label, Request $request): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'url' => route('admin.index', ['tab' => $key], false),
            'active' => $request->routeIs('admin.index') && $request->query('tab', 'billing') === $key,
            'enabled' => true,
        ];
    }

    /**
     * Resolve the active tab from query input.
     *
     * @param array<int, array<string, mixed>> $tabs
     */
    private function activeTab(Request $request, array $tabs): string
    {
        $requested = (string) $request->query('tab', 'billing');
        $available = collect($tabs)->pluck('key')->all();

        return in_array($requested, $available, true)
            ? $requested
            : (string) ($available[0] ?? 'billing');
    }

    /**
     * Return tab payload only when the tab is available.
     *
     * @param array<int, array<string, mixed>> $tabs
     * @param callable(): array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function activePayload(array $tabs, string $activeTab, string $key, callable $payload): ?array
    {
        if ($activeTab !== $key) {
            return null;
        }

        $available = collect($tabs)->contains(fn (array $tab): bool => $tab['key'] === $key);

        return $available ? $payload() : null;
    }

    /**
     * Build billing tab payload.
     *
     * @return array<string, mixed>
     */
    private function billingPayload(Request $request): array
    {
        $tenant = $request->user()->tenant;
        $checkoutStatus = (string) $request->query('checkout', '');
        $checkoutSessionId = (string) $request->query('session_id', '');
        $checkoutMessage = null;
        $planName = null;

        try {
            $planName = $this->billingService->subscriptionPlanLabel();
        } catch (\Throwable) {
            // Keep admin settings reachable if Stripe metadata is temporarily unavailable.
        }

        if ($checkoutStatus === 'success' && $checkoutSessionId !== '') {
            try {
                $syncedTenant = $this->billingService->syncCheckoutSession($checkoutSessionId);

                if ($syncedTenant) {
                    $tenant = $syncedTenant;
                }
            } catch (\Throwable) {
                // Keep the billing tab reachable if Stripe is briefly unavailable.
            }

            if ($this->billingEntitlement->hasAccess($tenant)) {
                $checkoutMessage = $planName !== null
                    ? __('Subscription confirmed for :plan.', ['plan' => $planName])
                    : __('Subscription confirmed.');
            } else {
                $checkoutMessage = __('Payment received. Waiting for Stripe confirmation.');
            }
        } elseif ($checkoutStatus === 'cancelled') {
            $checkoutMessage = __('Checkout cancelled. No billing changes were made.');
        }

        return [
            'tenantName' => $tenant?->tenant_name ?: 'Tenant account',
            'hasAccess' => $this->billingEntitlement->hasAccess($tenant),
            'trialActive' => $this->billingEntitlement->isTrialActive($tenant),
            'billingExempt' => $this->billingEntitlement->isBillingExempt($tenant),
            'subscriptionActive' => $this->billingEntitlement->hasActiveSubscription($tenant),
            'subscriptionPlanName' => $planName,
            'checkoutMessage' => $checkoutMessage,
            'trialEndsAt' => $tenant?->trial_ends_at?->format('M j, Y'),
            'billingProvider' => $tenant?->billing_provider,
            'checkoutUrl' => route('billing.checkout.store', absolute: false),
            'canManageBilling' => $request->user()->can('billing-subscription-manage'),
            'csrfToken' => csrf_token(),
        ];
    }

    /**
     * Build connector tab payload.
     *
     * @return array<string, mixed>
     */
    public function connectorsPayload(User $user): array
    {
        $wooConnection = ExternalProductSourceConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('source', ExternalProductSourceConnection::SOURCE_WOOCOMMERCE)
            ->first();
        $pluginConnection = WordPressPluginConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->latest('connected_at')
            ->latest('id')
            ->first();
        $searchConsoleConnection = GoogleSearchConsoleConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        return [
            'wooCommerce' => $this->wooCommerceConnectionData($wooConnection),
            'wordPressPlugin' => $this->wordPressPluginConnectionData($pluginConnection),
            'storeUrl' => route('profile.connectors.woocommerce.store', absolute: false),
            'disconnectUrl' => route('profile.connectors.woocommerce.destroy', absolute: false),
            'pluginDownloadUrl' => route('profile.connectors.woocommerce.plugin.download', absolute: false),
            'pluginRevokeUrl' => route('profile.connectors.wordpress-plugin.destroy', absolute: false),
            'googleSearchConsole' => $this->googleSearchConsoleConnectionData($searchConsoleConnection),
            'googleSearchConsoleConnectUrl' => route('profile.connectors.google-search-console.connect', absolute: false),
            'googleSearchConsoleDisconnectUrl' => route('profile.connectors.google-search-console.destroy', absolute: false),
            'googleSearchConsolePerformanceUrl' => route('profile.connectors.google-search-console.performance', absolute: false),
            'googleSearchConsoleRefreshUrl' => route('profile.connectors.google-search-console.refresh', absolute: false),
            'googleSearchConsoleReportUrl' => route('profile.connectors.google-search-console.report', absolute: false),
            'csrfToken' => csrf_token(),
        ];
    }

    /**
     * Build marketing payload.
     *
     * @return array<string, mixed>
     */
    public function marketingPayload(User $user): array
    {
        $connection = GoogleSearchConsoleConnection::query()
            ->where('tenant_id', $user->tenant_id)
            ->first();

        return [
            'connected' => $connection?->isConnected() ?? false,
            'siteUrl' => $connection?->site_url,
            'lastVerifiedAt' => $connection?->last_verified_at?->toAtomString(),
            'lastError' => $connection?->last_error,
            'connectorsUrl' => route('admin.index', ['tab' => 'connectors', 'connector' => 'google'], false),
            'reportUrl' => route('profile.connectors.google-search-console.report', absolute: false),
            'dataUrl' => route('admin.marketing.search-console.data', absolute: false),
            'views' => $this->searchConsoleViews(),
            'timeframes' => $this->searchConsoleTimeframes(),
        ];
    }

    /**
     * Build users tab payload.
     *
     * @return array<string, mixed>
     */
    private function usersPayload(Request $request): array
    {
        $roles = Role::query()
            ->where('name', '!=', 'super-admin')
            ->orderBy('name')
            ->get();

        return [
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values()->all(),
            'storeInvitationUrl' => route('admin.users.invitations.store', absolute: false),
            'listUrl' => route('admin.users.list', absolute: false),
            'csrfToken' => csrf_token(),
            'canManageUsers' => Gate::allows('admin-users-manage'),
            'rows' => $this->userRows($request)
                ->merge($this->invitationRows($request))
                ->values()
                ->all(),
        ];
    }

    /**
     * Build workflows tab payload.
     *
     * @return array<string, mixed>
     */
    private function workflowsPayload(Request $request): array
    {
        app(EnsureWorkflowDomainsSeededAction::class)->execute();
        app(SeedDefaultWorkflowStagesForTenantAction::class)->execute($request->user()->tenant);

        $showInactive = $request->boolean('show_inactive');

        $domains = WorkflowDomain::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $stagesQuery = WorkflowStage::query()
            ->with('workflowDomain')
            ->orderBy('workflow_domain_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        $templatesQuery = WorkflowTaskTemplate::query()
            ->with(['workflowDomain', 'workflowStage', 'defaultAssignee'])
            ->orderBy('workflow_stage_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! $showInactive) {
            $stagesQuery->where('is_active', true);
            $templatesQuery->where('is_active', true);
        }

        $stages = $stagesQuery->get();
        $templates = $templatesQuery->get();
        $users = User::query()->orderBy('id')->get();

        return [
            'domains' => $domains->map(fn (WorkflowDomain $domain): array => $this->workflowDomainData($domain))->values()->all(),
            'stages' => $stages->map(fn (WorkflowStage $stage): array => $this->workflowStageData($stage))->values()->all(),
            'taskTemplates' => $templates->map(fn (WorkflowTaskTemplate $template): array => $this->workflowTaskTemplateData($template))->values()->all(),
            'users' => $users->map(fn (User $user): array => $this->workflowUserData($user, $domains))->values()->all(),
            'showInactive' => $showInactive,
            'stageStoreUrl' => route('admin.workflows.stages.store'),
            'stageUpdateUrlBase' => url('/admin/workflows/stages'),
            'stageDeleteUrlBase' => url('/admin/workflows/stages'),
            'stageReorderUrl' => route('admin.workflows.stages.reorder'),
            'statusOptionsByDomainId' => app(WorkflowStatusOptions::class)->byDomainId($domains),
            'taskTemplateStoreUrl' => route('admin.workflows.task-templates.store'),
            'taskTemplateUpdateUrlBase' => url('/admin/workflows/task-templates'),
            'taskTemplateReorderUrl' => route('admin.workflows.task-templates.reorder'),
            'csrfToken' => csrf_token(),
        ];
    }

    /**
     * Build workflow-domain payload data.
     *
     * @return array<string, int|string>
     */
    private function workflowDomainData(WorkflowDomain $domain): array
    {
        return [
            'id' => $domain->id,
            'key' => $domain->key,
            'name' => $domain->name,
            'sort_order' => $domain->sort_order,
        ];
    }

    /**
     * Build workflow-stage payload data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function workflowStageData(WorkflowStage $stage): array
    {
        return [
            'id' => $stage->id,
            'workflow_domain_id' => $stage->workflow_domain_id,
            'workflow_domain_key' => $stage->workflowDomain?->key,
            'key' => $stage->key,
            'name' => $stage->name,
            'action_verb' => $stage->action_verb,
            'status_complete_label' => $stage->status_complete_label,
            'completion_mode' => $stage->completion_mode,
            'description' => $stage->description,
            'sort_order' => $stage->sort_order,
            'is_active' => $stage->is_active,
            'is_core' => $stage->is_core,
            'is_inventory_effect_stage' => $stage->is_inventory_effect_stage,
            'is_seeded_sales_stage' => in_array(
                $stage->key,
                ['creating', 'packing', 'shipping', 'invoicing', 'completing'],
                true
            )
                && $stage->workflowDomain?->key === 'sales',
        ];
    }

    /**
     * Build workflow-task-template payload data.
     *
     * @return array<string, int|string|bool|null>
     */
    private function workflowTaskTemplateData(WorkflowTaskTemplate $template): array
    {
        return [
            'id' => $template->id,
            'workflow_domain_id' => $template->workflow_domain_id,
            'workflow_domain_key' => $template->workflowDomain?->key,
            'workflow_stage_id' => $template->workflow_stage_id,
            'workflow_stage_key' => $template->workflowStage?->key,
            'title' => $template->title,
            'description' => $template->description,
            'sort_order' => $template->sort_order,
            'is_active' => $template->is_active,
            'default_assignee_user_id' => $template->default_assignee_user_id,
            'default_assignee_name' => $template->defaultAssignee?->name,
        ];
    }

    /**
     * Build workflow user payload data for assignee selection.
     *
     * @return array<string, int|string|array<int, int>>
     */
    private function workflowUserData(User $user, Collection $domains): array
    {
        $assignmentPermissions = app(WorkflowAssignmentPermissions::class);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'eligible_workflow_domain_ids' => $domains
                ->filter(
                    fn (WorkflowDomain $domain): bool => $assignmentPermissions
                        ->userCanBeAssignedToDomain($user, $domain->key)
                )
                ->pluck('id')
                ->values()
                ->all(),
        ];
    }

    /**
     * Build safe WooCommerce connection data.
     *
     * @return array<string, mixed>
     */
    private function wooCommerceConnectionData(?ExternalProductSourceConnection $connection): array
    {
        return [
            'source' => ExternalProductSourceConnection::SOURCE_WOOCOMMERCE,
            'status' => $connection?->status ?? ExternalProductSourceConnection::STATUS_DISCONNECTED,
            'connected' => $connection?->isConnected() ?? false,
            'is_connected' => $connection?->isConnected() ?? false,
            'last_verified_at' => $connection?->last_verified_at?->toAtomString(),
            'last_error' => $connection?->last_error,
        ];
    }

    /**
     * Build safe WordPress plugin connection data.
     *
     * @return array<string, mixed>
     */
    private function wordPressPluginConnectionData(?WordPressPluginConnection $connection): array
    {
        return [
            'status' => $connection?->status ?? WordPressPluginConnection::STATUS_REVOKED,
            'connected' => $connection?->isConnected() ?? false,
            'site_url' => $connection?->site_url,
            'site_name' => $connection?->site_name,
            'last_seen_at' => $connection?->last_seen_at?->toAtomString(),
            'connected_at' => $connection?->connected_at?->toAtomString(),
            'revoked_at' => $connection?->revoked_at?->toAtomString(),
        ];
    }

    /**
     * Build safe Search Console connection data.
     *
     * @return array<string, mixed>
     */
    private function googleSearchConsoleConnectionData(?GoogleSearchConsoleConnection $connection): array
    {
        return [
            'status' => $connection?->status ?? GoogleSearchConsoleConnection::STATUS_DISCONNECTED,
            'connected' => $connection?->isConnected() ?? false,
            'site_url' => $connection?->site_url,
            'last_verified_at' => $connection?->last_verified_at?->toAtomString(),
            'last_error' => $connection?->last_error,
        ];
    }

    /**
     * Return Search Console marketing views.
     *
     * @return array<int, array<string, string>>
     */
    private function searchConsoleViews(): array
    {
        return [
            ['key' => 'query', 'label' => 'Query Performance', 'description' => 'Top search queries by clicks, impressions, CTR, and average position.'],
            ['key' => 'page', 'label' => 'Page Performance', 'description' => 'Landing pages receiving organic search impressions and clicks.'],
            ['key' => 'query_by_page', 'label' => 'Query By Page', 'description' => 'Which queries are driving impressions to each marketing URL.'],
            ['key' => 'comparison', 'label' => 'Period Comparison', 'description' => 'Compare last 7 days against 28-day pace and recent 24-hour movement.'],
            ['key' => 'report', 'label' => 'Markdown Report', 'description' => 'Download the current Search Console analysis and recommendations.'],
        ];
    }

    /**
     * Return Search Console timeframes.
     *
     * @return array<int, array<string, int|string>>
     */
    private function searchConsoleTimeframes(): array
    {
        return [
            ['key' => '24h', 'label' => '24 Hours', 'days' => 1],
            ['key' => '7d', 'label' => '7 Days', 'days' => 7],
            ['key' => '28d', 'label' => '28 Days', 'days' => 28],
            ['key' => '90d', 'label' => '90 Days', 'days' => 90],
        ];
    }

    /**
     * Build active member rows.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function userRows(Request $request): Collection
    {
        return User::query()
            ->with('roles')
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => 'member-' . $user->id,
                'record_id' => $user->id,
                'type' => 'member',
                'name' => $user->name ?: $user->email,
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->filter()->values()->join(', ') ?: '-',
                'status' => 'Active',
                'status_key' => 'active-member',
            ]);
    }

    /**
     * Build pending invitation rows.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function invitationRows(Request $request): Collection
    {
        return TenantUserInvitation::query()
            ->with('role')
            ->where('tenant_id', $request->user()->tenant_id)
            ->whereNull('accepted_at')
            ->whereNull('accepted_user_id')
            ->whereNull('revoked_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TenantUserInvitation $invitation): array => [
                'id' => 'invitation-' . $invitation->id,
                'record_id' => $invitation->id,
                'type' => 'invitation',
                'name' => $invitation->email,
                'email' => $invitation->email,
                'role' => $invitation->role?->name ?: '-',
                'status' => $invitation->isExpired() ? 'Expired' : 'Pending',
                'status_key' => $invitation->isExpired() ? 'expired-invitation' : 'pending-invitation',
                'resend_url' => route('admin.users.invitations.resend', $invitation, false),
                'revoke_url' => route('admin.users.invitations.revoke', $invitation, false),
                'expires_at' => $invitation->expires_at?->toISOString(),
            ]);
    }
}
