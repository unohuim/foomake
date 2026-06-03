/**
 * Register a reusable Alpine smart number input component.
 *
 * @param {import('alpinejs').Alpine} Alpine
 * @returns {void}
 */
export function registerSmartNumberInput(Alpine) {
    Alpine.data('smartNumberInput', (config = {}) => ({
        name: config.name || '',
        type: config.type || 'decimal',
        currency: config.currency || '',
        precision: parseInt(config.precision || 0, 10),
        event: config.event || '',
        defaultChangedEvent: 'smart-number-input:changed',
        debounceMs: parseInt(config.debounceMs ?? 300, 10),
        emitOnInput: config.emitOnInput !== false,
        emitOnChange: config.emitOnChange !== false,
        emitOnBlur: config.emitOnBlur !== false,
        rawMode: config.rawMode || 'value',
        rawValue: config.value === null || config.value === undefined ? '' : String(config.value),
        displayValue: '',
        debounceTimer: null,
        init() {
            this.displayValue = this.formatDisplayValue(this.rawValue);

            this.$watch('rawValue', (value) => {
                const nextRawValue = value === null || value === undefined ? '' : String(value);

                if (nextRawValue !== this.rawValue) {
                    this.rawValue = nextRawValue;
                }

                const nextDisplayValue = this.formatDisplayValue(this.rawValue);

                if (nextDisplayValue !== this.displayValue) {
                    this.displayValue = nextDisplayValue;
                }
            });
        },
        handleInput(event) {
            const input = event.target;
            this.displayValue = input.value;
            const previousDisplayValue = this.displayValue;
            const previousCaret = input.selectionStart || 0;

            this.rawValue = this.toRawValue(this.displayValue);
            this.displayValue = this.formatDisplayValue(this.rawValue);
            this.restoreCaretPosition(input, previousDisplayValue, previousCaret);

            if (this.emitOnInput) {
                this.dispatchDebouncedEvent('input');
            }
        },
        handleChange(event) {
            this.displayValue = event.target.value;
            this.rawValue = this.toRawValue(this.displayValue);
            this.displayValue = this.formatDisplayValue(this.rawValue);
            this.clearDebouncedEvent();

            if (this.emitOnChange) {
                this.dispatchConfiguredEvent('change');
            }
        },
        handleBlur(event) {
            this.displayValue = event.target.value;
            this.rawValue = this.toRawValue(this.displayValue);
            this.displayValue = this.formatDisplayValue(this.rawValue, true);
            this.clearDebouncedEvent();

            if (this.emitOnBlur) {
                this.dispatchConfiguredEvent('blur');
            }
        },
        dispatchDebouncedEvent(sourceEventType) {
            this.clearDebouncedEvent();

            if (this.debounceMs <= 0) {
                this.dispatchConfiguredEvent(sourceEventType);
                return;
            }

            this.debounceTimer = window.setTimeout(() => {
                this.dispatchConfiguredEvent(sourceEventType);
                this.debounceTimer = null;
            }, this.debounceMs);
        },
        clearDebouncedEvent() {
            if (this.debounceTimer === null) {
                return;
            }

            window.clearTimeout(this.debounceTimer);
            this.debounceTimer = null;
        },
        dispatchConfiguredEvent(sourceEventType) {
            const payload = {
                name: this.name,
                rawValue: this.rawValue,
                displayValue: this.displayValue,
                type: this.type,
                currency: this.currency,
                precision: this.precision,
                source: sourceEventType,
            };

            this.$dispatch(this.defaultChangedEvent, payload);

            if (this.event !== '') {
                this.$dispatch(this.event, payload);
            }
        },
        inputSize() {
            const length = String(this.displayValue || '').length;

            return Math.min(Math.max(length + 1, 4), 18);
        },
        restoreCaretPosition(input, previousDisplayValue, previousCaret) {
            const beforeCaret = String(previousDisplayValue || '').slice(0, previousCaret);
            const rawBeforeCaret = this.stripGrouping(beforeCaret);
            let nextCaret = 0;
            let rawCount = 0;

            for (const character of String(this.displayValue || '')) {
                if (character !== ',') {
                    rawCount += 1;
                }

                nextCaret += 1;

                if (rawCount >= rawBeforeCaret.length) {
                    break;
                }
            }

            requestAnimationFrame(() => {
                input.setSelectionRange(nextCaret, nextCaret);
            });
        },
        toRawValue(displayValue) {
            const strippedValue = this.stripGrouping(String(displayValue || ''))
                .replace(this.currency, '')
                .replace('%', '')
                .replace('$', '')
                .trim();
            const normalizedValue = this.normalizeNumericText(strippedValue);

            if (normalizedValue === '') {
                return '';
            }

            if (this.type === 'integer') {
                return this.integerPart(normalizedValue);
            }

            if (this.type === 'money' && this.rawMode === 'cents') {
                return this.decimalAmountToCents(normalizedValue);
            }

            return normalizedValue;
        },
        formatDisplayValue(rawValue, shouldPadFraction = false) {
            const normalizedRawValue = this.normalizeRawValue(rawValue);

            if (normalizedRawValue === '') {
                return '';
            }

            if (this.type === 'integer') {
                return this.formatIntegerPart(this.integerPart(normalizedRawValue));
            }

            const displayValue = this.type === 'money' && this.rawMode === 'cents'
                ? this.centsToDecimalAmount(normalizedRawValue)
                : normalizedRawValue;
            const sign = displayValue.startsWith('-') ? '-' : '';
            const unsignedValue = sign === '' ? displayValue : displayValue.slice(1);
            const parts = unsignedValue.split('.');
            const integerPart = this.formatIntegerPart(parts[0] || '0');
            const fractionPart = this.normalizeFraction(parts[1] || '', shouldPadFraction);

            if (fractionPart === '' && !String(displayValue).includes('.')) {
                return `${sign}${integerPart}`;
            }

            return `${sign}${integerPart}.${fractionPart}`;
        },
        normalizeRawValue(rawValue) {
            const value = String(rawValue || '').trim();

            if (value === '') {
                return '';
            }

            return this.normalizeNumericText(this.stripGrouping(value));
        },
        normalizeNumericText(value) {
            const trimmedValue = String(value || '').trim();
            const sign = trimmedValue.startsWith('-') ? '-' : '';
            const unsignedValue = sign === '' ? trimmedValue : trimmedValue.slice(1);
            const sanitizedValue = unsignedValue.replace(/[^0-9.]/g, '');
            const firstDotIndex = sanitizedValue.indexOf('.');

            if (sanitizedValue === '') {
                return '';
            }

            const integerText = firstDotIndex === -1
                ? sanitizedValue
                : sanitizedValue.slice(0, firstDotIndex);
            const fractionText = firstDotIndex === -1
                ? ''
                : sanitizedValue.slice(firstDotIndex + 1).replace(/\./g, '');
            const normalizedInteger = this.trimLeadingZeros(integerText);

            if (firstDotIndex !== -1) {
                return `${sign}${normalizedInteger}.${this.normalizeFraction(fractionText, false)}`;
            }

            return `${sign}${normalizedInteger}`;
        },
        stripGrouping(value) {
            return String(value || '').replace(/,/g, '');
        },
        integerPart(value) {
            return String(value || '').split('.')[0] || '0';
        },
        normalizeFraction(value, shouldPadFraction = false) {
            const fraction = String(value || '').replace(/[^0-9]/g, '').slice(0, this.precision);

            if (!shouldPadFraction || this.precision <= 0 || (fraction === '' && this.type !== 'money')) {
                return fraction;
            }

            return fraction.padEnd(this.precision, '0');
        },
        trimLeadingZeros(value) {
            const strippedValue = String(value || '').replace(/^0+(?=\d)/, '');

            return strippedValue === '' ? '0' : strippedValue;
        },
        formatIntegerPart(value) {
            const stringValue = String(value || '0');
            const sign = stringValue.startsWith('-') ? '-' : '';
            const unsignedValue = sign === '' ? stringValue : stringValue.slice(1);
            const formattedValue = this.trimLeadingZeros(unsignedValue)
                .replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            return `${sign}${formattedValue}`;
        },
        centsToDecimalAmount(value) {
            const sign = String(value || '').startsWith('-') ? '-' : '';
            const digits = String(value || '').replace(/[^0-9]/g, '').padStart(3, '0');
            const whole = digits.slice(0, -2);
            const cents = digits.slice(-2);

            return `${sign}${this.trimLeadingZeros(whole)}.${cents}`;
        },
        decimalAmountToCents(value) {
            const normalizedValue = this.normalizeNumericText(value);

            if (normalizedValue === '' || normalizedValue === '-') {
                return '';
            }

            const sign = normalizedValue.startsWith('-') ? '-' : '';
            const unsignedValue = sign === '' ? normalizedValue : normalizedValue.slice(1);
            const parts = unsignedValue.split('.');
            const whole = parts[0] || '0';
            const cents = String(parts[1] || '').padEnd(2, '0').slice(0, 2);
            const centsValue = `${this.trimLeadingZeros(whole)}${cents}`.replace(/^0+(?=\d)/, '');

            return `${sign}${centsValue === '' ? '0' : centsValue}`;
        },
    }));
}
