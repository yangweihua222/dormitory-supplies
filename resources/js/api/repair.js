import http from './index';

export const repairApi = {
    list: (params) => http.get('/repairs', { params }),
    options: () => http.get('/repairs/options'),
    store: (data) => http.post('/repairs', data),
    update: (id, data) => http.put(`/repairs/${id}`, data),
    destroy: (id) => http.delete(`/repairs/${id}`),
    export: (params) => http.get('/repairs/export', { params, responseType: 'blob' }),
};
