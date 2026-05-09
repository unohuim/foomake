import { normalizeCrudConfig } from './crud-config';

const allowedSortDirections = ['asc', 'desc'];

export function createGenericCrud(config) {
    const crud = normalizeCrudConfig(config);

    return {
        ...crud,
        endpoints: crud.endpoints && typeof crud.endpoints === 'object' ? crud.endpoints : {},
        columns: Array.isArray(crud.columns) ? crud.columns : [],
        headers: crud.headers && typeof crud.headers === 'object' ? crud.headers : {},
        sortable: Array.isArray(crud.sortable) ? crud.sortable : [],
        buildListQueryParams(search, sort) {
            const params = new URLSearchParams();
            const normalizedSearch = typeof search === 'string' ? search.trim() : '';
            const normalizedColumn = this.sortable.includes(sort?.column) ? sort.column : null;
            const normalizedDirection = allowedSortDirections.includes(sort?.direction) ? sort.direction : null;

            if (normalizedSearch !== '') {
                params.set('search', normalizedSearch);
            }

            if (normalizedColumn && normalizedDirection) {
                params.set('sort', normalizedColumn);
                params.set('direction', normalizedDirection);
            }

            return params;
        },
        nextSort(currentSort, column) {
            if (!this.sortable.includes(column)) {
                return currentSort;
            }

            if (currentSort?.column !== column) {
                return {
                    column,
                    direction: 'desc',
                };
            }

            return {
                column,
                direction: currentSort.direction === 'desc' ? 'asc' : 'desc',
            };
        },
        async fetchList({ search, sort, onStart, onSuccess, onValidationError, onError, onFinally }) {
            if (!this.endpoints.list) {
                onFinally?.();
                return;
            }

            onStart?.();

            try {
                const params = this.buildListQueryParams(search, sort);
                const queryString = params.toString();
                const requestUrl = queryString === '' ? this.endpoints.list : `${this.endpoints.list}?${queryString}`;
                const response = await fetch(requestUrl, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (response.status === 422) {
                    const data = await response.json();
                    onValidationError?.(data);
                    return;
                }

                if (!response.ok) {
                    onError?.();
                    return;
                }

                onSuccess?.(await response.json());
            } catch (error) {
                onError?.(error);
            } finally {
                onFinally?.();
            }
        },
        async submitCreate({ body, csrfToken, onValidationError, onError, onSuccess, onFinally }) {
            if (!this.endpoints.create) {
                onError?.();
                onFinally?.();
                return;
            }

            try {
                const response = await fetch(this.endpoints.create, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(body),
                });

                if (response.status === 422) {
                    const data = await response.json();
                    onValidationError?.(data);
                    return;
                }

                if (!response.ok) {
                    onError?.();
                    return;
                }

                onSuccess?.(await response.json());
            } catch (error) {
                onError?.(error);
            } finally {
                onFinally?.();
            }
        },
    };
}
