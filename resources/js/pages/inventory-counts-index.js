import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const emptyErrors = () => ({
        counted_at: [],
        notes: [],
        general: [],
    });

    const resolveCsrfToken = () => {
        if (safePayload.csrfToken) {
            return safePayload.csrfToken;
        }

        const meta = document.querySelector('meta[name=csrf-token]');
        return meta ? meta.getAttribute('content') : '';
    };

    Alpine.data('inventoryCountsIndex', () => ({
        csrf: resolveCsrfToken(),
        storeUrl: safePayload.storeUrl || '',
        showCountForm: false,
        isEditing: false,
        errors: emptyErrors(),
        toast: { show: false, type: 'success', message: '' },
        form: { id: null, counted_at: '', notes: '', action: '', method: 'POST' },
        init() {
            if (window.location.hash === '#create-count') {
                this.openCreate();
                this.clearCreateHash();
            }

            window.addEventListener('hashchange', () => {
                if (window.location.hash === '#create-count') {
                    this.openCreate();
                    this.clearCreateHash();
                }
            });
        },
        normalizeErrors(errors) {
            if (!errors || typeof errors !== 'object') {
                return emptyErrors();
            }

            return {
                ...emptyErrors(),
                ...errors,
                counted_at: Array.isArray(errors.counted_at) ? errors.counted_at : [],
                notes: Array.isArray(errors.notes) ? errors.notes : [],
                general: Array.isArray(errors.general) ? errors.general : [],
            };
        },
        setCreateHash() {
            window.location.hash = '#create-count';
        },
        openCreate() {
            if (!this.storeUrl) {
                return;
            }

            this.isEditing = false;
            this.errors = emptyErrors();
            this.form = {
                id: null,
                counted_at: '',
                notes: '',
                action: this.storeUrl,
                method: 'POST',
            };
            this.showCountForm = true;
        },
        clearCreateHash() {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        },
        openEdit(event) {
            const row = event.target.closest('tr');
            if (!row) {
                return;
            }

            this.isEditing = true;
            this.errors = emptyErrors();
            this.form = {
                id: row.dataset.countId,
                counted_at: row.dataset.countedAtIso,
                notes: row.dataset.notes || '',
                action: row.dataset.updateUrl,
                method: 'PATCH',
            };
            this.showCountForm = true;
        },
        closeCountForm() {
            this.showCountForm = false;
        },
        async submitCountForm() {
            this.errors = emptyErrors();

            const response = await fetch(this.form.action, {
                method: this.form.method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify({
                    counted_at: this.form.counted_at,
                    notes: this.form.notes,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 422) {
                    this.errors = this.normalizeErrors(data.errors);

                    if (!data.errors) {
                        this.errors.general = [data.message || 'Unable to save count.'];
                    }

                    return;
                }

                this.showToast('error', data.message || 'Unable to save count.');
                return;
            }

            if (this.isEditing) {
                this.updateRow(data.count);
            } else {
                this.insertRow(data.count);
            }

            this.showToast('success', 'Inventory count saved.');
            this.closeCountForm();
        },
        async deleteCount(event) {
            const row = event.target.closest('tr');
            if (!row) {
                return;
            }

            if (!confirm('Delete this inventory count?')) {
                return;
            }

            const response = await fetch(row.dataset.deleteUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            });

            const data = await response.json();

            if (!response.ok) {
                this.showToast('error', data.message || 'Unable to delete count.');
                return;
            }

            row.remove();
            this.showToast('success', 'Inventory count deleted.');
        },
        ensureCountsTable() {
            if (this.$refs.countsTableBody) {
                return;
            }

            if (!this.$refs.countsTableContainer || !this.$refs.emptyStateContainer) {
                return;
            }

            this.$refs.emptyStateContainer.classList.add('hidden');
            this.$refs.countsTableContainer.classList.remove('hidden');
        },
        insertRow(count) {
            this.ensureCountsTable();

            if (!this.$refs.countsTableBody) {
                this.showToast('error', 'Unable to render new row.');
                return;
            }

            const template = this.$refs.countRowTemplate.content.cloneNode(true);
            const row = template.querySelector('tr');

            this.applyRowData(row, count);

            this.$refs.countsTableBody.prepend(row);
            window.Alpine.initTree(row);
        },
        updateRow(count) {
            if (!this.$refs.countsTableBody) {
                return;
            }

            const selector = '[data-count-id=\'' + count.id + '\']';
            const row = this.$refs.countsTableBody.querySelector(selector);

            if (!row) {
                return;
            }

            this.applyRowData(row, count);
        },
        applyRowData(row, count) {
            row.dataset.countId = count.id;
            row.dataset.countedAt = count.counted_at;
            row.dataset.countedAtIso = count.counted_at_iso;
            row.dataset.notes = count.notes || '';
            row.dataset.status = count.status;
            row.dataset.postedAt = count.posted_at_display || '';
            row.dataset.postedAtIso = count.posted_at_iso || '';
            row.dataset.linesCount = count.lines_count;
            row.dataset.showUrl = count.show_url;
            row.dataset.updateUrl = count.update_url;
            row.dataset.deleteUrl = count.delete_url;

            row.querySelector('[data-role=\'counted-at\']').textContent = count.counted_at;
            row.querySelector('[data-role=\'status\']').textContent = this.formatStatus(count.status);
            row.querySelector('[data-role=\'lines-count\']').textContent = count.lines_count;
            row.querySelector('[data-role=\'posted-at\']').textContent = count.posted_at_display || '—';
            row.querySelector('[data-role=\'show-link\']').setAttribute('href', count.show_url);
        },
        formatStatus(status) {
            return status === 'posted' ? 'Posted' : 'Draft';
        },
        showToast(type, message) {
            this.toast = { show: true, type: type, message: message };

            setTimeout(() => {
                this.toast.show = false;
            }, 2500);
        },
    }));
}
