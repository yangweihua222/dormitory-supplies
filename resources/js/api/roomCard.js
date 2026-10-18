import http from './index';

export const roomCardApi = {
    list: (params) => http.get('/room-cards', { params }),
    store: (data) => http.post('/room-cards', data),
    update: (id, data) => http.put(`/room-cards/${id}`, data),
    destroy: (id) => http.delete(`/room-cards/${id}`),
};
