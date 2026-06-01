<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Users') }}
        </h2>
    </x-slot>

    <script type="application/json" id="admin-users-index-payload">@json($payload)</script>

    <div
        class="flex h-[calc(100vh-8rem)] min-h-0 flex-col py-10"
        data-page="admin-users-index"
        data-payload="admin-users-index-payload"
        data-crud-config='@json($crudConfig)'
        x-data="adminUsersIndex"
    >
        <div class="fixed right-6 top-6 z-50" x-show="toast.visible" x-cloak>
            <div
                class="rounded-md px-4 py-3 text-sm shadow-md"
                :class="toast.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                x-text="toast.message"
            ></div>
        </div>

        <div class="mx-auto flex h-full min-h-0 w-full max-w-7xl flex-1 flex-col sm:px-6 lg:px-8">
            <div class="flex h-full min-h-0 flex-1 flex-col" data-crud-root></div>
        </div>

        <div
            class="fixed inset-0 z-50 overflow-hidden"
            x-show="invitePanelOpen"
            x-cloak
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 overflow-hidden">
                <div
                    class="absolute inset-0 bg-gray-500 bg-opacity-25 transition-opacity"
                    x-show="invitePanelOpen"
                    x-on:click="closeInvitePanel()"
                ></div>

                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div class="pointer-events-auto w-screen max-w-md">
                        <form class="flex h-full flex-col bg-white shadow-xl" x-on:submit.prevent="submitInvite()">
                            <div class="flex-1 overflow-y-auto p-6">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h2 class="text-lg font-medium text-gray-900">Invite member</h2>
                                        <p class="mt-1 text-sm text-gray-600">Choose the role this member receives when they accept.</p>
                                    </div>
                                    <button type="button" class="text-gray-400 hover:text-gray-500" x-on:click="closeInvitePanel()">
                                        <span class="sr-only">Close panel</span>
                                        x
                                    </button>
                                </div>

                                <div class="mt-6" x-show="generalError">
                                    <div class="rounded-md bg-red-50 p-3 text-sm text-red-700" x-text="generalError"></div>
                                </div>

                                <div class="mt-6 space-y-5">
                                    <div>
                                        <label for="invite-email" class="block text-sm font-medium text-gray-700">Email</label>
                                        <input
                                            id="invite-email"
                                            x-ref="inviteEmailInput"
                                            type="email"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            x-model="inviteForm.email"
                                        />
                                        <p class="mt-1 text-sm text-red-600" x-show="errors.email.length" x-text="errors.email[0]"></p>
                                    </div>

                                    <div>
                                        <label for="invite-role" class="block text-sm font-medium text-gray-700">Role</label>
                                        <select
                                            id="invite-role"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            x-model="inviteForm.role_id"
                                        >
                                            <option value="">Choose a role</option>
                                            <template x-for="role in roles" :key="role.id">
                                                <option :value="String(role.id)" x-text="role.name"></option>
                                            </template>
                                        </select>
                                        <p class="mt-1 text-sm text-red-600" x-show="errors.role_id.length" x-text="errors.role_id[0]"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 border-t border-gray-100 bg-white px-6 py-4">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                    x-on:click="closeInvitePanel()"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                    :disabled="submittingInvite"
                                    :class="submittingInvite ? 'opacity-50 cursor-not-allowed' : ''"
                                >
                                    Send invite
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
