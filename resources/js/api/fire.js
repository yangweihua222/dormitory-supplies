import http from './index';

export const fireVolunteerApi = {
    list: (params) => http.get('/fire-volunteers', { params }),
    export: (params) => http.get('/fire-volunteers/export', { params, responseType: 'blob', timeout: 60000 }),
    options: () => http.get('/fire-volunteers/options'),
    store: (data) => http.post('/fire-volunteers', data),
    update: (id, data) => http.put(`/fire-volunteers/${id}`, data),
    destroy: (id) => http.delete(`/fire-volunteers/${id}`),
    import: (formData) => http.post('/fire-volunteers/import', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }),
    template: () => http.get('/fire-volunteers/template', { responseType: 'blob', timeout: 60000 }),
};

export const fireTrainingApi = {
    list: (params) => http.get('/fire-trainings', { params }),
    export: (params) => http.get('/fire-trainings/export', { params, responseType: 'blob', timeout: 60000 }),
    options: () => http.get('/fire-trainings/options'),
    store: (data) => http.post('/fire-trainings', data),
    update: (id, data) => http.put(`/fire-trainings/${id}`, data),
    destroy: (id) => http.delete(`/fire-trainings/${id}`),
    import: (formData) => http.post('/fire-trainings/import', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }),
    template: () => http.get('/fire-trainings/template', { responseType: 'blob', timeout: 60000 }),
};
