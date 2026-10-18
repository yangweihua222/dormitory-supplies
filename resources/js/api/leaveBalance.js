import http from './index';

export const leaveBalanceApi = {
    list: (params) => http.get('/leave-balances', { params }),
    options: (exceptId) => http.get('/leave-balances/options', { params: { except_id: exceptId } }),
    store: (data) => http.post('/leave-balances', data),
    update: (id, data) => http.put(`/leave-balances/${id}`, data),
    destroy: (id) => http.delete(`/leave-balances/${id}`),
};
