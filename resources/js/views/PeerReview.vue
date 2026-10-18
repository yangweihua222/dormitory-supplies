<template>
  <div class="qt-page">
    <!-- ========== 列表视图 ========== -->
    <template v-if="mode === 'list'">
      <div class="qt-filter-bar">
        <div class="qt-filter">
          <span class="qt-filter-label">被评议人</span>
          <el-input v-model.trim="query.name" placeholder="请输入" clearable style="width:150px" @keyup.enter="search" />
        </div>
        <div class="qt-filter">
          <span class="qt-filter-label">考核月份</span>
          <el-date-picker v-model="query.month" type="month" value-format="YYYY-MM" placeholder="请选择" style="width:140px" />
        </div>
        <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
        <el-button type="primary" style="margin-left:auto;" @click="openCreate"><QtIcon name="plus" :size="13" />&nbsp;发起评议</el-button>
      </div>
      <div class="scope-hint">同行评议为员工之间的相互评价（5 个维度 × 0–10 分，满分 50 分，独立于月度考核 100 分制）。</div>

      <div class="qt-table-wrap">
        <el-table :data="rows" v-loading="loading" style="width:100%">
          <el-table-column type="index" label="序号" width="60" />
          <el-table-column prop="reviewer" label="评议人" min-width="90" />
          <el-table-column prop="reviewee" label="被评议人" min-width="90" />
          <el-table-column prop="month" label="考核月份" min-width="100" />
          <el-table-column v-for="(dim, i) in dims" :key="dim.short" :label="dim.short" min-width="80" align="center">
            <template #header>
              <span :title="dim.content">{{ dim.short }}</span>
            </template>
            <template #default="{ row }">{{ row.scores[i] ?? '—' }}</template>
          </el-table-column>
          <el-table-column label="总分" min-width="90" align="center">
            <template #default="{ row }"><b>{{ row.total }}</b> / 50</template>
          </el-table-column>
          <el-table-column prop="remark" label="备注" min-width="150" show-overflow-tooltip>
            <template #default="{ row }"><span>{{ row.remark || '—' }}</span></template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" min-width="140" />
          <el-table-column prop="updated_at" label="修改时间" min-width="140" />
          <el-table-column label="操作" width="130" fixed="right">
            <template #default="{ row }">
              <template v-if="row.can_edit">
                <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
                <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
              </template>
              <span v-else style="color:#c0c4cc;font-size:12px;">—</span>
            </template>
          </el-table-column>
          <template #empty><el-empty description="暂无同行评议记录" /></template>
        </el-table>
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
          <el-pagination background layout="total, prev, pager, next" :total="total"
            :page-size="query.pageSize" :current-page="query.page" @current-change="(p) => load(p)" />
        </div>
      </div>
    </template>

    <!-- ========== 发起 / 修改 评议表单 ========== -->
    <template v-else>
      <div class="qt-filter-bar">
        <el-button @click="mode = 'list'"><QtIcon name="back" :size="13" />&nbsp;返回列表</el-button>
        <span style="font-size:15px;font-weight:600;">{{ formMode === 'edit' ? '修改评议' : '发起评议' }}</span>
      </div>

      <div class="form-card">
        <div class="form-card-title"><QtIcon name="users" :size="15" /> 评议信息</div>
        <div class="info-grid">
          <div class="info-item">
            <div class="info-label">评议人</div>
            <div class="info-value">{{ auth.user?.username }}</div>
          </div>
          <div class="info-item">
            <div class="info-label">被评议人</div>
            <el-select v-model="form.reviewee_id" placeholder="请选择" style="width:100%">
              <el-option v-for="u in candidates" :key="u.id" :label="u.username" :value="u.id" />
            </el-select>
          </div>
          <div class="info-item">
            <div class="info-label">考核月份</div>
            <el-date-picker v-model="form.month" type="month" value-format="YYYY-MM" placeholder="请选择" style="width:100%" />
          </div>
        </div>
      </div>

      <div class="form-card">
        <div class="form-card-title"><QtIcon name="list" :size="15" /> 评分明细（每个维度 0–10 分）</div>
        <table class="score-table">
          <thead><tr><th style="min-width:150px;">评议维度</th><th style="min-width:260px;">评议说明</th><th style="min-width:130px;">评分标准</th><th>评分</th></tr></thead>
          <tbody>
            <tr v-for="(dim, i) in dims" :key="dim.short">
              <td>
                <div style="font-weight:500;">{{ dim.short }}</div>
                <div style="font-size:12px;color:#909399;font-weight:400;line-height:1.7;margin-top:2px;">{{ dim.content }}</div>
              </td>
              <td style="color:#606266;font-size:12px;line-height:1.8;">{{ dim.desc }}</td>
              <td style="color:#909399;font-size:12px;">{{ dim.standard }}</td>
              <td align="center">
                <el-input-number v-model="form.scores[i]" :min="0" :max="10" :step="1" step-strictly style="width:100px" />
              </td>
            </tr>
            <tr class="sum-row">
              <td>总分</td>
              <td colspan="2">满分 50 分（自动合计）</td>
              <td align="center"><b style="font-size:15px;">{{ formTotal }}</b> / 50</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="form-card">
        <div class="form-card-title"><QtIcon name="edit" :size="15" /> 备注</div>
        <el-input v-model.trim="form.remark" type="textarea" :rows="3" placeholder="选填" />
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px;">
        <el-button type="primary" :loading="saving" @click="onSave">
          <QtIcon name="check" :size="13" />&nbsp;{{ formMode === 'edit' ? '保存修改' : '保存评议' }}
        </el-button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import http from '../api';
import { peerApi } from '../api/assess';
import { useAuthStore } from '../stores/auth';
import QtIcon from '../components/QtIcon.vue';

const auth = useAuthStore();
const mode = ref('list');
const formMode = ref('create');
const rows = ref([]);
const total0 = ref(0);
const loading = ref(false);
const saving = ref(false);
const dims = ref([]);
const users = ref([]);

const query = reactive({ name: '', month: '', page: 1, pageSize: 10 });
const form = reactive({ id: null, reviewee_id: null, month: '', scores: [8, 8, 8, 8, 8], remark: '' });

const candidates = computed(() => users.value.filter((u) => u.id !== auth.user?.id));
const total = computed(() => form.scores.reduce((s, v) => s + (Number(v) || 0), 0));

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await peerApi.list(query);
        rows.value = res.data.list || [];
        total0.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}
function search() { load(1); }

function openCreate() {
    formMode.value = 'create';
    Object.assign(form, {
        id: null, reviewee_id: null,
        month: new Date().toISOString().slice(0, 7),
        scores: [8, 8, 8, 8, 8], remark: '',
    });
    mode.value = 'form';
}
function openEdit(row) {
    formMode.value = 'edit';
    Object.assign(form, {
        id: row.id, reviewee_id: row.reviewee_id, month: row.month,
        scores: dims.value.map((d, i) => row.scores[i] ?? 0), remark: row.remark || '',
    });
    mode.value = 'form';
}

async function onSave() {
    if (!form.reviewee_id) { ElMessage.warning('请选择被评议人'); return; }
    if (!form.month) { ElMessage.warning('请选择考核月份'); return; }
    saving.value = true;
    try {
        const payload = { reviewee_id: form.reviewee_id, month: form.month, scores: form.scores, remark: form.remark || undefined };
        if (formMode.value === 'edit') {
            await peerApi.update(form.id, payload);
            ElMessage.success('已保存');
        } else {
            await peerApi.store(payload);
            ElMessage.success('已保存评议');
        }
        mode.value = 'list';
        load(query.page);
    } catch (e) {
        ElMessage.error(e?.data?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除「${row.reviewer} → ${row.reviewee} / ${row.month}」的这条评议吗？`, '提示', { type: 'warning' });
    await peerApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(async () => {
    const [opt, us] = await Promise.all([peerApi.options(), http.get('/users/options')]);
    dims.value = opt.data.dims || [];
    users.value = us.data || [];
    load();
});
</script>

<style scoped>
.scope-hint {
    font-size: 12px;
    color: var(--text-muted, #909399);
    padding: 0 2px 8px;
}
.form-card {
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 14px;
}
.form-card-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 14px;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px 20px;
}
.info-label {
    font-size: 12px;
    color: #909399;
    margin-bottom: 6px;
}
.info-value {
    font-size: 14px;
    color: #303133;
    min-height: 32px;
    display: flex;
    align-items: center;
}
.score-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.score-table th, .score-table td {
    border: 1px solid #ebeef5;
    padding: 8px 10px;
    text-align: left;
    vertical-align: middle;
}
.score-table th {
    background: #fafbfc;
    font-weight: 600;
    color: #606266;
}
.sum-row {
    background: #fafbfc;
    font-weight: 500;
}
</style>
