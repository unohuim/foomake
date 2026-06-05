import Alpine from 'alpinejs';

export function mount(rootEl, payload) {
    Alpine.data('profileEdit', () => ({
        toast: {
            visible: false,
            type: 'success',
            message: '',
            timeoutId: null,
        },
        init() {
            if (payload?.status === 'profile-updated') {
                this.showToast('success', 'Saved.');
            }

            if (payload?.status === 'password-updated') {
                this.showToast('success', 'Saved.');
            }

            if (payload?.status === 'verification-link-sent') {
                this.showToast('success', 'A new verification link has been sent to your email address.');
            }
        },
        showToast(type, message) {
            this.toast.type = type;
            this.toast.message = message;
            this.toast.visible = true;

            if (this.toast.timeoutId) {
                window.clearTimeout(this.toast.timeoutId);
            }

            this.toast.timeoutId = window.setTimeout(() => {
                this.toast.visible = false;
                this.toast.timeoutId = null;
            }, 1500);
        },
    }));
}
