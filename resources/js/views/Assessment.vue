<template>
  <div class="qt-page">
    <!-- ========== 列表视图 ========== -->
    <template v-if="mode === 'list'">
      <div class="qt-filter-bar">
        <div class="qt-filter">
          <span class="qt-filter-label">员工姓名</span>
          <el-input v-model.trim="query.name" placeholder="请输入" clearable style="width:150px" @keyup.enter="search" />
        </div>
        <div class="qt-filter">
          <span class="qt-filter-label">考核月份</span>
          <el-date-picker v-model="query.month" type="month" value-format="YYYY-MM" placeholder="请选择" style="width:140px" />
        </div>
        <div class="qt-filter">
          <span class="qt-filter-label">考核等级</span>
          <el-select v-model="query.grade" placeholder="全部" clearable style="width:130px">
            <el-option v-for="g in grades" :key="g" :label="g" :value="g" />
          </el-select>
        </div>
        <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
        <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出 Excel</el-button>
        <el-button v-if="auth.isAdmin" type="primary" style="margin-left:auto;" @click="openCreate">
          <QtIcon name="plus" :size="13" />&nbsp;创建考核
        </el-button>
      </div>
      <div v-if="!auth.isAdmin" class="scope-hint">数据范围：仅展示本人考核记录；对考核结果有异议请联系管理员发起申诉。</div>

      <div class="qt-table-wrap">
        <el-table :data="rows" v-loading="loading" style="width:100%">
          <el-table-column type="index" label="序号" width="60" />
          <el-table-column prop="name" label="员工姓名" min-width="100" />
          <el-table-column prop="post" label="所属岗位" min-width="140" />
          <el-table-column prop="month" label="考核月份" min-width="100" />
          <el-table-column label="最终得分" min-width="90" align="center">
            <template #default="{ row }"><b>{{ row.total }}</b></template>
          </el-table-column>
          <el-table-column label="考核等级" min-width="100" align="center">
            <template #default="{ row }"><el-tag :type="gradeTagType(row.grade)" effect="light">{{ row.grade }}</el-tag></template>
          </el-table-column>
          <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip>
            <template #default="{ row }"><span>{{ row.remark || '—' }}</span></template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" min-width="140" />
          <el-table-column prop="updated_at" label="修改时间" min-width="140" />
          <el-table-column label="操作" :width="auth.isAdmin ? 190 : 90" fixed="right">
            <template #default="{ row }">
              <a class="qt-link" @click="openDetail(row)"><QtIcon name="eye" :size="13" /> 详情</a>
              <template v-if="auth.isAdmin">
                <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 修改</a>
                <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
              </template>
            </template>
          </el-table-column>
          <template #empty><el-empty description="暂无考核记录" /></template>
        </el-table>
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
          <el-pagination background layout="total, prev, pager, next" :total="total"
            :page-size="query.pageSize" :current-page="query.page" @current-change="(p) => load(p)" />
        </div>
      </div>
    </template>

    <!-- ========== 创建 / 修改 / 详情 共用（月度考核评分表） ========== -->
    <template v-else>
      <div class="qt-filter-bar">
        <el-button @click="backToList"><QtIcon name="back" :size="13" />&nbsp;返回列表</el-button>
        <span style="font-size:15px;font-weight:600;">{{ formMode === 'create' ? '创建考核' : (formMode === 'edit' ? '修改考核' : '考核详情') }}</span>
      </div>

      <!-- 员工信息 -->
      <div class="form-card">
        <div class="form-card-title"><QtIcon name="user" :size="15" /> 员工信息</div>
        <div class="info-grid">
          <div class="info-item">
            <div class="info-label">员工姓名</div>
            <el-select v-if="formMode === 'create'" v-model="form.user_id" placeholder="请选择" style="width:100%" @change="onUserChange">
              <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
            </el-select>
            <div v-else class="info-value">{{ form.name }}</div>
          </div>
          <div class="info-item">
            <div class="info-label">所属岗位</div>
            <div class="info-value">{{ form.post || '—' }}</div>
          </div>
          <div class="info-item">
            <div class="info-label">员工编号</div>
            <div class="info-value">{{ form.job_no || '—' }}</div>
          </div>
          <div class="info-item">
            <div class="info-label">直属主管</div>
            <el-select v-if="editable" v-model="form.supervisor_id" placeholder="请选择" clearable style="width:100%">
              <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
            </el-select>
            <div v-else class="info-value">{{ form.supervisor || '—' }}</div>
          </div>
          <div class="info-item">
            <div class="info-label">考核月份</div>
            <el-date-picker v-if="formMode === 'create'" v-model="form.month" type="month" value-format="YYYY-MM"
              placeholder="请选择" style="width:100%" />
            <div v-else class="info-value">{{ form.month }}</div>
          </div>
          <div class="info-item">
            <div class="info-label">考核日期</div>
            <el-date-picker v-if="editable" v-model="form.assess_date" type="date" value-format="YYYY-MM-DD"
              placeholder="请选择" style="width:100%" />
            <div v-else class="info-value">{{ form.assess_date || '—' }}</div>
          </div>
        </div>
      </div>

      <!-- 评分明细 -->
      <div class="form-card">
        <div class="form-card-title"><QtIcon name="list" :size="15" /> 评分明细（最终得分 = 标准分值 − 违规扣分 + 奖励加分）</div>
        <table class="score-table">
          <thead>
            <tr><th style="min-width:180px;">考核模块</th><th>标准分值</th><th>违规扣分</th><th>奖励加分</th><th>最终得分</th><th style="min-width:200px;">扣分说明</th></tr>
          </thead>
          <tbody>
            <tr v-for="(m, i) in dict.modules" :key="m.name">
              <td>
                <div style="font-weight:500;">{{ m.name }}</div>
                <el-popover placement="right" :width="380" trigger="click">
                  <template #reference>
                    <a class="qt-link" style="font-size:12px;">参考细则（点击展开）</a>
                  </template>
                  <div class="rule-pop">
                    <div v-for="(r, ri) in rulesOf(m.name)" :key="ri" class="rule-line">
                      · <b>{{ r.item }}</b>：{{ r.rule }}<span v-if="r.remark">（{{ r.remark }}）</span>
                    </div>
                    <div class="rule-line" style="margin-top:6px;">
                      · <b>加分项参考</b>：{{ dict.bonuses.map(b => b.item + ' ' + b.score).join('；') }}
                    </div>
                  </div>
                </el-popover>
              </td>
              <td align="center">{{ m.std }}</td>
              <td align="center">
                <el-input-number v-if="editable" v-model="form.items[i].ded" :min="0" :max="999" :step="0.5" size="small" style="width:100px" />
                <span v-else>{{ form.items[i].ded }}</span>
              </td>
              <td align="center">
                <el-input-number v-if="editable" v-model="form.items[i].bon" :min="0" :max="999" :step="0.5" size="small" style="width:100px" />
                <span v-else>{{ form.items[i].bon }}</span>
              </td>
              <td align="center"><b>{{ moduleScore(i) }}</b></td>
              <td>
                <el-input v-if="editable" v-model.trim="form.items[i].note" type="textarea" :rows="1" autosize
                  placeholder="扣分说明：对应细则项目与次数" />
                <span v-else>{{ form.items[i].note || '—' }}</span>
              </td>
            </tr>
            <tr class="sum-row">
              <td>合计</td>
              <td align="center">{{ totalStd }}</td>
              <td align="center">-{{ totalDed }}</td>
              <td align="center">+{{ totalBon }}</td>
              <td align="center"><b style="font-size:15px;">{{ formTotal }}</b></td>
              <td>考核等级：<el-tag :type="gradeTagType(currentGrade)" effect="light">{{ currentGrade }}</el-tag></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 等级判定 -->
      <div class="form-card">
        <div class="form-card-title"><QtIcon name="info" :size="15" /> 考核等级判定</div>
        <table class="score-table">
          <thead><tr><th>等级</th><th>分数区间</th><th>考核结果</th></tr></thead>
          <tbody>
            <tr v-for="g in dict.grade_rules" :key="g.grade" :class="{ 'grade-hit': g.grade === currentGrade }">
              <td><el-tag :type="gradeTagType(g.grade)" effect="light">{{ g.grade }}</el-tag></td>
              <td>{{ g.range }}</td>
              <td style="color:#606266;">{{ g.result }}</td>
            </tr>
          </tbody>
        </table>
        <div class="scope-hint" style="padding:8px 2px 0;">特殊规则：全天旷工当月直接不合格；脱岗、空岗为红线问题；同一问题整改优先、不叠加扣分；对结果有异议可书面申诉复核。</div>
      </div>

      <!-- 备注 -->
      <div class="form-card">
        <div class="form-card-title"><QtIcon name="edit" :size="15" /> 备注</div>
        <el-input v-if="editable" v-model.trim="form.remark" type="textarea" :rows="2" placeholder="选填" />
        <div v-else class="info-value">{{ form.remark || '—' }}</div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px;">
        <el-button v-if="editable" type="primary" :loading="saving" @click="onSave">
          <QtIcon name="check" :size="13" />&nbsp;{{ formMode === 'create' ? '保存并创建' : '保存修改' }}
        </el-button>
        <el-button v-else-if="auth.isAdmin" type="primary" @click="formMode = 'edit'"><QtIcon name="edit" :size="13" />&nbsp;修改</el-button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import http from '../api';
import { assessmentApi } from '../api/assess';
import { useAuthStore } from '../stores/auth';
import QtIcon from '../components/QtIcon.vue';

const auth = useAuthStore();
const grades = ['优秀', '合格', '基本合格', '不合格'];

const mode = ref('list');           // list | form
const formMode = ref('create');     // create | edit | detail
const editable = computed(() => formMode.value !== 'detail');

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const saving = ref(false);
const users = ref([]);
const dict = reactive({ modules: [], deductions: [], bonuses: [], grade_rules: [] });

const query = reactive({ name: '', month: '', grade: '', page: 1, pageSize: 10 });

const blankItems = () => dict.modules.map(() => ({ ded: 0, bon: 0, note: '' }));
const form = reactive({
    id: null, user_id: null, name: '', post: '', job_no: '',
    supervisor_id: null, supervisor: '', month: '', assess_date: '',
    items: [], remark: '',
});

function gradeTagType(g) {
    return { '优秀': 'success', '合格': 'primary', '基本合格': 'warning', '不合格': 'danger' }[g] || 'info';
}
function gradeOf(t) {
    if (t >= 85) return '优秀';
    if (t >= 71) return '合格';
    if (t >= 61) return '基本合格';
    return '不合格';
}
function rulesOf(moduleName) {
    return dict.deductions.filter((d) => d.module === moduleName);
}

function moduleScore(i) {
    const it = form.items[i] || { ded: 0, bon: 0 };
    const std = dict.modules[i]?.std ?? 0;
    return Math.round((std - (Number(it.ded) || 0) + (Number(it.bon) || 0)) * 10) / 10;
}
const totalStd = computed(() => dict.modules.reduce((s, m) => s + m.std, 0));
const totalDed = computed(() => Math.round(form.items.reduce((s, it) => s + (Number(it.ded) || 0), 0) * 10) / 10);
const totalBon = computed(() => Math.round(form.items.reduce((s, it) => s + (Number(it.bon) || 0), 0) * 10) / 10);
const formTotal = computed(() => Math.round(form.items.reduce((s, it, i) => s + moduleScore(i), 0) * 10) / 10);
const currentGrade = computed(() => gradeOf(formTotal.value));

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await assessmentApi.list(query);
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}
function search() { load(1); }

async function onExport() {
    try {
        const res = await assessmentApi.export({
            name: query.name || undefined, month: query.month || undefined, grade: query.grade || undefined,
        });
        const url = URL.createObjectURL(new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `月度考核导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        ElMessage.success('导出成功');
    } catch {
        ElMessage.error('导出失败，请重试');
    }
}

function onUserChange(uid) {
    const u = users.value.find((x) => x.id === uid);
    form.post = u ? (u.identity === 'staff' ? '前台值班人员' : '综合事务管理岗') : '';
    form.job_no = u?.employee_no || '';
}

function openCreate() {
    formMode.value = 'create';
    Object.assign(form, {
        id: null, user_id: null, name: '', post: '', job_no: '',
        supervisor_id: auth.user?.id || null, supervisor: '',
        month: new Date().toISOString().slice(0, 7),
        assess_date: new Date().toISOString().slice(0, 10),
        items: blankItems(), remark: '',
    });
    mode.value = 'form';
}

function fillForm(row) {
    Object.assign(form, {
        id: row.id, user_id: row.user_id, name: row.name, post: row.post, job_no: row.job_no,
        supervisor_id: row.supervisor_id, supervisor: row.supervisor,
        month: row.month, assess_date: row.assess_date,
        items: dict.modules.map((m, i) => {
            const it = (row.items || [])[i] || {};
            return { ded: it.ded || 0, bon: it.bon || 0, note: it.note || '' };
        }),
        remark: row.remark || '',
    });
    mode.value = 'form';
}
function openDetail(row) { formMode.value = 'detail'; fillForm(row); }
function openEdit(row) { formMode.value = 'edit'; fillForm(row); }
function backToList() { mode.value = 'list'; }

async function onSave() {
    if (formMode.value === 'create' && !form.user_id) { ElMessage.warning('请选择被考核员工'); return; }
    if (!form.month) { ElMessage.warning('请选择考核月份'); return; }
    if (!form.assess_date) { ElMessage.warning('请选择考核日期'); return; }
    saving.value = true;
    try {
        const payload = {
            user_id: form.user_id, month: form.month, assess_date: form.assess_date,
            supervisor_id: form.supervisor_id || undefined,
            items: form.items, remark: form.remark || undefined,
        };
        if (formMode.value === 'create') {
            await assessmentApi.store(payload);
            ElMessage.success('已创建');
        } else {
            await assessmentApi.update(form.id, payload);
            ElMessage.success('已保存');
        }
        backToList();
        load(query.page);
    } catch (e) {
        ElMessage.error(e?.data?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除「${row.name} / ${row.month}」的考核记录吗？删除后不可恢复。`, '提示', { type: 'warning' });
    await assessmentApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(async () => {
    const [opt, us] = await Promise.all([assessmentApi.options(), http.get('/users/options')]);
    Object.assign(dict, opt.data);
    users.value = us.data || [];
    form.items = blankItems();
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
.grade-hit {
    background: #ecf5ff;
}
.rule-pop {
    font-size: 12px;
    color: #606266;
    line-height: 1.8;
}
.rule-line { margin-bottom: 4px; }
</style>
