import http from './index';

export const scheduleApi = {
    list: (params) => http.get('/schedules', { params }),
    export: (params) => http.get('/schedules/export', { params, responseType: 'blob', timeout: 60000 }),
    create: (data) => http.post('/schedules', data),
    update: (id, data) => http.put(`/schedules/${id}`, data),
    destroy: (id) => http.delete(`/schedules/${id}`),
    batch: (data) => http.post('/schedules/batch', data),
    calendar: (params) => http.get('/schedules/calendar', { params }),
    template: () => http.get('/schedules/template', { responseType: 'blob', timeout: 60000 }),
    importExcel: (formData) => http.post('/schedules/import', formData, { timeout: 60000 }),
};
