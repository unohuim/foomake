/**
 * Register a reusable Alpine dropdown select component.
 *
 * @param {import('alpinejs').Alpine} Alpine
 * @returns {void}
 */
export function registerDropdownSelect(Alpine) {
    Alpine.data('dropdownSelect', (config = {}) => ({
        name: config.name || '',
        placeholder: config.placeholder || 'Select an option',
        buttonId: config.buttonId || '',
        listId: config.listId || '',
        optionsExpression: config.optionsExpression || '',
        disabledExpression: config.disabledExpression || '',
        configuredOptions: Array.isArray(config.options) ? config.options : [],
        slotConfiguredOptions: [],
        selectedValue: config.selectedValue === null || config.selectedValue === undefined
            ? ''
            : String(config.selectedValue),
        selectedLabel: '',
        open: false,
        highlightedIndex: -1,
        init() {
            this.refreshSlotOptions();
            this.syncSelectedLabel();

            this.$nextTick(() => {
                this.refreshSlotOptions();
                this.syncSelectedLabel();
            });

            this.$watch('selectedValue', () => {
                this.syncSelectedLabel();
            });
        },
        refreshSlotOptions() {
            this.slotConfiguredOptions = this.slotOptions();
        },
        normalizedOptions(inputOptions) {
            return (Array.isArray(inputOptions) ? inputOptions : []).map((option) => {
                const meta = option?.meta && typeof option.meta === 'object' ? option.meta : {};
                const label = option?.label ?? option?.name ?? '';

                return {
                    ...option,
                    value: String(option?.value ?? option?.id ?? ''),
                    label: String(label),
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
            const optionNodes = this.$refs.slotOptions?.querySelectorAll('[data-dropdown-option]') || [];

            return Array.from(optionNodes).map((node) => {
                try {
                    return JSON.parse(node.getAttribute('data-option') || '{}');
                } catch (error) {
                    return null;
                }
            }).filter((option) => option !== null);
        },
        allOptions() {
            this.refreshSlotOptions();

            const options = this.optionsExpression === ''
                ? this.configuredOptions
                : this.resolveOptionsFromExpression();

            const mergedOptions = this.normalizedOptions([
                ...options,
                ...this.slotConfiguredOptions,
            ]);

            return mergedOptions.filter((option, index, collection) => {
                return collection.findIndex((candidate) => candidate.value === option.value) === index;
            });
        },
        isDisabled() {
            if (this.disabledExpression === '') {
                return false;
            }

            return Boolean(Alpine.evaluate(this.$el, this.disabledExpression));
        },
        selectedOption() {
            return this.allOptions().find((option) => option.value === String(this.selectedValue)) || null;
        },
        syncSelectedLabel() {
            const selectedOption = this.selectedOption();

            this.selectedLabel = selectedOption ? selectedOption.label : '';
        },
        toggleDropdown() {
            if (this.isDisabled()) {
                return;
            }

            if (this.open) {
                this.closeDropdown();
                return;
            }

            this.openDropdown();
        },
        openDropdown() {
            if (this.isDisabled()) {
                return;
            }

            this.refreshSlotOptions();
            this.open = true;
            this.ensureHighlight();
        },
        closeDropdown() {
            this.open = false;
            this.highlightedIndex = -1;
        },
        ensureHighlight() {
            const options = this.allOptions();

            if (options.length === 0) {
                this.highlightedIndex = -1;
                return;
            }

            const selectedIndex = options.findIndex((option) => option.value === String(this.selectedValue));

            if (selectedIndex !== -1) {
                this.highlightedIndex = selectedIndex;
                return;
            }

            if (this.highlightedIndex < 0 || this.highlightedIndex >= options.length) {
                this.highlightedIndex = 0;
            }
        },
        highlightNext() {
            if (!this.open) {
                this.openDropdown();
                return;
            }

            const options = this.allOptions();

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

            const options = this.allOptions();

            if (options.length === 0) {
                this.highlightedIndex = -1;
                return;
            }

            this.highlightedIndex = this.highlightedIndex <= 0
                ? options.length - 1
                : this.highlightedIndex - 1;
        },
        selectHighlighted() {
            const options = this.allOptions();

            if (this.highlightedIndex < 0 || !options[this.highlightedIndex]) {
                return;
            }

            this.selectOption(options[this.highlightedIndex]);
        },
        selectOption(option) {
            this.selectedValue = String(option.value);
            this.selectedLabel = option.label;
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
