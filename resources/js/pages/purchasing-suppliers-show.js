export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};
    const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': safePayload.csrfToken || '',
    };

    Alpine.data('purchasingSuppliersShow', () => {
        const tenantCurrencyCode = safePayload.tenantCurrencyCode || 'USD';
        const supplierCurrencyCode = safePayload.supplierCurrencyCode || null;

        return {
            supplier: safePayload.supplier || {},
            packages: safePayload.packages || [],
            canManage: safePayload.canManage || false,
            purchasableItems: safePayload.purchasableItems || [],
            uoms: safePayload.uoms || [],
            packageStoreUrl: safePayload.packageStoreUrl || '',
            priceStoreUrlBase: safePayload.priceStoreUrlBase || '',
            tenantCurrencyCode,
            supplierCurrencyCode,
            form: {
                item_id: '',
                pack_quantity: '1.000000',
                pack_uom_id: '',
                supplier_sku: '',
                price_cents: '',
                price_currency_code: supplierCurrencyCode || tenantCurrencyCode,
                fx_rate: '',
                fx_rate_as_of: '',
            },
            packageErrors: {},
            priceErrors: {},
            generalError: '',
            isSubmitting: false,
            hasFxFields() {
                if (!this.form.price_currency_code) {
                    return false;
                }

                return this.form.price_currency_code.toUpperCase() !== this.tenantCurrencyCode;
            },
            resetForm() {
                this.form = {
                    item_id: '',
                    pack_quantity: '1.000000',
                    pack_uom_id: '',
                    supplier_sku: '',
                    price_cents: '',
                    price_currency_code: this.supplierCurrencyCode || this.tenantCurrencyCode,
                    fx_rate: '',
                    fx_rate_as_of: '',
                };
                this.packageErrors = {};
                this.priceErrors = {};
                this.generalError = '';
            },
            async submitPackageAndPrice() {
                if (!this.canManage || !this.packageStoreUrl || !this.priceStoreUrlBase) {
                    return;
                }

                this.isSubmitting = true;
                this.generalError = '';
                this.packageErrors = {};
                this.priceErrors = {};

                const packagePayload = {
                    item_id: Number(this.form.item_id) || null,
                    pack_quantity: this.form.pack_quantity || '1.000000',
                    pack_uom_id: Number(this.form.pack_uom_id) || null,
                    supplier_sku: this.form.supplier_sku || null,
                };

                try {
                    const packageResponse = await fetch(this.packageStoreUrl, {
                        method: 'POST',
                        headers,
                        body: JSON.stringify(packagePayload),
                    });

                    if (packageResponse.status === 422) {
                        const data = await packageResponse.json();
                        this.packageErrors = data.errors || {};
                        this.generalError = data.message || 'Unable to save package.';
                        return;
                    }

                    if (!packageResponse.ok) {
                        this.generalError = 'Unable to save package. Please try again.';
                        return;
                    }

                    const packageData = await packageResponse.json();
                    const optionId = packageData.data?.id;

                    if (!optionId) {
                        this.generalError = 'Unable to retrieve the created package.';
                        return;
                    }

                    const currencyInput = this.form.price_currency_code?.trim();
                    const pricePayload = {
                        price_cents: this.form.price_cents,
                    };

                    if (currencyInput) {
                        pricePayload.price_currency_code = currencyInput.toUpperCase();
                    }

                    if (this.form.fx_rate) {
                        pricePayload.fx_rate = this.form.fx_rate;
                    }

                    if (this.form.fx_rate_as_of) {
                        pricePayload.fx_rate_as_of = this.form.fx_rate_as_of;
                    }

                    const priceResponse = await fetch(`${this.priceStoreUrlBase}/${optionId}/prices`, {
                        method: 'POST',
                        headers,
                        body: JSON.stringify(pricePayload),
                    });

                    if (priceResponse.status === 422) {
                        const body = await priceResponse.json();
                        this.priceErrors = body.errors || {};
                        this.generalError = body.message || 'Unable to save price.';
                        return;
                    }

                    if (!priceResponse.ok) {
                        this.generalError = 'Unable to save price. Please try again.';
                        return;
                    }

                    const priceBody = await priceResponse.json();
                    const item = this.purchasableItems.find((entry) => entry.id === Number(packagePayload.item_id));
                    const uom = this.uoms.find((entry) => entry.id === Number(packagePayload.pack_uom_id));

                    this.packages.push({
                        id: optionId,
                        item_id: packagePayload.item_id,
                        item_name: item?.name ?? 'Material',
                        pack_quantity: packagePayload.pack_quantity,
                        pack_uom_id: packagePayload.pack_uom_id,
                        pack_uom_symbol: uom?.symbol ?? '—',
                        pack_uom_name: uom?.name ?? '',
                        supplier_sku: this.form.supplier_sku || null,
                        current_price_display: this.formatMoney(
                            priceBody.data.price_currency_code,
                            priceBody.data.converted_price_cents
                        ),
                        current_price_currency_code: priceBody.data.price_currency_code,
                        current_price_cents: priceBody.data.converted_price_cents,
                    });

                    this.resetForm();
                } catch (error) {
                    // eslint-disable-next-line no-console
                    console.error(error);
                    this.generalError = 'Unable to save package and price.';
                } finally {
                    this.isSubmitting = false;
                }
            },
            formatMoney(currency, cents) {
                if (!currency || cents === undefined || cents === null) {
                    return '—';
                }

                return `${currency} ${(cents / 100).toFixed(2)}`;
            },
        };
    });
}
