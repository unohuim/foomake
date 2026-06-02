const asRecord = (value) => (value && typeof value === 'object' && !Array.isArray(value) ? value : {});

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

export function registerNotesFeed(Alpine) {
    Alpine.data('notesFeed', (config = {}) => ({
        body: '',
        saving: false,
        error: '',
        storeUrl: config.storeUrl || '',
        csrfToken: config.csrfToken || '',
        emptyState: config.emptyState || 'No notes yet.',
        sortDirection: 'asc',

        async submit() {
            if (!this.storeUrl || this.saving) {
                return;
            }

            this.error = '';
            this.saving = true;

            try {
                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        body: this.body,
                    }),
                });

                const data = await response.json().catch(() => ({}));
                const responseData = asRecord(data);

                if (!response.ok) {
                    const errors = asRecord(responseData.errors);
                    const bodyErrors = Array.isArray(errors.body) ? errors.body : [];

                    this.error = bodyErrors[0] || responseData.message || 'Unable to add note.';
                    return;
                }

                this.appendNote(asRecord(responseData.note));
                this.body = '';
            } catch (error) {
                this.error = 'Unable to add note.';
            } finally {
                this.saving = false;
            }
        },

        appendNote(note) {
            if (!this.$refs.list || !note.id) {
                return;
            }

            if (this.$refs.emptyState) {
                this.$refs.emptyState.classList.add('hidden');
            }

            this.$refs.list.insertAdjacentHTML('beforeend', this.noteHtml(note));
            this.sortByTime(this.sortDirection);
        },

        sortByTimeToggle() {
            this.sortByTime(this.sortDirection === 'asc' ? 'desc' : 'asc');
        },

        sortByTime(direction) {
            if (!this.$refs.list) {
                return;
            }

            const rows = Array.from(this.$refs.list.querySelectorAll('[data-note-row]'));
            this.sortDirection = direction === 'desc' ? 'desc' : 'asc';

            const multiplier = this.sortDirection === 'desc' ? -1 : 1;

            rows.sort((left, right) => {
                const leftTime = Date.parse(left.dataset.noteCreatedAt || '') || 0;
                const rightTime = Date.parse(right.dataset.noteCreatedAt || '') || 0;

                return (leftTime - rightTime) * multiplier;
            });

            rows.forEach((row) => {
                this.$refs.list.appendChild(row);
            });
        },

        noteHtml(note) {
            const authorName = escapeHtml(note.author_name || 'Unknown user');
            const createdAt = escapeHtml(note.created_at || '');
            const createdAtDisplay = escapeHtml(note.created_at_display || '');
            const body = escapeHtml(note.body || '').replaceAll('\n', '<br>');

            return `
                <li class="relative" data-note-row data-note-created-at="${createdAt}">
                    <span class="absolute -left-[1.65rem] top-4 flex h-3 w-3 rounded-full bg-blue-600 ring-4 ring-white"></span>
                    <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-note-card>
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                            <p class="text-sm font-semibold text-gray-900">${authorName}</p>
                            <time class="text-xs font-medium text-gray-500" datetime="${createdAt}">${createdAtDisplay}</time>
                        </div>
                        <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700">${body}</p>
                    </article>
                </li>
            `;
        },
    }));
}
