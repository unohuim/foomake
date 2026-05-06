/**
 * Register a reusable Alpine combobox component.
 *
 * @param {import('alpinejs').Alpine} Alpine
 * @returns {void}
 */
export function registerCombobox(Alpine) {
    Alpine.data('combobox', (config = {}) => ({
        name: config.name || '',
        placeholder: config.placeholder || 'Search',
        noResultsText: config.noResultsText || 'No items found.',
        inputId: config.inputId || '',
        listId: config.listId || '',
        optionsExpression: config.optionsExpression || '',
        disabledExpression: config.disabledExpression || '',
        configuredOptions: Array.isArray(config.options) ? config.options : [],
        selectedValue: config.selectedValue === null || config.selectedValue === undefined
            ? ''
            : String(config.selectedValue),
        query: '',
        open: false,
        highlightedIndex: -1,
        init() {
            this.syncQueryFromSelection();

            this.$watch('selectedValue', () => {
                this.syncQueryFromSelection();
            });
        },
        normalizedOptions(inputOptions) {
            return (Array.isArray(inputOptions) ? inputOptions : []).map((option) => {
                const meta = option?.meta && typeof option.meta === 'object' ? option.meta : {};
                const label = option?.label ?? option?.name ?? '';
                const description = option?.description
                    ?? option?.uom_display
                    ?? meta.uom_display
                    ?? '';
                const searchText = option?.search_text
                    ?? option?.display_text
                    ?? [label, description, JSON.stringify(meta)]
                        .filter((value) => value !== '')
                        .join(' ');

                return {
                    ...option,
                    value: String(option?.value ?? option?.id ?? ''),
                    label: String(label),
                    description: String(description),
                    search_text: String(searchText).toLowerCase(),
                    meta,
                };
            });
        },
        resolveOptionsFromExpression() {
            if (this.optionsExpression === '') {
                return [];
            }

            const evaluatedOptions = Alpine.evaluate(this.$el, this.optionsExpression);

            return Array.isArray(evaluatedOptions) ? evaluatedOptions : [];
        },
        slotOptions() {
            const optionNodes = this.$refs.slotOptions?.querySelectorAll('[data-combo-item]') || [];

            return Array.from(optionNodes).map((node) => {
                try {
                    return JSON.parse(node.getAttribute('data-item') || '{}');
                } catch (error) {
                    return null;
                }
            }).filter((option) => option !== null);
        },
        allOptions() {
            const options = this.optionsExpression === ''
                ? this.configuredOptions
                : this.resolveOptionsFromExpression();

            return this.normalizedOptions([
                ...options,
                ...this.slotOptions(),
            ]);
        },
        filteredOptions() {
            const normalizedQuery = String(this.query || '').trim().toLowerCase();
            const options = this.allOptions();

            if (normalizedQuery === '') {
                return options;
            }

            return options.filter((option) => {
                const haystack = [
                    option.label,
                    option.description,
                    option.search_text,
                    JSON.stringify(option.meta || {}),
                ].join(' ').toLowerCase();

                return haystack.includes(normalizedQuery);
            });
        },
        isDisabled() {
            if (this.disabledExpression === '') {
                return false;
            }

            return Boolean(Alpine.evaluate(this.$el, this.disabledExpression));
        },
        syncQueryFromSelection() {
            const selectedOption = this.allOptions().find((option) => option.value === String(this.selectedValue));

            if (!selectedOption) {
                if (this.selectedValue === '') {
                    this.query = '';
                }

                return;
            }

            this.query = selectedOption.label;
        },
        openDropdown() {
            if (this.isDisabled()) {
                return;
            }

            this.open = true;
            this.ensureHighlight();
        },
        closeDropdown() {
            this.open = false;
            this.highlightedIndex = -1;
            this.syncQueryFromSelection();
        },
        handleQueryInput(value) {
            if (this.isDisabled()) {
                return;
            }

            this.query = value;
            this.open = true;
            this.highlightedIndex = this.filteredOptions().length > 0 ? 0 : -1;

            if (value === '') {
                this.selectedValue = '';
            }
        },
        ensureHighlight() {
            if (this.filteredOptions().length === 0) {
                this.highlightedIndex = -1;
                return;
            }

            if (this.highlightedIndex < 0 || this.highlightedIndex >= this.filteredOptions().length) {
                this.highlightedIndex = 0;
            }
        },
        highlightNext() {
            if (!this.open) {
                this.openDropdown();
                return;
            }

            const options = this.filteredOptions();

            if (options.length === 0) {
                this.highlightedIndex = -1;
                return;
            }

            this.highlightedIndex = this.highlightedIndex >= options.length - 1
                ? 0
                : this.highlightedIndex + 1;
        },
        highlightPrevious() {
            if (!this.open) {
                this.openDropdown();
                return;
            }

            const options = this.filteredOptions();

            if (options.length === 0) {
                this.highlightedIndex = -1;
                return;
            }

            this.highlightedIndex = this.highlightedIndex <= 0
                ? options.length - 1
                : this.highlightedIndex - 1;
        },
        selectHighlighted() {
            const options = this.filteredOptions();

            if (this.highlightedIndex < 0 || !options[this.highlightedIndex]) {
                return;
            }

            this.selectOption(options[this.highlightedIndex]);
        },
        selectOption(option) {
            this.selectedValue = String(option.value);
            this.query = option.label;
            this.open = false;
            this.highlightedIndex = -1;
        },
        isSelected(option) {
            return String(this.selectedValue) === String(option.value);
        },
        optionDomId(index) {
            return `${this.listId}-option-${index}`;
        },
        activeDescendantId() {
            return this.highlightedIndex >= 0 ? this.optionDomId(this.highlightedIndex) : '';
        },
    }));
}
