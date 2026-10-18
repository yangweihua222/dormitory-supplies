import http from './index';

export const accommodationApi = {
    /** 房间维度列表（房间号 / 是否满 / 业务类型） */
    list: (params) => http.get('/accommodations', { params }),
    /** 房间详情：当前在住学生 */
    detail: (roomId) => http.get(`/accommodations/rooms/${roomId}/detail`),
    /** 房间历史：住过的学生 */
    history: (roomId) => http.get(`/accommodations/rooms/${roomId}/history`),
    store: (data) => http.post('/accommodations', data),
    update: (id, data) => http.put(`/accommodations/${id}`, data),
    destroy: (id) => http.delete(`/accommodations/${id}`),
    handover: (id) => http.post(`/accommodations/${id}/handover`),
    export: (params) => http.get('/accommodations/export', { params, responseType: 'blob', timeout: 60000 }),
};
