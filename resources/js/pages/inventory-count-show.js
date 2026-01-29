import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    const safePayload = payload || {};
    const count = safePayload.count || {};

    Alpine.data('inventoryCountShow', () => ({
        csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '',
        showCountForm: false,
        showLineForm: false,
        showPostConfirm: false,
        errors: { count: {}, line: {}, post: '' },
        toast: { show: false, type: 'success', message: '' },

        count,
        lineCount: safePayload.lineCount ?? 0,
        lineCreateUrl: safePayload.lineCreateUrl ?? '',

        countForm: { counted_at: '', notes: '', method: 'PATCH' },
        lineForm: { id: null, item_id: '', counted_quantity: '', notes: '', action: '', method: 'POST' },

        init() {
            this.updateLineCountDisplay();
            this.toggleDraftActions((this.count.status || '') === 'draft');
        },

        openEditCount() {
            this.errors.count = {};
            this.countForm = {
                counted_at: this.$refs.countMeta?.dataset?.countedAtIso || this.count.countedAtIso || '',
                notes: this.$refs.countMeta?.dataset?.notes || '',
                method: 'PATCH',
            };

            this.showCountForm = true;
            this.showLineForm = false;
            this.showPostConfirm = false;
        },

        closeCountForm() {
            this.showCountForm = false;
        },

        async submitCountForm() {
            this.errors.count = {};

            const response = await fetch(this.count.updateUrl, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify({
                    counted_at: this.countForm.counted_at,
                    notes: this.countForm.notes,
                }),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (response.status === 422) {
                    this.errors.count = data.errors || { general: [data.message || 'Unable to save count.'] };
                    return;
                }

                this.showToast('error', data.message || 'Unable to save count.');
                return;
            }

            this.updateCountDetails(data.count);
            this.showToast('success', 'Inventory count updated.');
            this.closeCountForm();
        },

        openCreateLine() {
            this.errors.line = {};
            this.lineForm = {
                id: null,
                item_id: '',
                counted_quantity: '',
                notes: '',
                action: this.lineCreateUrl,
                method: 'POST',
            };

            this.showLineForm = true;
            this.showCountForm = false;
            this.showPostConfirm = false;
        },

        openEditLine(event) {
            const row = event.target.closest('tr');

            if (!row) {
                return;
            }

            this.errors.line = {};
            this.lineForm = {
                id: row.dataset.lineId,
                item_id: row.dataset.itemId,
                counted_quantity: row.dataset.countedQuantity,
                notes: row.dataset.notes || '',
                action: row.dataset.updateUrl,
                method: 'PATCH',
            };

            this.showLineForm = true;
            this.showCountForm = false;
            this.showPostConfirm = false;
        },

        closeLineForm() {
            this.showLineForm = false;
        },

        async submitLineForm() {
            this.errors.line = {};

            const response = await fetch(this.lineForm.action, {
                method: this.lineForm.method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify({
                    item_id: this.lineForm.item_id,
                    counted_quantity: this.lineForm.counted_quantity,
                    notes: this.lineForm.notes,
                }),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (response.status === 422) {
                    this.errors.line = data.errors || { general: [data.message || 'Unable to save line.'] };
                    return;
                }

                this.showToast('error', data.message || 'Unable to save line.');
                return;
            }

            if (this.lineForm.method === 'POST') {
                this.insertLineRow(data.line);
                this.lineCount = this.lineCount + 1;
            } else {
                this.updateLineRow(data.line);
            }

            this.updateLineCountDisplay();
            this.showToast('success', 'Inventory line saved.');
            this.closeLineForm();
        },

        async deleteLine(event) {
            const row = event.target.closest('tr');

            if (!row) {
                return;
            }

            if (!confirm('Delete this line?')) {
                return;
            }

            const response = await fetch(row.dataset.deleteUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.showToast('error', data.message || 'Unable to delete line.');
                return;
            }

            row.remove();
            this.lineCount = Math.max(this.lineCount - 1, 0);
            this.updateLineCountDisplay();
            this.showToast('success', 'Inventory line deleted.');
        },

        openPostConfirm() {
            this.errors.post = '';
            this.showPostConfirm = true;
            this.showCountForm = false;
            this.showLineForm = false;
        },

        closePostConfirm() {
            this.showPostConfirm = false;
        },

        async confirmPost() {
            this.errors.post = '';

            const response = await fetch(this.count.postUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.errors.post = data.message || 'Unable to post count.';
                return;
            }

            this.updateCountDetails(data.count);
            this.count.status = data.count.status;
            this.showPostConfirm = false;
            this.showToast('success', 'Inventory count posted.');
        },

        updateCountDetails(countUpdate) {
            if (!countUpdate) {
                return;
            }

            this.count.countedAt = countUpdate.counted_at;
            this.count.countedAtIso = countUpdate.counted_at_iso;
            this.count.postedAt = countUpdate.posted_at_display || '';

            if (this.$refs.countedAtDisplay) {
                this.$refs.countedAtDisplay.textContent = countUpdate.counted_at;
            }

            if (this.$refs.statusDisplay) {
                this.$refs.statusDisplay.textContent = this.formatStatus(countUpdate.status);
            }

            if (this.$refs.postedAtDisplay) {
                this.$refs.postedAtDisplay.textContent = countUpdate.posted_at_display || '—';
            }

            if (this.$refs.countMeta) {
                this.$refs.countMeta.dataset.countedAtIso = countUpdate.counted_at_iso;
                this.$refs.countMeta.dataset.notes = countUpdate.notes || '';
                this.$refs.countMeta.dataset.status = countUpdate.status;
            }

            this.toggleDraftActions(countUpdate.status === 'draft');
        },

        toggleDraftActions(isDraft) {
            if (this.$refs.draftActions) {
                this.$refs.draftActions.style.display = isDraft ? 'flex' : 'none';
            }

            if (this.$refs.lineActions) {
                this.$refs.lineActions.style.display = isDraft ? 'block' : 'none';
            }
        },

        insertLineRow(line) {
            if (!this.$refs.lineRowTemplate || !this.$refs.linesTableBody) {
                return;
            }

            const template = this.$refs.lineRowTemplate.content.cloneNode(true);
            const row = template.querySelector('tr');

            this.applyLineData(row, line);
            this.$refs.linesTableBody.prepend(row);

            if (Alpine && typeof Alpine.initTree === 'function') {
                Alpine.initTree(row);
            }
        },

        updateLineRow(line) {
            if (!this.$refs.linesTableBody) {
                return;
            }

            const row = this.$refs.linesTableBody.querySelector('[data-line-id="' + line.id + '"]');

            if (!row) {
                return;
            }

            this.applyLineData(row, line);
        },

        applyLineData(row, line) {
            if (!row || !line) {
                return;
            }

            row.dataset.lineId = line.id;
            row.dataset.itemId = line.item_id;
            row.dataset.countedQuantity = line.counted_quantity;
            row.dataset.notes = line.notes || '';
            row.dataset.updateUrl = line.update_url;
            row.dataset.deleteUrl = line.delete_url;

            const itemDisplayEl = row.querySelector('[data-role="item-display"]');
            if (itemDisplayEl) {
                itemDisplayEl.textContent = line.item_display;
            }
            const countedQuantityEl = row.querySelector('[data-role="counted-quantity"]');
            if (countedQuantityEl) {
                countedQuantityEl.textContent = line.counted_quantity;
            }
            const notesEl = row.querySelector('[data-role="notes"]');
            if (notesEl) {
                notesEl.textContent = line.notes || '—';
            }
        },

        updateLineCountDisplay() {
            if (this.$refs.lineCountDisplay) {
                this.$refs.lineCountDisplay.textContent = String(this.lineCount);
            }

            if (this.$refs.linesEmptyState) {
                this.$refs.linesEmptyState.style.display = this.lineCount === 0 ? 'block' : 'none';
            }
        },

        formatStatus(status) {
            return status === 'posted' ? 'Posted' : 'Draft';
        },

        showToast(type, message) {
            this.toast = { show: true, type, message };

            setTimeout(() => {
                this.toast.show = false;
            }, 2500);
        },
    }));
}
