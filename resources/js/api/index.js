import axios from 'axios';
import { ElMessage } from 'element-plus';
import router from '../router';

/**
 * Axios 封装（技术方案 §3.2）：
 * - 同源 /api 基础路径；Session Cookie 认证
 * - XSRF-TOKEN Cookie → X-XSRF-TOKEN 头（axios 默认行为）
 * - 401 跳登录；统一错误提示
 */
const http = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
});

http.interceptors.response.use(
    (res) => res.data,
    (err) => {
        const res = err.response;
        const message = res?.data?.message || '网络异常，请稍后重试';

        if (res?.status === 401) {
            // 未登录：清状态回登录页（避免在登录页自身重复跳转）
            if (router.currentRoute.value.name !== 'login') {
                ElMessage.error(message);
                router.push({ name: 'login' });
            }
        } else if (res?.status === 403) {
            ElMessage.error(message);
        } else if (res?.status !== 422) {
            // 422 由表单内联提示处理
            ElMessage.error(message);
        }

        return Promise.reject(res?.data || err);
    }
);

export default http;
