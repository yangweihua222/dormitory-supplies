import { defineStore } from 'pinia';
import http from '../api';

/** 当前登录用户（技术方案 §4.2 三层权限模型的前端载体） */
export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loaded: false,
    }),
    getters: {
        isLoggedIn: (s) => !!s.user,
        isAdmin: (s) => !!s.user?.is_admin,
        menuKeys: (s) => (s.user?.menus || []).map((m) => m.key),
    },
    actions: {
        async fetchMe() {
            try {
                const res = await http.get('/me');
                this.user = res.data;
            } catch {
                this.user = null;
            } finally {
                this.loaded = true;
            }
        },
        async login(payload) {
            const res = await http.post('/login', payload);
            this.user = res.data;
            this.loaded = true;
        },
        async logout() {
            await http.post('/logout');
            this.user = null;
        },
        hasMenu(key) {
            return this.menuKeys.includes(key);
        },
    },
});
