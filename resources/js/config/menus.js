/**
 * 侧边栏菜单结构（与原型 app.js MENU 一致）。
 * key = 后端 menus.key；分组仅作展示，可见性按子项权限过滤，空组隐藏。
 */
export const MENU_TREE = [
    { key: 'dashboard', label: '工作台', icon: 'dashboard' },
    { key: 'user', label: '用户管理', icon: 'users' },
    {
        label: '考勤管理', icon: 'clock', children: [
            { key: 'attendance', label: '考勤统计', icon: 'clock' },
            { key: 'shift', label: '班次管理', icon: 'shift' },
        ],
    },
    {
        label: '值班管理', icon: 'duty', children: [
            { key: 'schedule', label: '排班管理', icon: 'schedule' },
            { key: 'dutylog', label: '值班日志', icon: 'duty' },
            { key: 'calendar', label: '排班日历', icon: 'calendar' },
        ],
    },
    {
        label: '请假管理', icon: 'leave', children: [
            { key: 'leave', label: '请假列表', icon: 'leave' },
            { key: 'leavebalance', label: '假勤管理', icon: 'calendar' },
        ],
    },
    {
        label: '宿舍管理', icon: 'home', children: [
            { key: 'room', label: '房源管理', icon: 'bed' },
            { key: 'accommodation', label: '住宿管理', icon: 'home' },
            { key: 'card', label: '房卡管理', icon: 'card' },
        ],
    },
    { key: 'repair', label: '报修管理', icon: 'tool' },
    {
        label: '安全管理', icon: 'shield', children: [
            { key: 'safety', label: '检查列表', icon: 'shield' },
            { key: 'volunteer', label: '消防志愿服务', icon: 'users' },
            { key: 'training', label: '消防培训', icon: 'list' },
        ],
    },
    {
        label: '物资管理', icon: 'box', children: [
            { key: 'material', label: '物资列表', icon: 'box' },
            { key: 'inbound', label: '入库管理', icon: 'download' },
            { key: 'outbound', label: '出库管理', icon: 'upload' },
        ],
    },
    {
        label: '考核管理', icon: 'list', children: [
            { key: 'assess', label: '月度考核', icon: 'list' },
            { key: 'assess-appeal', label: '考核申诉', icon: 'edit' },
            { key: 'peer-review', label: '同行评议', icon: 'users' },
        ],
    },
    { key: 'work', label: '工作管理', icon: 'chart' },
];

/**
 * 权限勾选网格的展示项（与原型 MENU_MODULES 一致，18 项）。
 * 「物资管理」一项代表 material/inbound/outbound 三个 key。
 */
export const PERMISSION_OPTIONS = [
    { label: '工作台', keys: ['dashboard'] },
    { label: '用户管理', keys: ['user'] },
    { label: '考勤统计', keys: ['attendance'] },
    { label: '班次管理', keys: ['shift'] },
    { label: '排班管理', keys: ['schedule'] },
    { label: '值班日志', keys: ['dutylog'] },
    { label: '排班日历', keys: ['calendar'] },
    { label: '请假列表', keys: ['leave'] },
    { label: '假勤管理', keys: ['leavebalance'] },
    { label: '房源管理', keys: ['room'] },
    { label: '住宿管理', keys: ['accommodation'] },
    { label: '房卡管理', keys: ['card'] },
    { label: '报修管理', keys: ['repair'] },
    { label: '检查列表', keys: ['safety'] },
    { label: '消防志愿服务', keys: ['volunteer'] },
    { label: '消防培训', keys: ['training'] },
    { label: '物资列表', keys: ['material'] },
    { label: '入库管理', keys: ['inbound'] },
    { label: '出库管理', keys: ['outbound'] },
    { label: '月度考核', keys: ['assess'] },
    { label: '考核申诉', keys: ['assess-appeal'] },
    { label: '同行评议', keys: ['peer-review'] },
    { label: '工作管理', keys: ['work'] },
];

/** 用户的 key 列表 → 勾选项 label 列表（列表页 chips 展示用） */
export function keysToPermissionLabels(keys) {
    return PERMISSION_OPTIONS
        .filter((o) => o.keys.every((k) => keys.includes(k)))
        .map((o) => o.label);
}
