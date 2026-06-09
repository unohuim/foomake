<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TenantUserInvitation;
use App\Models\User;
use App\Notifications\TenantUserInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Manage tenant-scoped users and invitations.
 */
class UserManagementController extends Controller
{
    /**
     * Display the tenant user-management page.
     */
    public function index(Request $request): View
    {
        Gate::authorize('admin-users-view');

        $roles = $this->assignableRoles();
        $crudConfig = $this->usersCrudConfig();
        $payload = [
            'roles' => $roles->map(fn (Role $role): array => $this->roleData($role))->values()->all(),
            'storeInvitationUrl' => $crudConfig['endpoints']['create'],
            'csrfToken' => csrf_token(),
            'canManageUsers' => Gate::allows('admin-users-manage'),
        ];

        return view('admin.users.index', [
            'crudConfig' => $crudConfig,
            'payload' => $payload,
        ]);
    }

    /**
     * Return the tenant-scoped users and invitations list read model.
     */
    public function list(Request $request): JsonResponse
    {
        Gate::authorize('admin-users-view');

        $crudConfig = $this->usersCrudConfig();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in($crudConfig['sortable'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sortColumn = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $rows = $this->userRows($request)
            ->merge($this->invitationRows($request))
            ->filter(function (array $row) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $haystack = strtolower(implode(' ', [
                    $row['name'],
                    $row['email'],
                    $row['role'],
                    $row['status'],
                ]));

                return str_contains($haystack, strtolower($search));
            })
            ->sortBy(
                fn (array $row): string => strtolower((string) ($row[$sortColumn] ?? '')),
                SORT_REGULAR,
                $direction === 'desc'
            )
            ->values();

        return response()->json([
            'data' => $rows->all(),
            'meta' => [
                'search' => $search,
                'sort' => [
                    'column' => $sortColumn,
                    'direction' => $direction,
                ],
                'allowed_sort_columns' => $crudConfig['sortable'],
                'total' => $rows->count(),
            ],
        ]);
    }

    /**
     * Return one tenant-scoped invitation.
     */
    public function showInvitation(TenantUserInvitation $invitation): JsonResponse
    {
        Gate::authorize('admin-users-view');

        return response()->json([
            'data' => $this->invitationData($invitation->load('role')),
        ]);
    }

    /**
     * Create a tenant-scoped invitation.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeInvitation(Request $request): JsonResponse
    {
        Gate::authorize('admin-users-manage');

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
            ],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('name', '!=', 'super-admin')),
            ],
        ]);

        $email = strtolower((string) $validated['email']);
        $this->assertNoPendingInvitation($request, $email);

        $plainToken = Str::random(64);
        $invitation = TenantUserInvitation::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'invited_by_user_id' => $request->user()->id,
            'email' => $email,
            'role_id' => (int) $validated['role_id'],
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $email)
            ->notify(new TenantUserInvitationNotification($invitation, $plainToken));

        return response()->json([
            'data' => $this->invitationData($invitation->load('role')),
        ], 201);
    }

    /**
     * Resend an open tenant-scoped invitation with a fresh token.
     */
    public function resendInvitation(TenantUserInvitation $invitation): JsonResponse
    {
        Gate::authorize('admin-users-manage');

        if ($invitation->isAccepted() || $invitation->isRevoked()) {
            abort(403);
        }

        $plainToken = Str::random(64);
        $invitation->forceFill([
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
        ])->save();

        Notification::route('mail', $invitation->email)
            ->notify(new TenantUserInvitationNotification($invitation, $plainToken));

        return response()->json([
            'data' => $this->invitationData($invitation->load('role')),
        ]);
    }

    /**
     * Revoke an open tenant-scoped invitation.
     */
    public function revokeInvitation(TenantUserInvitation $invitation): JsonResponse
    {
        Gate::authorize('admin-users-manage');

        if ($invitation->isAccepted() || $invitation->isRevoked()) {
            abort(403);
        }

        $invitation->forceFill([
            'revoked_at' => now(),
        ])->save();

        return response()->json([
            'data' => $this->invitationData($invitation->load('role')),
        ]);
    }

    /**
     * Ensure the tenant does not already have an open invitation for this email.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertNoPendingInvitation(Request $request, string $email): void
    {
        $exists = TenantUserInvitation::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->exists();

        if (! $exists) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'This email already has a pending invitation.',
        ]);
    }

    /**
     * Get assignable tenant-member roles.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(): Collection
    {
        return Role::query()
            ->where('name', '!=', 'super-admin')
            ->orderBy('name')
            ->get();
    }

    /**
     * Build the configured CRUD page contract.
     *
     * @return array<string, mixed>
     */
    private function usersCrudConfig(): array
    {
        $canManage = Gate::allows('admin-users-manage');

        return [
            'resource' => 'admin-users',
            'endpoints' => [
                'list' => route('admin.users.list'),
                'create' => route('admin.users.invitations.store'),
            ],
            'columns' => ['name', 'email', 'role', 'status'],
            'headers' => [
                'name' => 'Name or email',
                'email' => 'Email',
                'role' => 'Role',
                'status' => 'Status',
            ],
            'sortable' => ['name', 'email', 'role', 'status'],
            'labels' => [
                'searchPlaceholder' => 'Search users',
                'createTitle' => 'Invite Member',
                'createAriaLabel' => 'Invite Member',
                'emptyState' => 'No users or invitations found.',
                'actionsAriaLabel' => 'User actions',
            ],
            'permissions' => [
                'showExport' => false,
                'showImport' => false,
                'showCreate' => $canManage,
            ],
            'rowDisplay' => [
                'columns' => [
                    'name' => [
                        'kind' => 'stacked-text',
                        'subtitleExpression' => 'record.type === "member" ? record.email : ""',
                    ],
                    'email' => ['kind' => 'text'],
                    'role' => ['kind' => 'text'],
                    'status' => ['kind' => 'text'],
                ],
            ],
            'mobileCard' => [
                'titleExpression' => "record.name || record.email || '-'",
                'subtitleExpression' => "record.email || '-'",
                'bodyExpression' => "record.status || '-'",
            ],
            'desktopList' => [
                'enabled' => true,
                'titleExpression' => "record.name || record.email || '-'",
                'subtitleExpression' => "record.email || '-'",
                'metaExpression' => "record.role || '-'",
                'badgesExpression' => 'record.status ? [record.status] : []',
                'asideExpression' => "record.type === 'invitation' ? 'Invited' : 'Member'",
                'urlExpression' => "record.email ? 'mailto:' + record.email : '#'",
                'subtitleUrlExpression' => "record.email ? 'mailto:' + record.email : '#'",
            ],
            'rowActions' => [
                'mode' => 'menu',
                'icon' => 'ellipsis-vertical',
                'ariaLabel' => 'User actions',
            ],
            'actions' => $canManage ? [
                [
                    'id' => 'change-role',
                    'label' => 'Change role',
                    'tone' => 'default',
                ],
                [
                    'id' => 'remove-member',
                    'label' => 'Remove member',
                    'tone' => 'warning',
                ],
                [
                    'id' => 'resend-invite',
                    'label' => 'Resend invite',
                    'tone' => 'default',
                ],
                [
                    'id' => 'revoke-invite',
                    'label' => 'Revoke invite',
                    'tone' => 'warning',
                ],
            ] : [],
        ];
    }

    /**
     * Build active member rows for the configured CRUD list.
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
            ->map(fn (User $user): array => $this->userData($user));
    }

    /**
     * Build invitation rows for the configured CRUD list.
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
            ->map(fn (TenantUserInvitation $invitation): array => $this->invitationData($invitation));
    }

    /**
     * Build user row payload data.
     *
     * @return array<string, mixed>
     */
    private function userData(User $user): array
    {
        return [
            'id' => 'member-' . $user->id,
            'record_id' => $user->id,
            'type' => 'member',
            'name' => $user->name ?: $user->email,
            'email' => $user->email,
            'role' => $user->roles->pluck('name')->filter()->values()->join(', ') ?: '-',
            'status' => 'Active',
            'status_key' => 'active-member',
            'available_actions' => ['change-role', 'remove-member'],
        ];
    }

    /**
     * Build role payload data.
     *
     * @return array<string, int|string>
     */
    private function roleData(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
        ];
    }

    /**
     * Build invitation payload data.
     *
     * @return array<string, mixed>
     */
    private function invitationData(TenantUserInvitation $invitation): array
    {
        $isExpired = $invitation->isExpired();

        return [
            'id' => 'invitation-' . $invitation->id,
            'record_id' => $invitation->id,
            'type' => 'invitation',
            'name' => $invitation->email,
            'email' => $invitation->email,
            'role' => $invitation->role?->name ?: '-',
            'status' => $isExpired ? 'Expired' : 'Pending',
            'status_key' => $isExpired ? 'expired-invitation' : 'pending-invitation',
            'available_actions' => $isExpired
                ? ['resend-invite', 'revoke-invite']
                : ['change-role', 'resend-invite', 'revoke-invite'],
            'resend_url' => route('admin.users.invitations.resend', $invitation),
            'revoke_url' => route('admin.users.invitations.revoke', $invitation),
            'expires_at' => $invitation->expires_at?->toISOString(),
            'revoked_at' => $invitation->revoked_at?->toISOString(),
            'accepted_at' => $invitation->accepted_at?->toISOString(),
            'accepted_user_id' => $invitation->accepted_user_id,
        ];
    }
}
