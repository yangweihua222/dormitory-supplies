<template>
  <div class="qt-page">
    <!-- 筛选条（原型：请假人 + 状态 + 申请时间 + 查询 + 申请请假） -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">请假人</span>
        <el-select v-model="query.user_id" placeholder="全部" clearable filterable style="width:150px">
          <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">状态</span>
        <el-select v-model="query.status" placeholder="全部" clearable style="width:130px">
          <el-option v-for="s in statuses" :key="s.value" :label="s.label" :value="s.value" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">申请时间</span>
        <el-date-picker v-model="query.applied_date" type="date" placeholder="选择日期"
          value-format="YYYY-MM-DD" clearable style="width:180px" />
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出 Excel</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;申请请假
      </el-button>
    </div>

    <!-- 列表（原型：请假人 | 类型 | 申请时间 | 理由 | 请假时间范围 | 图片 | 替班人员 | 审批人 | 状态 | 审批时间 | 创建/修改时间 | 操作） -->
    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column prop="username" label="请假人" min-width="90" />
        <el-table-column label="类型" min-width="80">
          <template #default="{ row }">
            <el-tag type="primary" disable-transitions>{{ row.type_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="applied_at" label="申请时间" min-width="140" />
        <el-table-column prop="reason" label="理由" min-width="180" show-overflow-tooltip />
        <el-table-column prop="time_range" label="请假时间范围" min-width="200" />
        <el-table-column label="图片" min-width="110">
          <template #default="{ row }">
            <template v-if="row.images && row.images.length">
              <el-image v-for="(u, i) in row.images" :key="u" :src="u"
                :preview-src-list="row.images" :initial-index="i"
                fit="cover" class="lv-thumb" hide-on-click-modal preview-teleported />
            </template>
            <el-tag v-else type="info" disable-transitions>无</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="substitute" label="替班人员" min-width="100">
          <template #default="{ row }"><span>{{ row.substitute || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="approver" label="审批人" min-width="100">
          <template #default="{ row }"><span>{{ row.approver || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="状态" min-width="90">
          <template #default="{ row }">
            <el-tag :type="row.status_tag" disable-transitions>{{ row.status_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="approved_at" label="审批时间" min-width="140">
          <template #default="{ row }"><span>{{ row.approved_at || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" min-width="140" />
        <el-table-column prop="updated_at" label="修改时间" min-width="140" />
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <template v-if="row.status === 'pending' && isAdmin">
              <a class="qt-link" @click="onApprove(row)">同意</a>
              <a class="qt-link danger" @click="onReject(row)">拒绝</a>
            </template>
            <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无请假记录" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page"
          @current-change="(p) => load(p)" />
      </div>
    </div>

    <!-- 申请/编辑弹窗 -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑请假' : '请假申请'"
      width="720px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
        <div class="form-grid-2">
          <el-form-item label="请假人" prop="user_id">
            <el-select v-model="form.user_id" placeholder="请选择" style="width:100%" filterable
              :disabled="!isAdmin" @change="loadAnnual">
              <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="类型" prop="type">
            <el-select v-model="form.type" placeholder="请选择" style="width:100%" @change="loadAnnual">
              <el-option v-for="t in types" :key="t.value" :label="t.label" :value="t.value" />
            </el-select>
          </el-form-item>
          <el-form-item label="申请时间" prop="applied_at">
            <el-date-picker v-model="form.applied_at" type="datetime" placeholder="选择时间"
              value-format="YYYY-MM-DD HH:mm" style="width:100%" />
          </el-form-item>
          <el-form-item label="替班人员" prop="substitute_user_id">
            <el-select v-model="form.substitute_user_id" placeholder="请选择" clearable filterable style="width:100%">
              <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="开始时间" prop="start_date">
            <el-date-picker v-model="form.start_date" type="date" placeholder="选择日期"
              value-format="YYYY-MM-DD" style="width:100%" />
          </el-form-item>
          <el-form-item label="结束时间" prop="end_date">
            <el-date-picker v-model="form.end_date" type="date" placeholder="选择日期"
              value-format="YYYY-MM-DD" style="width:100%" />
          </el-form-item>
        </div>

        <!-- 原型：选「年假」时展示该人员剩余年假天数（常驻占位，避免页面抖动） -->
        <el-form-item label=" " class="lv-annual">
          <div class="lv-annual-tip">{{ annualTip }}</div>
        </el-form-item>

        <el-form-item label="理由" prop="reason">
          <el-input v-model.trim="form.reason" type="textarea" :rows="3" placeholder="请输入请假理由" />
        </el-form-item>

        <el-form-item label="图片">
          <div class="lv-upload">
            <el-upload list-type="picture-card" :file-list="imageList" accept="image/jpeg,image/png"
              :limit="9" :http-request="doUploadImage" :on-remove="onRemoveImage"
              :before-upload="beforeUploadImage">
              <QtIcon name="plus" :size="18" />
            </el-upload>
            <div class="lv-upload-tip">支持 jpg / png，单张不超过 5MB，最多 9 张</div>
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取 消</el-button>
        <el-button type="primary" :loading="dialog.saving" @click="onSave">保 存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { leaveApi } from '../api/leave';
import { useAuthStore } from '../stores/auth';
import QtIcon from '../components/QtIcon.vue';
import dayjs from 'dayjs';

const auth = useAuthStore();
const isAdmin = computed(() => !!auth.user?.is_admin);

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const users = ref([]);
const types = ref([]);
const statuses = ref([]);
const query = reactive({ user_id: '', status: '', applied_date: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await leaveApi.list({
            page: query.page,
            pageSize: query.pageSize,
            user_id: query.user_id || undefined,
            status: query.status || undefined,
            applied_date: query.applied_date || undefined,
        });
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}

function search() {
    load(1);
}

async function onExport() {
    try {
        const res = await leaveApi.export({
            user_id: query.user_id || undefined,
            status: query.status || undefined,
            applied_date: query.applied_date || undefined,
        });
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `请假导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        ElMessage.success('导出成功');
    } catch {
        ElMessage.error('导出失败，请重试');
    }
}

async function loadOptions() {
    const res = await leaveApi.options();
    users.value = res.data.users || [];
    types.value = res.data.types || [];
    statuses.value = res.data.statuses || [];
}

const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, saving: false, editId: null });
const form = reactive({
    user_id: '',
    type: 'annual',
    applied_at: '',
    start_date: '',
    end_date: '',
    reason: '',
    substitute_user_id: '',
    images: [],
});

const rules = {
    user_id: [{ required: true, message: '请选择请假人', trigger: 'change' }],
    type: [{ required: true, message: '请选择类型', trigger: 'change' }],
    applied_at: [{ required: true, message: '请选择申请时间', trigger: 'change' }],
    start_date: [{ required: true, message: '请选择开始时间', trigger: 'change' }],
    end_date: [{ required: true, message: '请选择结束时间', trigger: 'change' }],
    reason: [{ required: true, message: '请填写请假理由', trigger: 'blur' }],
};

// 年假剩余提示（原型 §4.1）
const annualRemain = ref(null);
const annualTip = computed(() => {
    if (form.type !== 'annual' || !form.user_id) return '';
    if (annualRemain.value === null) return '';
    if (!annualRemain.value.has_record) return '该人员暂无年假记录';
    return `该人员剩余年假 ${annualRemain.value.remain_days} 天（年假总额 ${annualRemain.value.total_days} 天）`;
});

async function loadAnnual() {
    annualRemain.value = null;
    if (form.type !== 'annual' || !form.user_id) return;
    try {
        const res = await leaveApi.annualRemain(form.user_id);
        annualRemain.value = res.data;
    } catch (e) {
        annualRemain.value = null;
    }
}

watch(() => [form.type, form.user_id], loadAnnual);

function openCreate() {
    dialog.isEdit = false;
    dialog.editId = null;
    Object.assign(form, {
        user_id: auth.user?.id || '',
        type: 'annual',
        applied_at: dayjs().format('YYYY-MM-DD HH:mm'),
        start_date: dayjs().format('YYYY-MM-DD'),
        end_date: dayjs().format('YYYY-MM-DD'),
        reason: '',
        substitute_user_id: '',
        images: [],
    });
    dialog.visible = true;
    loadAnnual();
}

function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    Object.assign(form, {
        user_id: row.user_id,
        type: row.type,
        applied_at: row.applied_at,
        start_date: row.start_date,
        end_date: row.end_date,
        reason: row.reason || '',
        substitute_user_id: row.substitute_user_id || '',
        images: extractNames(row.images),
    });
    dialog.visible = true;
    loadAnnual();
}

async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        const payload = {
            user_id: form.user_id,
            type: form.type,
            applied_at: form.applied_at,
            start_date: form.start_date,
            end_date: form.end_date,
            reason: form.reason,
            substitute_user_id: form.substitute_user_id || null,
            images: form.images,
        };
        if (dialog.isEdit) {
            const res = await leaveApi.update(dialog.editId, payload);
            ElMessage.success(res.message || '已保存');
        } else {
            const res = await leaveApi.store(payload);
            ElMessage.success(res.message || '已提交申请');
        }
        dialog.visible = false;
        load(query.page);
    } finally {
        dialog.saving = false;
    }
}

async function onApprove(row) {
    await ElMessageBox.confirm(
        `同意 ${row.username} 的${row.type_label}（${row.time_range}）？通过后其排班状态将置为「请假」。`,
        '审批确认', { type: 'warning' }
    );
    const res = await leaveApi.approve(row.id);
    ElMessage.success(res.message || '已同意');
    load(query.page);
}

async function onReject(row) {
    await ElMessageBox.confirm(`拒绝 ${row.username} 的${row.type_label}申请吗？`, '审批确认', { type: 'warning' });
    const res = await leaveApi.reject(row.id);
    ElMessage.success(res.message || '已拒绝');
    load(query.page);
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除 ${row.username} ${row.time_range} 的请假申请吗？`, '提示', { type: 'warning' });
    await leaveApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

// ===== 图片 =====
function extractNames(urls) {
    return (urls || []).map((u) => (u.split('name=')[1] || u));
}

const imageList = computed(() => (Array.isArray(form.images) ? form.images : [])
    .map((n) => ({ name: n, url: `/api/leaves/image?name=${n}` })));

function beforeUploadImage(file) {
    if (!['image/jpeg', 'image/png'].includes(file.type)) {
        ElMessage.error('仅支持 jpg / png 图片');
        return false;
    }
    if (file.size > 5 * 1024 * 1024) {
        ElMessage.error('单张图片不能超过 5MB');
        return false;
    }
    return true;
}

async function doUploadImage({ file, onSuccess, onError }) {
    const fd = new FormData();
    fd.append('file', file);
    try {
        if (form.images.length >= 9) {
            ElMessage.warning('最多上传 9 张图片');
            onError?.(new Error('limit'));
            return;
        }
        const res = await leaveApi.upload(fd);
        form.images.push(res.data.name);
        onSuccess?.(res);
    } catch (e) {
        onError?.(e);
    }
}

function onRemoveImage(file) {
    const name = file?.name || (file?.url || '').split('name=')[1];
    form.images = form.images.filter((n) => n !== name);
}

onMounted(async () => {
    await loadOptions();
    load();
});
</script>

<style scoped>
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 20px;
}

.form-grid-2 :deep(.el-form-item) {
    min-width: 0;
}

.lv-annual-tip {
    color: #c0820a;
    font-weight: 600;
    min-height: 20px;
    line-height: 1.4;
}

.lv-thumb {
    width: 34px;
    height: 34px;
    border-radius: 4px;
    margin-right: 4px;
    vertical-align: middle;
}

.lv-upload-tip {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
}
</style>
