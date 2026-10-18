import http from './index';

/** 月度考核（需求 10.1） */
export const assessmentApi = {
    list: (params) => http.get('/assessments', { params }),
    export: (params) => http.get('/assessments/export', { params, responseType: 'blob', timeout: 60000 }),
    options: () => http.get('/assessments/options'),
    store: (data) => http.post('/assessments', data),
    update: (id, data) => http.put(`/assessments/${id}`, data),
    destroy: (id) => http.delete(`/assessments/${id}`),
};

/** 考核申诉（需求 10.2） */
export const appealApi = {
    list: (params) => http.get('/assessment-appeals', { params }),
    export: (params) => http.get('/assessment-appeals/export', { params, responseType: 'blob', timeout: 60000 }),
    store: (data) => http.post('/assessment-appeals', data),
    update: (id, data) => http.put(`/assessment-appeals/${id}`, data),
    destroy: (id) => http.delete(`/assessment-appeals/${id}`),
};

/** 同行评议（需求 10.3） */
export const peerApi = {
    list: (params) => http.get('/peer-reviews', { params }),
    options: () => http.get('/peer-reviews/options'),
    store: (data) => http.post('/peer-reviews', data),
    update: (id, data) => http.put(`/peer-reviews/${id}`, data),
    destroy: (id) => http.delete(`/peer-reviews/${id}`),
};
