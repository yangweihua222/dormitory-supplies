import http from './index';

export const dutyLogApi = {
    list: (params) => http.get('/duty-logs', { params }),
    schema: () => http.get('/duty-logs/schema'),
    store: (data) => http.post('/duty-logs', data),
    update: (id, data) => http.put(`/duty-logs/${id}`, data),
    destroy: (id) => http.delete(`/duty-logs/${id}`),
    handover: (id) => http.post(`/duty-logs/${id}/handover`),
    upload: (formData) => http.post('/duty-logs/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }),
};
