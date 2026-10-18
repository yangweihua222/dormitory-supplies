import http from './index';

export const shiftApi = {
    list: (params) => http.get('/shifts', { params }),
    create: (data) => http.post('/shifts', data),
    update: (id, data) => http.put(`/shifts/${id}`, data),
    destroy: (id) => http.delete(`/shifts/${id}`),
};
