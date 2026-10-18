import http from './index';

export const attendanceApi = {
    list: (params) => http.get('/attendance', { params }),
    stats: (params) => http.get('/attendance/stats', { params }),
    export: (params) => http.get('/attendance/export', { params, responseType: 'blob', timeout: 60000 }),
};
