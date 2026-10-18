<template>
  <div class="qt-page">
    <!-- 统计卡片 -->
    <div class="att-stats">
      <div class="att-stat">
        <div class="att-stat-num">{{ stats.expected }}</div>
        <div class="att-stat-label">应到人次</div>
      </div>
      <div class="att-stat">
        <div class="att-stat-num c-signed">{{ stats.actual }}</div>
        <div class="att-stat-label">实到人次</div>
      </div>
      <div class="att-stat">
        <div class="att-stat-num c-late">{{ stats.late }}</div>
        <div class="att-stat-label">迟到</div>
      </div>
      <div class="att-stat">
        <div class="att-stat-num c-early">{{ stats.early }}</div>
        <div class="att-stat-label">早退</div>
      </div>
      <div class="att-stat">
        <div class="att-stat-num c-absent">{{ stats.absent }}</div>
        <div class="att-stat-label">缺卡</div>
      </div>
      <div class="att-stat">
        <div class="att-stat-num c-leave">{{ stats.leave }}</div>
        <div class="att-stat-label">请假</div>
      </div>
    </div>

    <!-- 搜索条 -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">日期范围</span>
        <el-date-picker v-model="query.dateRange" type="daterange" range-separator="至" start-placeholder="开始日期"
          end-placeholder="结束日期" value-format="YYYY-MM-DD" format="YYYY-MM-DD" style="width:260px" />
      </div>
      <div v-if="auth.user?.is_admin" class="qt-filter">
        <span class="qt-filter-label">人员</span>
        <el-select v-model="query.user_id" placeholder="全部" clearable filterable style="width:160px">
          <el-option v-for="u in userOptions" :key="u.id" :label="u.username" :value="u.id" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">状态</span>
        <el-select v-model="query.status" placeholder="全部" clearable style="width:120px">
          <el-option label="正常" value="normal" />
          <el-option label="迟到" value="late" />
          <el-option label="早退" value="early" />
          <el-option label="迟到+早退" value="late_early" />
          <el-option label="缺卡" value="absent" />
          <el-option label="请假" value="leave" />
          <el-option label="未签到" value="pending" />
          <el-option label="未签退" value="not_out" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">姓名</span>
        <el-input v-model.trim="query.keyword" placeholder="请输入" clearable style="width:140px" @keyup.enter="search" />
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button @click="handleExport">
        <QtIcon name="download" :size="13" />&nbsp;导出
      </el-button>
      <el-button style="margin-left:auto;" @click="resetQuery">
        <QtIcon name="refresh" :size="13" />&nbsp;重置
      </el-button>
    </div>

    <!-- 列表 -->
    <div class="qt-table-wrap">
      <el-table :data="filteredRows" v-loading="loading" style="width:100%">
        <el-table-column prop="date" label="日期" min-width="110" />
        <el-table-column prop="username" label="签到人" min-width="110" />
        <el-table-column prop="shift_name" label="班次名称" min-width="130" />
        <el-table-column prop="plan_check_in" label="计划签到" min-width="110" />
        <el-table-column prop="plan_check_out" label="计划签退" min-width="150" />
        <el-table-column prop="check_in_at" label="签到时间" min-width="150" />
        <el-table-column prop="check_out_at" label="签退时间" min-width="150" />
        <el-table-column label="统计" min-width="200">
          <template #default="{ row }">
            <span v-if="row.late_minutes > 0" class="qt-tag qt-tag-warning" style="margin-right:6px;">迟到 {{ row.late_minutes }} 分钟</span>
            <span v-if="row.early_leave_minutes > 0" class="qt-tag qt-tag-warning" style="margin-right:6px;">早退 {{ row.early_leave_minutes }} 分钟</span>
            <span v-if="row.status === 'normal'" class="qt-tag qt-tag-success">正常</span>
            <span v-if="row.status === 'absent'" class="qt-tag qt-tag-danger">缺卡</span>
            <span v-if="row.status === 'leave'" class="qt-tag qt-tag-info">请假</span>
            <span v-if="row.status === 'pending'" class="qt-tag">未签到</span>
            <span v-if="row.status === 'not_out'" class="qt-tag qt-tag-warning">未签退</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" min-width="120">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)" disable-transitions>{{ row.status_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" min-width="160" />
        <el-table-column prop="updated_at" label="修改时间" min-width="160" />
        <template #empty><el-empty description="暂无考勤记录" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page"
          @current-change="(p) => load(p)" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { attendanceApi } from '../api/attendance';
import { useAuthStore } from '../stores/auth';
import http from '../api';
import QtIcon from '../components/QtIcon.vue';

const auth = useAuthStore();

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const userOptions = ref([]);
const stats = reactive({ expected: 0, actual: 0, late: 0, early: 0, absent: 0, leave: 0 });

const today = new Date().toISOString().slice(0, 10);
const weekAgo = new Date(Date.now() - 6 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

const query = reactive({
    dateRange: [weekAgo, today],
    user_id: '',
    status: '',
    keyword: '',
    page: 1,
    pageSize: 10,
});

const filteredRows = computed(() => {
    if (!query.status) return rows.value;
    return rows.value.filter((r) => r.status === query.status);
});

async function loadStats() {
    try {
        const [start_date, end_date] = query.dateRange || [weekAgo, today];
        const params = { start_date, end_date };
        if (auth.user?.is_admin && query.user_id) params.user_id = query.user_id;
        const res = await attendanceApi.stats(params);
        if (res.code === 0) Object.assign(stats, res.data);
    } catch (e) {
        // handled by interceptor
    }
}

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const [start_date, end_date] = query.dateRange || [weekAgo, today];
        const params = {
            start_date,
            end_date,
            status: query.status,
            keyword: query.keyword,
            page,
            pageSize: query.pageSize,
        };
        if (auth.user?.is_admin && query.user_id) params.user_id = query.user_id;

        const res = await attendanceApi.list(params);
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
        await loadStats();
    } finally {
        loading.value = false;
    }
}

function search() {
    load(1);
}

function resetQuery() {
    query.dateRange = [weekAgo, today];
    query.user_id = '';
    query.status = '';
    query.keyword = '';
    load(1);
}

function statusTagType(status) {
    return {
        normal: 'success',
        late: 'warning',
        early: 'warning',
        late_early: 'warning',
        absent: 'danger',
        leave: 'info',
        pending: '',
        not_out: 'warning',
    }[status] || '';
}

async function loadUsers() {
    try {
        const res = await http.get('/users/options');
        const list = (res.code === 0 && res.data) || [];
        // 管理员可查全部人；普通员工至少能选到自己，否则筛选框空着像坏了
        userOptions.value = auth.user?.is_admin
            ? list
            : list.filter((u) => u.id === auth.user?.id);
    } catch (e) {
        // handled by interceptor
    }
}

async function handleExport() {
    try {
        const [start_date, end_date] = query.dateRange || [weekAgo, today];
        const params = { start_date, end_date };
        if (auth.user?.is_admin && query.user_id) params.user_id = query.user_id;
        if (query.keyword) params.keyword = query.keyword;

        const res = await attendanceApi.export(params);
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `考勤表_${start_date}_${end_date}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        ElMessage.success('导出成功');
    } catch (e) {
        ElMessage.error('导出失败，请重试');
    }
}

watch(() => query.dateRange, () => loadStats());
watch(() => query.user_id, () => loadStats());

onMounted(() => {
    load();
    loadUsers();
});
</script>

<style scoped>
.att-stats {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 14px;
    margin-bottom: 16px;
}
.att-stat {
    background: #fff; border: 1px solid var(--border); border-radius: 6px;
    padding: 16px; text-align: center;
}
.att-stat-num {
    font-size: 26px; font-weight: 600; margin-bottom: 6px;
}
.att-stat-label { font-size: 13px; color: var(--text-muted); }
.c-signed { color: var(--success); }
.c-late { color: #e6a23c; }
.c-early { color: #e6a23c; }
.c-absent { color: #f56c6c; }
.c-leave { color: #909399; }
</style>
