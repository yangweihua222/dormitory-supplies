import http from './index';

export const safetyApi = {
    list: (params) => http.get('/safety-checks', { params }),
    export: (params) => http.get('/safety-checks/export', { params, responseType: 'blob', timeout: 60000 }),
    options: () => http.get('/safety-checks/options'),
    store: (data) => http.post('/safety-checks', data),
    update: (id, data) => http.put(`/safety-checks/${id}`, data),
    destroy: (id) => http.delete(`/safety-checks/${id}`),
    upload: (formData) => http.post('/safety-checks/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }),
};
