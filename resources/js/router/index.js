import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { MENU_TREE } from '../config/menus';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/Login.vue'),
        meta: { public: true },
    },
    {
        path: '/',
        component: () => import('../views/Layout.vue'),
        children: [
            { path: '', redirect: '/dashboard' },
            { path: 'dashboard', name: 'dashboard', component: () => import('../views/Dashboard.vue'), meta: { menu: 'dashboard', title: '工作台' } },
            { path: 'users', name: 'user', component: () => import('../views/Users.vue'), meta: { menu: 'user', title: '用户管理' } },
            { path: 'attendance', name: 'attendance', component: () => import('../views/Attendance.vue'), meta: { menu: 'attendance', title: '考勤统计' } },
            { path: 'shift', name: 'shift', component: () => import('../views/Shift.vue'), meta: { menu: 'shift', title: '班次管理' } },
            { path: 'schedule', name: 'schedule', component: () => import('../views/Schedule.vue'), meta: { menu: 'schedule', title: '排班管理' } },
            { path: 'dutylog', name: 'dutylog', component: () => import('../views/DutyLog.vue'), meta: { menu: 'dutylog', title: '值班日志' } },
            { path: 'calendar', name: 'calendar', component: () => import('../views/ScheduleCalendar.vue'), meta: { menu: 'calendar', title: '排班日历' } },
            { path: 'leave', name: 'leave', component: () => import('../views/Leave.vue'), meta: { menu: 'leave', title: '请假列表' } },
            { path: 'leavebalance', name: 'leavebalance', component: () => import('../views/LeaveBalance.vue'), meta: { menu: 'leavebalance', title: '假勤管理' } },
            { path: 'rooms', name: 'room', component: () => import('../views/Room.vue'), meta: { menu: 'room', title: '房源管理' } },
            { path: 'accommodation', name: 'accommodation', component: () => import('../views/Accommodation.vue'), meta: { menu: 'accommodation', title: '住宿管理' } },
            { path: 'roomcard', name: 'card', component: () => import('../views/RoomCard.vue'), meta: { menu: 'card', title: '房卡管理' } },
            { path: 'repair', name: 'repair', component: () => import('../views/Repair.vue'), meta: { menu: 'repair', title: '报修管理' } },
            { path: 'safety', name: 'safety', component: () => import('../views/Safety.vue'), meta: { menu: 'safety', title: '检查列表' } },
            { path: 'volunteer', name: 'volunteer', component: () => import('../views/FireVolunteer.vue'), meta: { menu: 'volunteer', title: '消防志愿服务' } },
            { path: 'training', name: 'training', component: () => import('../views/FireTraining.vue'), meta: { menu: 'training', title: '消防培训' } },
            { path: 'material', name: 'material', component: () => import('../views/Material.vue'), meta: { menu: 'material', title: '物资列表' } },
            { path: 'inbound', name: 'inbound', component: () => import('../views/MaterialStock.vue'), props: { direction: 'in' }, meta: { menu: 'inbound', title: '入库管理' } },
            { path: 'outbound', name: 'outbound', component: () => import('../views/MaterialStock.vue'), props: { direction: 'out' }, meta: { menu: 'outbound', title: '出库管理' } },
            { path: 'work', name: 'work', component: () => import('../views/Work.vue'), meta: { menu: 'work', title: '工作管理' } },
            { path: 'assess', name: 'assess', component: () => import('../views/Assessment.vue'), meta: { menu: 'assess', title: '月度考核' } },
            { path: 'assess-appeal', name: 'assess-appeal', component: () => import('../views/AssessAppeal.vue'), meta: { menu: 'assess-appeal', title: '考核申诉' } },
            { path: 'peer-review', name: 'peer-review', component: () => import('../views/PeerReview.vue'), meta: { menu: 'peer-review', title: '同行评议' } },
            // 其余模块占位（后续迭代逐个替换为真页面）
            { path: 'm/:key', name: 'module', component: () => import('../views/Placeholder.vue'), meta: { title: '模块' } },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

/** key → 路由跳转路径 */
export function menuPath(key) {
    const map = {
        dashboard: '/dashboard',
        user: '/users',
        attendance: '/attendance',
        shift: '/shift',
        schedule: '/schedule',
        dutylog: '/dutylog',
        calendar: '/calendar',
        leave: '/leave',
        leavebalance: '/leavebalance',
        room: '/rooms',
        accommodation: '/accommodation',
        card: '/roomcard',
        repair: '/repair',
        safety: '/safety',
        volunteer: '/volunteer',
        training: '/training',
        material: '/material',
        inbound: '/inbound',
        outbound: '/outbound',
        work: '/work',
        assess: '/assess',
        'assess-appeal': '/assess-appeal',
        'peer-review': '/peer-review',
    };
    return map[key] || `/m/${key}`;
}

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.loaded) await auth.fetchMe();

    if (to.meta.public) {
        // 已登录访问登录页 → 回首页
        return auth.isLoggedIn ? { path: '/dashboard' } : true;
    }

    if (!auth.isLoggedIn) return { name: 'login' };

    // 菜单权限守卫：无权限的模块不得直达（meta.perm 可声明实际权限 key）
    const needKey = to.meta.perm ?? to.meta.menu ?? to.params.key;
    if (needKey && !auth.hasMenu(needKey)) {
        // 回退到第一个可见菜单
        const first = MENU_TREE.flatMap((g) => (g.children ? g.children : [g]))
            .find((m) => auth.hasMenu(m.perm || m.key));
        return first ? menuPath(first.key) : { name: 'login' };
    }

    return true;
});

export default router;
