export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    Alpine.data('materialsShowSupplierPricing', () => ({
        packages: safePayload.packages || [],
    }));
}
