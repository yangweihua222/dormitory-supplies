<template>
  <div class="qt-page">
    <!-- 快速入口 -->
    <div class="qe-grid">
      <div v-for="m in quickEntries" :key="m.key" class="qe-card" @click="$router.push(menuPath(m.key))">
        <QtIcon :name="m.icon" :size="22" />
        <span>{{ m.label }}</span>
      </div>
    </div>

    <!-- 签到打卡 + 待办事项 -->
    <div class="dashboard-row" :class="{ 'is-loading': !loaded }">
      <!-- 签到打卡 -->
      <div class="qt-card sign-card">
        <div class="card-title-row">
          <span class="card-title"><QtIcon name="clock" :size="16" /> 签到打卡</span>
          <span class="card-date muted">{{ dashboard.date }} {{ dashboard.weekday }}</span>
        </div>

        <div class="sign-body">
          <div class="sign-ring" :class="{ 'is-out': signStatus === '已签退', 'is-in': signStatus === '已签到' }">
            <div class="ring-time">{{ currentTime }}</div>
          </div>
          <div v-if="dashboard.duty_users?.length" class="duty-panel">
            <div class="duty-label muted">今日值班人员</div>
            <div class="duty-tags">
              <span v-for="u in dashboard.duty_users" :key="u.id" class="duty-tag">{{ u.name }}</span>
            </div>
          </div>
        </div>

        <div class="sign-meta">
          <div class="meta-item">
            <div class="meta-label muted">当前状态</div>
            <div class="meta-value">
              <span class="qt-tag" :class="statusTagClass">{{ signStatus }}</span>
            </div>
          </div>
          <div class="meta-item">
            <div class="meta-label muted">我的班次</div>
            <div class="meta-value">{{ dashboard.sign_info?.shift_name || '—' }}</div>
          </div>
          <div class="meta-item">
            <div class="meta-label muted">计划签到</div>
            <div class="meta-value">{{ dashboard.sign_info?.plan_check_in || '—' }}</div>
          </div>
          <div class="meta-item">
            <div class="meta-label muted">计划签退</div>
            <div class="meta-value">{{ dashboard.sign_info?.plan_check_out || '—' }}</div>
          </div>
        </div>

        <div class="sign-actions">
          <el-button type="success" :disabled="!canSignIn" :plain="signStatus !== '未签到'"
            @click="onSignIn">
            <QtIcon name="check" :size="13" /> {{ signInLabel }}
          </el-button>
          <el-button :disabled="signStatus !== '已签到'" @click="openSignOut">
            <QtIcon name="back" :size="13" /> 签退
          </el-button>
        </div>
      </div>

      <!-- 待办事项 -->
      <div class="qt-card todo-card">
        <div class="card-title-row">
          <span class="card-title"><QtIcon name="list" :size="16" /> 待办事项</span>
          <el-button v-if="todos.list?.length" type="success" size="small" plain @click="completeAll">
            <QtIcon name="check" :size="13" /> 一键完成
          </el-button>
        </div>

        <div class="todo-stats">
          <div class="todo-stat">
            <div class="todo-stat-num">{{ todos.pending || 0 }}</div>
            <div class="todo-stat-label">今日待办</div>
          </div>
          <div class="todo-stat">
            <div class="todo-stat-num">{{ todos.done || 0 }}</div>
            <div class="todo-stat-label">已完成待办</div>
          </div>
          <div class="todo-stat">
            <div class="todo-stat-num">{{ todos.month_handover || 0 }}</div>
            <div class="todo-stat-label">当月交接</div>
          </div>
          <div class="todo-stat">
            <div class="todo-stat-num c-rate">{{ todos.rate || 0 }}%</div>
            <div class="todo-stat-label">完成率</div>
          </div>
        </div>

        <div class="todo-list">
          <div v-if="!todos.list?.length" class="muted empty-tips">暂无待办事项</div>
          <div v-for="t in todos.list" :key="t.id" class="todo-row" :class="{ 'is-done': t.status !== 'pending' }">
            <div class="todo-main">
              <span class="todo-tag" :class="todoTagClass(t.type)">{{ t.type_label }}</span>
              <span class="todo-title" :class="{ 'is-link': t.link }" :title="t.link ? '点击查看来源记录' : t.title"
                @click="onTodoClick(t)">{{ t.title }}</span>
            </div>
            <div class="todo-side">
              <span class="todo-assignee muted">{{ formatAssignees(t.assignees) }}</span>
              <el-button v-if="t.status === 'pending'" type="success" size="small" @click="completeTodo(t.id)">完成</el-button>
              <span v-else class="todo-state-done">已完成 {{ t.completed_at || '' }}</span>
            </div>
          </div>
        </div>

        <div v-if="todos.pages > 1" class="pagination">
          <div class="pg-list">
            <el-button size="small" :disabled="todos.page <= 1" @click="changePage(todos.page - 1)">‹ 上一页</el-button>
            <el-button
              v-for="p in todos.pages" :key="p"
              size="small" :type="todos.page === p ? 'primary' : ''"
              @click="changePage(p)"
            >{{ p }}</el-button>
            <el-button size="small" :disabled="todos.page >= todos.pages" @click="changePage(todos.page + 1)">下一页 ›</el-button>
          </div>
          <span class="pg-info">共 {{ todos.total }} 条</span>
        </div>
      </div>
    </div>

    <!-- 签退弹窗 -->
    <el-dialog v-model="signOutVisible" title="签退确认" width="460px" :close-on-click-modal="false">
      <div class="signout-tip">请如实确认房卡与钥匙归还情况</div>
      <div class="signout-items">
        <div class="signout-item">
          <div class="signout-item-left">
            <QtIcon name="card" :size="24" />
            <div>
              <div class="signout-item-label">房卡</div>
              <div class="signout-item-desc">应归还数量：{{ signOutForm.returned_cards }}</div>
            </div>
          </div>
          <el-input-number v-model="signOutForm.returned_cards" :min="0" :max="99" size="small" />
        </div>
        <div class="signout-item">
          <div class="signout-item-left">
            <QtIcon name="key" :size="24" />
            <div>
              <div class="signout-item-label">钥匙</div>
              <div class="signout-item-desc">应归还数量：{{ signOutForm.returned_keys }}</div>
            </div>
          </div>
          <el-input-number v-model="signOutForm.returned_keys" :min="0" :max="99" size="small" />
        </div>
      </div>
      <div class="signout-time">本次签退时间：{{ currentTime }}</div>
      <div class="signout-confirm">
        <el-checkbox v-model="signOutForm.is_confirmed" size="large">
          已完成以上物品交接核对
        </el-checkbox>
      </div>
      <template #footer>
        <el-button @click="signOutVisible = false">取消</el-button>
        <el-button type="primary" :disabled="!signOutForm.is_confirmed" @click="onSignOut">确认签退</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useAuthStore } from '../stores/auth';
import { menuPath } from '../router';
import QtIcon from '../components/QtIcon.vue';
import http from '../api';

const auth = useAuthStore();
const router = useRouter();

/** 点待办标题：能定位到来源记录的直接跳过去（当前值班日志交接支持） */
function onTodoClick(t) {
    if (!t.link) return;
    const key = t.link.split('?')[0].replace('/', '');
    if (!auth.hasMenu(key === 'dutylog' ? 'dutylog' : key)) {
        ElMessage.warning('你没有该模块的访问权限');
        return;
    }
    router.push(t.link);
}

const dashboard = ref({
    date: '',
    weekday: '',
    quick_entries: [],
    sign_info: {},
    duty_users: [],
    todos: { list: [], pending: 0, done: 0, total: 0, rate: 0, month_handover: 0 },
});

const todos = computed(() => dashboard.value.todos || { list: [], pending: 0, done: 0, total: 0, rate: 0, month_handover: 0, page: 1, pages: 1, size: 10 });
const quickEntries = computed(() => dashboard.value.quick_entries || []);

const currentTime = ref('');
let timer = null;
function updateTime() {
    const now = new Date();
    currentTime.value = now.toTimeString().slice(0, 8);
}

const signStatus = computed(() => {
    const info = dashboard.value.sign_info || {};
    if (info.status) return info.status;
    if (info.check_out_at && info.check_out_at !== '—') return '已签退';
    if (info.check_in_at && info.check_in_at !== '—') return '已签到';
    return '未签到';
});

/** 无排班 / 请假状态下禁止签到 */
const canSignIn = computed(() => signStatus.value === '未签到');
const signInLabel = computed(() => {
    const s = signStatus.value;
    if (s === '已签到') return '已签到';
    if (s === '请假') return '请假中';
    if (s === '今日无排班') return '今日无排班';
    return '签到';
});

const statusTagClass = computed(() => {
    const s = signStatus.value;
    if (s === '已签退' || s === '已签到') return 'qt-tag-success';
    if (s === '请假' || s === '今日无排班') return 'qt-tag-info';
    return 'qt-tag-warning';
});

function todoTagClass(type) {
    if (type === 'handover_overdue') return 'tag-danger';
    if (type === 'scrap') return 'tag-danger';
    if (type === 'qty_mismatch') return 'tag-warning';
    if (type === 'handover') return 'tag-info';
    return 'tag-default';
}

function formatAssignees(list) {
    if (!list || !list.length) return '';
    return list.map(a => a.name || a.username).join('、');
}

async function loadDashboard() {
    try {
        const res = await http.get('/dashboard');
        if (res.code === 0) {
            dashboard.value = res.data;
            if (!dashboard.value.todos.page) dashboard.value.todos.page = 1;
            if (!dashboard.value.todos.pages) dashboard.value.todos.pages = 1;
        }
    } catch (e) {
        // error handled by interceptor
    }
}

/**
 * 只刷新签到信息（签到/签退成功后调用）：
 * 不整页 loadDashboard，避免待办列表被重置、签到卡片闪烁
 */
async function refreshSignInfo() {
    try {
        const res = await http.get('/attendance/today');
        if (res.code === 0) {
            const d = res.data;
            let status = '未签到';
            if (d.leave) status = '请假';
            else if (!d.shift_name) status = '今日无排班';
            else if (d.check_in_at && d.check_out_at) status = '已签退';
            else if (d.check_in_at) status = '已签到';
            dashboard.value.sign_info = {
                ...dashboard.value.sign_info,
                shift_name: d.shift_name || '—',
                plan_check_in: d.plan_check_in || '—',
                plan_check_out: d.plan_check_out || '—',
                check_in_at: d.check_in_at || '—',
                check_out_at: d.check_out_at || '—',
                returned_cards: d.returned_cards ?? 0,
                returned_keys: d.returned_keys ?? 0,
                status,
            };
        }
    } catch (e) {
        // error handled by interceptor
    }
}

/** 待办每页条数：由卡片可用高度自适应（行数太少时不低于 3 条，避免小窗口下挤成一条） */
const todoSize = ref(10);
const ROW_H = 46;   // .todo-row 实际行高
const ROW_GAP = 8;  // .todo-list 行间距
const PG_RESERVE = 36; // 底部分页条（24px + 12px margin）预留位，避免出现内部滚动条

/** 量出卡片能给列表用的高度，换算成能放下的条数 */
function calcTodoCapacity() {
    const list = document.querySelector('.todo-list');
    if (!list) return todoSize.value;
    const avail = list.clientHeight - PG_RESERVE;
    if (avail < ROW_H) return 3;
    const n = Math.floor((avail + ROW_GAP) / (ROW_H + ROW_GAP));
    return Math.max(3, Math.min(10, n));
}

async function loadTodos(page = 1, size = todoSize.value) {
    try {
        const res = await http.get(`/todos?page=${page}&size=${size}`);
        if (res.code === 0) {
            dashboard.value.todos = { ...dashboard.value.todos, ...res.data };
        }
    } catch (e) {
        // error handled by interceptor
    }
}

function changePage(page) {
    loadTodos(page);
}

async function onSignIn() {
    try {
        const res = await http.post('/attendance/sign-in');
        if (res.code === 0) {
            ElMessage.success(res.message || '签到成功');
            await refreshSignInfo();
        } else {
            // 业务失败（无排班/请假/已签到等）：后端以 code+message 返回，必须给出提示
            ElMessage.warning(res.message || '签到失败');
        }
    } catch (e) {
        // handled by interceptor
    }
}

const signOutVisible = ref(false);
const signOutForm = reactive({ returned_cards: 0, returned_keys: 0, is_confirmed: false });

function openSignOut() {
    signOutForm.returned_cards = dashboard.value.sign_info?.returned_cards || 0;
    signOutForm.returned_keys = dashboard.value.sign_info?.returned_keys || 0;
    signOutForm.is_confirmed = false;
    signOutVisible.value = true;
}

async function onSignOut() {
    try {
        const res = await http.post('/attendance/sign-out', signOutForm);
        if (res.code === 0) {
            ElMessage.success(res.message || '签退成功');
            signOutVisible.value = false;
            await refreshSignInfo();
        } else {
            ElMessage.warning(res.message || '签退失败');
        }
    } catch (e) {
        // handled by interceptor
    }
}

async function completeTodo(id) {
    try {
        const res = await http.post(`/todos/${id}/complete`);
        if (res.code === 0) {
            ElMessage.success('完成成功');
            await loadTodos(todos.value.page || 1);
        }
    } catch (e) {
        // handled by interceptor
    }
}

async function completeAll() {
    if (!todos.value.list?.length) return;
    const ids = todos.value.list.filter(t => t.status === 'pending').map(t => t.id);
    if (!ids.length) return;
    try {
        const res = await http.post('/todos/complete-all', { ids });
        if (res.code === 0) {
            ElMessage.success('一键完成成功');
            await loadTodos(todos.value.page || 1);
        }
    } catch (e) {
        // handled by interceptor
    }
}

/** 首屏数据未就绪时隐藏签到/待办区（保留占位高度），避免"先显示未签到、再跳成已签到"的闪动 */
const loaded = ref(false);

let resizeTimer = null;
function onResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(async () => {
        const size = calcTodoCapacity();
        if (size !== todoSize.value) {
            todoSize.value = size;
            await loadTodos(1, size);
        }
    }, 200);
}

onMounted(async () => {
    updateTime();
    timer = setInterval(updateTime, 1000);
    await loadDashboard();
    await nextTick();
    // 首屏数据到位后再量一次可用高度；容量与默认 10 一致时跳过二次请求，避免列表重复渲染闪动
    const size = calcTodoCapacity();
    todoSize.value = size;
    if (size !== (dashboard.value.todos?.size ?? 10) || (dashboard.value.todos?.list?.length ?? 0) === 0) {
        await loadTodos(1, size);
    }
    loaded.value = true;
    window.addEventListener('resize', onResize);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
    window.removeEventListener('resize', onResize);
});
</script>

<style scoped>
.qt-page {
    display: flex; flex-direction: column;
    height: 100%;
}

/* 快速入口：原型 7 列 */
.qe-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
    margin-bottom: 14px;
    min-height: 82px; /* 数据到位前先占位，避免签到卡被顶下来产生位移 */
}
.qe-card {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 8px;
    padding: 16px 6px;
    border: 1px solid var(--border);
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    transition: all .15s;
    background: #fff;
    color: var(--primary);
    min-height: 82px;
}
.qe-card:hover { border-color: var(--primary); background: var(--primary-bg); }
.qe-card span { color: var(--text); }

/* 左右两卡 */
.dashboard-row {
    display: flex;
    gap: 14px;
    flex: 1;
    min-height: 400px; /* 保证待办卡片始终放得下最少 3 条，窗口过矮时由页面滚动 */
}
.qt-card {
    background: #fff; border: 1px solid var(--border); border-radius: 6px;
    padding: 14px 16px 16px;
    display: flex; flex-direction: column;
    min-height: 0;
    flex: 1;
}
.card-title-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
    flex-shrink: 0;
}
.card-title {
    display: flex; align-items: center; gap: 6px;
    font-size: 15px; font-weight: 600; color: var(--text);
}
.card-date { font-size: 13px; }

/* 签到卡 */
.sign-card {
    justify-content: space-between;
}
.sign-body {
    flex: 1;
    display: flex; align-items: center; justify-content: center;
    gap: 40px;
    min-height: 0;
    padding: 10px 0;
}
.sign-ring {
    width: 220px; height: 220px; border-radius: 50%;
    background: linear-gradient(135deg, #67c23a 0%, #5daf34 100%);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 8px 24px rgba(103, 194, 58, 0.32);
    color: #fff;
    flex-shrink: 0;
    transition: transform .2s, box-shadow .2s;
}
.sign-ring:hover { transform: scale(1.03); }
.sign-ring.is-out {
    background: linear-gradient(135deg, #67c23a 0%, #5daf34 100%);
}
.sign-ring.is-in {
    background: linear-gradient(135deg, #67c23a 0%, #5daf34 100%);
}
.ring-time { font-size: 36px; font-weight: 700; letter-spacing: 1px; }

.duty-panel {
    display: flex; flex-direction: column; gap: 10px;
    min-width: 80px;
}
.duty-label { font-size: 13px; }
.duty-tags { display: flex; flex-direction: column; gap: 8px; }
.duty-tag {
    display: inline-flex; align-items: center; justify-content: center;
    height: 28px; padding: 0 14px;
    border-radius: 14px;
    background: #ecf5ff; color: var(--primary);
    font-size: 13px;
}

.sign-meta {
    display: flex; align-items: flex-start; justify-content: center;
    gap: 36px;
    margin-bottom: 16px;
    flex-shrink: 0;
}
.meta-item { text-align: center; }
.meta-label { font-size: 12px; margin-bottom: 6px; }
.meta-value {
    font-size: 14px; font-weight: 500; color: var(--text);
    white-space: nowrap;
}
.sign-actions {
    display: flex; gap: 10px; justify-content: center;
    flex-shrink: 0;
}

/* 待办卡 */
.todo-stats {
    display: flex; align-items: center;
    border-bottom: 1px solid var(--border);
    margin-bottom: 12px;
    flex-shrink: 0;
}
.todo-stat {
    flex: 1; text-align: center;
    padding: 10px 0 12px;
}
.todo-stat + .todo-stat { border-left: 1px solid var(--border); }
.todo-stat-num { font-size: 22px; font-weight: 600; color: var(--text); line-height: 1.2; }
.todo-stat-num.c-rate { color: var(--danger); }
.todo-stat-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; }

.todo-list {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    display: flex; flex-direction: column;
    gap: 8px;
}
.empty-tips { padding: 40px 0; text-align: center; font-size: 13px; }
.todo-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 4px;
    border: 1px solid var(--border-light);
    background: #fafbfc;
    flex-shrink: 0;
}
.todo-row.is-done { background: #f4f4f5; border-color: #e9e9eb; }
.todo-main {
    display: flex; align-items: center; gap: 10px;
    flex: 1; min-width: 0;
}
.todo-tag {
    display: inline-flex; align-items: center;
    height: 22px; padding: 0 8px; border-radius: 3px;
    font-size: 12px; color: #fff; flex-shrink: 0;
}
.todo-tag.tag-danger { background: #f56c6c; }
.todo-tag.tag-warning { background: #e6a23c; }
.todo-tag.tag-info { background: #409eff; }
.todo-tag.tag-default { background: #909399; }
.todo-title {
    font-size: 13px; color: var(--text);
    flex: 1; min-width: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.todo-title.is-link {
    cursor: pointer;
    color: var(--primary);
}
.todo-title.is-link:hover {
    text-decoration: underline;
}
.todo-side {
    display: flex; align-items: center; gap: 10px;
    flex-shrink: 0;
}
.todo-assignee { font-size: 12px; white-space: nowrap; }
.todo-state-done {
    display: inline-flex; align-items: center;
    height: 24px; padding: 0 10px;
    background: #f4f4f5; color: #909399;
    border: 1px solid #e9e9eb; border-radius: 3px;
    font-size: 12px;
}

/* 分页 */
.pagination {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 12px;
    flex-shrink: 0;
}
.pg-list { display: flex; gap: 6px; }
.pg-info { font-size: 12px; color: var(--text-muted); }

/* 签退弹窗 */
.signout-tip {
    background: #ecf5ff; color: var(--primary);
    padding: 8px 12px; border-radius: 4px; font-size: 13px; margin-bottom: 14px;
}
.signout-items { display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px; }
.signout-item { display: flex; align-items: center; justify-content: space-between; }
.signout-item-left { display: flex; align-items: center; gap: 12px; }
.signout-item-label { font-weight: 500; }
.signout-item-desc { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.signout-time { text-align: center; color: var(--text-muted); font-size: 13px; margin-bottom: 16px; }
.signout-confirm { display: flex; justify-content: center; }
</style>
