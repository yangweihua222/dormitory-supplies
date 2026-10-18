import http from './index';

export const roomApi = {
    list: (params) => http.get('/rooms', { params }),
    all: () => http.get('/rooms/all'),
    options: () => http.get('/rooms/options'),
    store: (data) => http.post('/rooms', data),
    update: (id, data) => http.put(`/rooms/${id}`, data),
    destroy: (id) => http.delete(`/rooms/${id}`),
};
