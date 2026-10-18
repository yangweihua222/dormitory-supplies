import http from './index';

export const leaveApi = {
    list: (params) => http.get('/leaves', { params }),
    export: (params) => http.get('/leaves/export', { params, responseType: 'blob', timeout: 60000 }),
    options: () => http.get('/leaves/options'),
    annualRemain: (userId) => http.get('/leaves/annual-remain', { params: { user_id: userId } }),
    store: (data) => http.post('/leaves', data),
    update: (id, data) => http.put(`/leaves/${id}`, data),
    destroy: (id) => http.delete(`/leaves/${id}`),
    approve: (id) => http.post(`/leaves/${id}/approve`),
    reject: (id) => http.post(`/leaves/${id}/reject`),
    upload: (fd) => http.post('/leaves/upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } }),
};
