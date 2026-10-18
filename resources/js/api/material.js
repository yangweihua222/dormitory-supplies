import http from './index';

/** 物资列表（需求 §8 子模块①） */
export const materialApi = {
    list: (params) => http.get('/materials', { params }),
    export: (params) => http.get('/materials/export', { params, responseType: 'blob', timeout: 60000 }),
    options: () => http.get('/materials/options'),
    store: (data) => http.post('/materials', data),
    update: (id, data) => http.put(`/materials/${id}`, data),
    destroy: (id) => http.delete(`/materials/${id}`),
};

/** 入库 / 出库流水（direction: 'in' | 'out'） */
export const materialStockApi = {
    list: (direction, params) => http.get(`/material-stocks/${direction}`, { params }),
    export: (direction, params) => http.get(`/material-stocks/${direction}/export`, { params, responseType: 'blob', timeout: 60000 }),
    options: () => http.get('/material-stocks/options'),
    store: (direction, data) => http.post(`/material-stocks/${direction}`, data),
    update: (direction, id, data) => http.put(`/material-stocks/${direction}/${id}`, data),
    destroy: (direction, id) => http.delete(`/material-stocks/${direction}/${id}`),
};

/** 工作管理 Dashboard（需求 §9，只读） */
export const workApi = {
    stats: (year) => http.get('/work/stats', { params: { year } }),
    assessStats: (params) => http.get('/work/assess-stats', { params }),
    exportBuyScrap: (year) => http.get('/work/export-buy-scrap', { params: { year }, responseType: 'blob', timeout: 60000 }),
    exportInUse: () => http.get('/work/export-in-use', { responseType: 'blob', timeout: 60000 }),
};
