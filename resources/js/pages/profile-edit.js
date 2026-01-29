import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    Alpine.data('profileEdit', () => ({}));

    Alpine.data('flashNotice', () => ({
        show: true,
        init() {
            setTimeout(() => {
                this.show = false;
            }, 2000);
        },
    }));
}
