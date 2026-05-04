export function mount(rootEl, payload) {
    const Alpine = window.Alpine;
    const safePayload = payload || {};

    const emptyErrors = () => ({
        name: [],
        status: [],
        notes: [],
        address_line_1: [],
        address_line_2: [],
        city: [],
        region: [],
        postal_code: [],
        country_code: [],
        formatted_address: [],
    });

    const emptyContactErrors = () => ({
        first_name: [],
        last_name: [],
        email: [],
        phone: [],
        role: [],
        is_primary: [],
    });

    const emptyOrderErrors = () => ({
        customer_id: [],
        contact_id: [],
    });

    const emptyOrderLineErrors = () => ({
        item_id: [],
        quantity: [],
    });

    const customerToForm = (customer) => ({
        name: customer.name || '',
        status: customer.status || 'active',
        notes: customer.notes || '',
        address_line_1: customer.address_line_1 || '',
        address_line_2: customer.address_line_2 || '',
        city: customer.city || '',
        region: customer.region || '',
        postal_code: customer.postal_code || '',
        country_code: customer.country_code || '',
        formatted_address: customer.formatted_address || '',
    });

    const emptyContactForm = () => ({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        role: '',
    });

    const contactToForm = (contact) => ({
        first_name: contact.first_name || '',
        last_name: contact.last_name || '',
        email: contact.email || '',
        phone: contact.phone || '',
        role: contact.role || '',
    });

    const emptyOrderForm = () => ({
        customer_id: '',
        contact_id: '',
    });

    const emptyOrderLineForm = () => ({
        item_id: '',
        quantity: '1.000000',
    });

    const orderToForm = (order) => ({
        customer_id: order.customer_id ? String(order.customer_id) : '',
        contact_id: order.contact_id ? String(order.contact_id) : '',
    });

    Alpine.data('salesCustomersShow', () => ({
        customer: safePayload.customer || {},
        contacts: safePayload.contacts || [],
        orders: safePayload.orders || [],
        orderCustomers: safePayload.orderCustomers || [],
        orderItems: safePayload.orderItems || [],
        canManage: safePayload.canManage || false,
        canManageOrders: safePayload.canManageOrders || false,
        updateUrl: safePayload.updateUrl || '',
        deleteUrl: safePayload.deleteUrl || '',
        contactsStoreUrl: safePayload.contactsStoreUrl || '',
        contactsBaseUrl: safePayload.contactsBaseUrl || '',
        ordersStoreUrl: safePayload.ordersStoreUrl || '',
        ordersUpdateUrlBase: safePayload.ordersUpdateUrlBase || '',
        ordersDeleteUrlBase: safePayload.ordersDeleteUrlBase || '',
        ordersLineStoreUrlBase: safePayload.ordersLineStoreUrlBase || '',
        indexUrl: safePayload.indexUrl || '/sales/customers',
        csrfToken: safePayload.csrfToken || '',
        statuses: safePayload.statuses || ['active', 'inactive', 'archived'],
        isFormOpen: false,
        isSubmitting: false,
        form: customerToForm(safePayload.customer || {}),
        errors: emptyErrors(),
        generalError: '',
        isContactFormOpen: false,
        isContactSubmitting: false,
        contactFormMode: 'create',
        editingContactId: null,
        contactForm: emptyContactForm(),
        contactErrors: emptyContactErrors(),
        contactGeneralError: '',
        isOrderFormOpen: false,
        isOrderSubmitting: false,
        orderFormMode: 'create',
        editingOrderId: null,
        orderForm: emptyOrderForm(),
        orderErrors: emptyOrderErrors(),
        orderGeneralError: '',
        orderLineForms: {},
        orderLineErrorsByOrder: {},
        orderLineGeneralErrorsByOrder: {},
        orderLineEditQuantities: {},
        orderLineEditErrorsByLine: {},
        toast: {
            visible: false,
            message: '',
            type: 'success',
            timeoutId: null,
        },
        init() {
            this.orders.forEach((order) => {
                this.syncOrderLineState(order);
            });
        },
        normalizeErrors(errors) {
            const normalized = emptyErrors();

            if (!errors || typeof errors !== 'object') {
                return normalized;
            }

            Object.keys(normalized).forEach((key) => {
                normalized[key] = Array.isArray(errors[key]) ? errors[key] : [];
            });

            return normalized;
        },
        normalizeContactErrors(errors) {
            const normalized = emptyContactErrors();

            if (!errors || typeof errors !== 'object') {
                return normalized;
            }

            Object.keys(normalized).forEach((key) => {
                normalized[key] = Array.isArray(errors[key]) ? errors[key] : [];
            });

            return normalized;
        },
        normalizeOrderErrors(errors) {
            const normalized = emptyOrderErrors();

            if (!errors || typeof errors !== 'object') {
                return normalized;
            }

            Object.keys(normalized).forEach((key) => {
                normalized[key] = Array.isArray(errors[key]) ? errors[key] : [];
            });

            return normalized;
        },
        normalizeOrderLineErrors(errors) {
            const normalized = emptyOrderLineErrors();

            if (!errors || typeof errors !== 'object') {
                return normalized;
            }

            Object.keys(normalized).forEach((key) => {
                normalized[key] = Array.isArray(errors[key]) ? errors[key] : [];
            });

            return normalized;
        },
        showToast(type, message) {
            this.toast.type = type;
            this.toast.message = message;
            this.toast.visible = true;

            if (this.toast.timeoutId) {
                clearTimeout(this.toast.timeoutId);
            }

            this.toast.timeoutId = setTimeout(() => {
                this.toast.visible = false;
            }, 2500);
        },
        openEdit() {
            if (!this.canManage) {
                return;
            }

            this.form = customerToForm(this.customer);
            this.errors = emptyErrors();
            this.generalError = '';
            this.isFormOpen = true;
        },
        closeForm() {
            this.isFormOpen = false;
            this.isSubmitting = false;
            this.errors = emptyErrors();
            this.generalError = '';
        },
        openContactCreate() {
            if (!this.canManage) {
                return;
            }

            this.contactFormMode = 'create';
            this.editingContactId = null;
            this.contactForm = emptyContactForm();
            this.contactErrors = emptyContactErrors();
            this.contactGeneralError = '';
            this.isContactFormOpen = true;
        },
        openContactEdit(contact) {
            if (!this.canManage) {
                return;
            }

            this.contactFormMode = 'edit';
            this.editingContactId = contact.id;
            this.contactForm = contactToForm(contact);
            this.contactErrors = emptyContactErrors();
            this.contactGeneralError = '';
            this.isContactFormOpen = true;
        },
        closeContactForm() {
            this.isContactFormOpen = false;
            this.isContactSubmitting = false;
            this.contactErrors = emptyContactErrors();
            this.contactGeneralError = '';
        },
        upsertContact(contact) {
            const existingIndex = this.contacts.findIndex((entry) => entry.id === contact.id);

            if (existingIndex === -1) {
                this.contacts.push(contact);
            } else {
                this.contacts.splice(existingIndex, 1, contact);
            }

            this.contacts.sort((left, right) => {
                if (left.is_primary === right.is_primary) {
                    return left.full_name.localeCompare(right.full_name);
                }

                return left.is_primary ? -1 : 1;
            });

            this.syncOrderCustomerContacts();
        },
        syncOrderCustomerContacts() {
            this.orderCustomers = this.orderCustomers.map((entry) => {
                if (entry.id !== this.customer.id) {
                    return entry;
                }

                const primaryContact = this.contacts.find((contact) => contact.is_primary);

                return {
                    ...entry,
                    primary_contact_id: primaryContact ? primaryContact.id : null,
                    contacts: this.contacts.map((contact) => ({
                        id: contact.id,
                        customer_id: contact.customer_id,
                        full_name: contact.full_name,
                        is_primary: contact.is_primary,
                    })),
                };
            });
        },
        selectedOrderCustomer() {
            const customerId = Number(this.orderForm.customer_id);

            if (!customerId) {
                return null;
            }

            return this.orderCustomers.find((entry) => entry.id === customerId) || null;
        },
        selectedOrderCustomerContacts() {
            return this.selectedOrderCustomer()?.contacts || [];
        },
        orderContactOptionLabel(contact) {
            return contact.full_name;
        },
        formatLineMoney(amount, currencyCode) {
            return `${currencyCode} ${amount}`;
        },
        formatOrderLineMoney(amount, lines) {
            const firstLine = (lines || [])[0];
            const currencyCode = firstLine ? firstLine.unit_price_currency_code : 'USD';

            return this.formatLineMoney(amount, currencyCode);
        },
        defaultOrderContactIdForCustomer(customerId) {
            const customer = this.orderCustomers.find((entry) => entry.id === Number(customerId));

            if (!customer || !customer.primary_contact_id) {
                return '';
            }

            return String(customer.primary_contact_id);
        },
        handleOrderCustomerChange() {
            this.orderForm.contact_id = this.defaultOrderContactIdForCustomer(this.orderForm.customer_id);
        },
        ensureOrderLineForm(orderId) {
            if (!this.orderLineForms[orderId]) {
                this.orderLineForms[orderId] = emptyOrderLineForm();
            }

            if (!this.orderLineErrorsByOrder[orderId]) {
                this.orderLineErrorsByOrder[orderId] = emptyOrderLineErrors();
            }

            if (!Object.prototype.hasOwnProperty.call(this.orderLineGeneralErrorsByOrder, orderId)) {
                this.orderLineGeneralErrorsByOrder[orderId] = '';
            }
        },
        syncOrderLineState(order) {
            this.ensureOrderLineForm(order.id);

            (order.lines || []).forEach((line) => {
                this.orderLineEditQuantities[line.id] = line.quantity;

                if (!this.orderLineEditErrorsByLine[line.id]) {
                    this.orderLineEditErrorsByLine[line.id] = emptyOrderLineErrors();
                }
            });
        },
        openOrderCreate() {
            if (!this.canManageOrders || this.orderItems.length === 0) {
                return;
            }

            this.orderFormMode = 'create';
            this.editingOrderId = null;
            this.orderForm = {
                customer_id: String(this.customer.id || ''),
                contact_id: this.defaultOrderContactIdForCustomer(this.customer.id),
            };
            this.orderErrors = emptyOrderErrors();
            this.orderGeneralError = '';
            this.isOrderFormOpen = true;
        },
        openOrderEdit(order) {
            if (!this.canManageOrders) {
                return;
            }

            this.orderFormMode = 'edit';
            this.editingOrderId = order.id;
            this.orderForm = orderToForm(order);
            this.orderErrors = emptyOrderErrors();
            this.orderGeneralError = '';
            this.isOrderFormOpen = true;
        },
        closeOrderForm() {
            this.isOrderFormOpen = false;
            this.isOrderSubmitting = false;
            this.orderErrors = emptyOrderErrors();
            this.orderGeneralError = '';
        },
        upsertOrder(order) {
            if (order.customer_id !== this.customer.id) {
                this.orders = this.orders.filter((entry) => entry.id !== order.id);
                return;
            }

            const existingIndex = this.orders.findIndex((entry) => entry.id === order.id);

            if (existingIndex === -1) {
                this.orders.unshift(order);
                this.syncOrderLineState(order);
                return;
            }

            this.orders.splice(existingIndex, 1, order);
            this.syncOrderLineState(order);
        },
        async submitForm() {
            if (this.isSubmitting || !this.canManage) {
                return;
            }

            this.isSubmitting = true;
            this.errors = emptyErrors();
            this.generalError = '';
            const addressPayload = {
                address_line_1: this.form.address_line_1 || null,
                address_line_2: this.form.address_line_2 || null,
                city: this.form.city || null,
                region: this.form.region || null,
                postal_code: this.form.postal_code || null,
                country_code: this.form.country_code || null,
                formatted_address: this.form.formatted_address || null,
            };

            const response = await fetch(this.updateUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    name: this.form.name,
                    status: this.form.status,
                    notes: this.form.notes || null,
                    ...addressPayload,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.errors = this.normalizeErrors(data.errors);
                this.generalError = data.message || 'Validation failed.';
                this.isSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.generalError = 'Something went wrong. Please try again.';
                this.showToast('error', this.generalError);
                this.isSubmitting = false;
                return;
            }

            const data = await response.json();
            this.customer = data.data;
            this.closeForm();
            this.showToast('success', 'Customer updated.');
        },
        async submitContactForm() {
            if (this.isContactSubmitting || !this.canManage) {
                return;
            }

            this.isContactSubmitting = true;
            this.contactErrors = emptyContactErrors();
            this.contactGeneralError = '';

            const isCreate = this.contactFormMode === 'create';
            const url = isCreate
                ? this.contactsStoreUrl
                : `${this.contactsBaseUrl}/${this.editingContactId}`;
            const method = isCreate ? 'POST' : 'PATCH';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    first_name: this.contactForm.first_name,
                    last_name: this.contactForm.last_name,
                    email: this.contactForm.email || null,
                    phone: this.contactForm.phone || null,
                    role: this.contactForm.role || null,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.contactErrors = this.normalizeContactErrors(data.errors);
                this.contactGeneralError = data.message || 'Validation failed.';
                this.isContactSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.contactGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', this.contactGeneralError);
                this.isContactSubmitting = false;
                return;
            }

            const data = await response.json();
            this.upsertContact(data.data);
            this.closeContactForm();
            this.showToast('success', isCreate ? 'Contact created.' : 'Contact updated.');
        },
        async submitOrderForm() {
            if (this.isOrderSubmitting || !this.canManageOrders) {
                return;
            }

            this.isOrderSubmitting = true;
            this.orderErrors = emptyOrderErrors();
            this.orderGeneralError = '';

            const isCreate = this.orderFormMode === 'create';
            const url = isCreate
                ? this.ordersStoreUrl
                : `${this.ordersUpdateUrlBase}/${this.editingOrderId}`;
            const method = isCreate ? 'POST' : 'PATCH';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    customer_id: this.orderForm.customer_id === '' ? null : Number(this.orderForm.customer_id),
                    contact_id: this.orderForm.contact_id === '' ? null : Number(this.orderForm.contact_id),
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.orderErrors = this.normalizeOrderErrors(data.errors);
                this.orderGeneralError = data.message || 'Validation failed.';
                this.isOrderSubmitting = false;
                return;
            }

            if (!response.ok) {
                this.orderGeneralError = 'Something went wrong. Please try again.';
                this.showToast('error', this.orderGeneralError);
                this.isOrderSubmitting = false;
                return;
            }

            const data = await response.json();
            this.upsertOrder(data.data);
            this.closeOrderForm();
            this.showToast('success', isCreate ? 'Sales order created.' : 'Sales order updated.');
        },
        async submitOrderLine(order) {
            if (!this.canManageOrders) {
                return;
            }

            this.ensureOrderLineForm(order.id);
            this.orderLineErrorsByOrder[order.id] = emptyOrderLineErrors();
            this.orderLineGeneralErrorsByOrder[order.id] = '';

            const response = await fetch(`${this.ordersLineStoreUrlBase}/${order.id}/lines`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    item_id: this.orderLineForms[order.id].item_id === '' ? null : Number(this.orderLineForms[order.id].item_id),
                    quantity: this.orderLineForms[order.id].quantity,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.orderLineErrorsByOrder[order.id] = this.normalizeOrderLineErrors(data.errors);
                this.orderLineGeneralErrorsByOrder[order.id] = data.message || 'Validation failed.';
                return;
            }

            if (!response.ok) {
                this.orderLineGeneralErrorsByOrder[order.id] = 'Unable to add line.';
                this.showToast('error', this.orderLineGeneralErrorsByOrder[order.id]);
                return;
            }

            const data = await response.json();
            this.upsertOrder(data.data.order);
            this.orderLineForms[order.id] = emptyOrderLineForm();
            this.orderLineErrorsByOrder[order.id] = emptyOrderLineErrors();
            this.orderLineGeneralErrorsByOrder[order.id] = '';
            this.showToast('success', 'Line added.');
        },
        async saveOrderLineQuantity(order, line) {
            if (!this.canManageOrders) {
                return;
            }

            this.orderLineEditErrorsByLine[line.id] = emptyOrderLineErrors();

            const response = await fetch(`${this.ordersLineStoreUrlBase}/${order.id}/lines/${line.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify({
                    quantity: this.orderLineEditQuantities[line.id] || line.quantity,
                }),
            });

            if (response.status === 422) {
                const data = await response.json();
                this.orderLineEditErrorsByLine[line.id] = this.normalizeOrderLineErrors(data.errors);
                this.showToast('error', data.message || 'Unable to update line quantity.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to update line quantity.');
                return;
            }

            const data = await response.json();
            this.upsertOrder(data.data.order);
            this.showToast('success', 'Line quantity updated.');
        },
        async deleteOrderLine(order, line) {
            if (!this.canManageOrders) {
                return;
            }

            const response = await fetch(`${this.ordersLineStoreUrlBase}/${order.id}/lines/${line.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.showToast('error', data.message || 'Unable to remove line.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to remove line.');
                return;
            }

            const data = await response.json();
            this.upsertOrder(data.data.order);
            this.showToast('success', 'Line removed.');
        },
        async setPrimary(contact) {
            if (!this.canManage) {
                return;
            }

            const response = await fetch(`${this.contactsBaseUrl}/${contact.id}/primary`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to update the primary contact.');
                return;
            }

            const data = await response.json();
            this.contacts = this.contacts.map((entry) => ({
                ...entry,
                is_primary: entry.id === data.data.id,
            }));
            this.upsertContact(data.data);
            this.showToast('success', 'Primary contact updated.');
        },
        async deleteContact(contact) {
            if (!this.canManage) {
                return;
            }

            const response = await fetch(`${this.contactsBaseUrl}/${contact.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (response.status === 422) {
                const data = await response.json();
                this.showToast('error', data.message || 'Unable to delete contact.');
                return;
            }

            if (!response.ok) {
                this.showToast('error', 'Unable to delete contact.');
                return;
            }

            this.contacts = this.contacts.filter((entry) => entry.id !== contact.id);
            this.syncOrderCustomerContacts();
            this.showToast('success', 'Contact deleted.');
        },
        async deleteOrder(order) {
            if (!this.canManageOrders) {
                return;
            }

            const response = await fetch(`${this.ordersDeleteUrlBase}/${order.id}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to delete sales order.');
                return;
            }

            this.orders = this.orders.filter((entry) => entry.id !== order.id);
            this.showToast('success', 'Sales order deleted.');
        },
        async archive() {
            if (!this.canManage) {
                return;
            }

            const response = await fetch(this.deleteUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (!response.ok) {
                this.showToast('error', 'Unable to archive customer.');
                return;
            }

            const data = await response.json();
            this.customer = data.data;
            window.location.href = this.indexUrl;
        },
    }));
}
