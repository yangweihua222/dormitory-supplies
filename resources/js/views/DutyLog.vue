<template>
  <div class="qt-page">
    <!-- 筛选条（原型：日期 + 分类 + 查询 + 新增日志） -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">日期</span>
        <el-date-picker v-model="query.date" type="date" placeholder="选择日期"
          value-format="YYYY-MM-DD" clearable style="width:180px" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">分类</span>
        <el-select v-model="query.category" placeholder="全部分类" clearable style="width:150px">
          <el-option v-for="(meta, key) in categories" :key="key" :label="meta.label" :value="key" />
        </el-select>
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;新增日志
      </el-button>
    </div>

    <!-- 列表（原型：日期 | 分类 | 分类字段详情 | 创建人 | 状态 | 创建/修改时间 | 操作） -->
    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%" :row-class-name="rowClass">
        <el-table-column prop="log_date" label="日期" width="110" />
        <el-table-column label="分类" width="100">
          <template #default="{ row }">
            <el-tag type="primary" disable-transitions>{{ row.category_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="分类字段详情" min-width="420">
          <template #default="{ row }">
            <div class="dl-fields">
              <span v-for="f in row.fields" :key="f.label" class="dl-kv">
                <template v-if="f.images && f.images.length">
                  <span class="dl-k">{{ f.label }}</span>
                  <el-image v-for="(u, i) in f.images" :key="u" :src="u"
                    :preview-src-list="f.images" :initial-index="i"
                    fit="cover" class="dl-thumb" hide-on-click-modal preview-teleported />
                </template>
                <template v-else>
                  <span class="dl-k">{{ f.label }}</span><span>{{ f.value || '-' }}</span>
                </template>
              </span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="creator" label="创建人" width="100" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status_label)" disable-transitions>{{ row.status_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160" />
        <el-table-column prop="updated_at" label="修改时间" width="160" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
            <a v-if="row.can_handover" class="qt-link" @click="onHandover(row)">
              <QtIcon name="refresh" :size="13" /> 交接
            </a>
            <a v-else class="qt-link muted" :title="handoverTip(row)">
              <QtIcon name="refresh" :size="13" /> 交接
            </a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无日志" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page"
          @current-change="(p) => load(p)" />
      </div>
    </div>

    <!-- 新增/编辑弹窗（原型：日期/创建人/状态 + 分类 Tab + 各分类字段面板） -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑日志' : '新增日志'"
      width="720px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="140px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="日期" prop="log_date">
              <el-date-picker v-model="form.log_date" type="date" placeholder="选择日期"
                value-format="YYYY-MM-DD" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="创建人" prop="creator_id">
              <el-select v-model="form.creator_id" placeholder="请选择" style="width:100%" filterable>
                <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio value="pending">未完成</el-radio>
            <el-radio value="done">已完成</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-tabs v-model="form.category" class="dl-tabs">
          <el-tab-pane v-for="(meta, key) in categories" :key="key" :name="key" :label="meta.label" />
        </el-tabs>

        <div class="form-grid-2">
          <el-form-item v-for="(meta, key) in currentFields" :key="key"
            :class="{ 'full-col': meta.type === 'textarea' || meta.type === 'images' }"
            :label="meta.label" :prop="`detail.${key}`"
            :rules="meta.required ? [{ required: true, message: `请填写${meta.label}`, trigger: 'blur' }] : []">
            <el-radio-group v-if="meta.type === 'bool'" v-model="form.detail[key]">
              <el-radio :value="true">是</el-radio>
              <el-radio :value="false">否</el-radio>
            </el-radio-group>
            <el-radio-group v-else-if="meta.type === 'publish'" v-model="form.detail[key]">
              <el-radio :value="true">已发布</el-radio>
              <el-radio :value="false">未发布</el-radio>
            </el-radio-group>
            <el-input v-else-if="meta.type === 'textarea'" v-model.trim="form.detail[key]" type="textarea"
              :rows="3" :placeholder="`请输入${meta.label}`" />
            <div v-else-if="meta.type === 'images'" class="dl-upload">
              <el-upload list-type="picture-card" :file-list="imageList" accept="image/jpeg,image/png"
                :limit="9" :http-request="doUploadImage" :on-remove="onRemoveImage"
                :before-upload="beforeUploadImage">
                <QtIcon name="plus" :size="18" />
              </el-upload>
              <div class="dl-upload-tip">支持 jpg / png，单张不超过 5MB，最多 9 张</div>
            </div>
            <el-input v-else v-model.trim="form.detail[key]" :placeholder="`请输入${meta.label}`" />
          </el-form-item>
        </div>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取 消</el-button>
        <el-button type="primary" :loading="dialog.saving" @click="onSave">保 存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { dutyLogApi } from '../api/dutyLog';
import http from '../api';
import { useAuthStore } from '../stores/auth';
import QtIcon from '../components/QtIcon.vue';

const auth = useAuthStore();
const route = useRoute();
// 从待办点进来时高亮定位到那条日志（/dutylog?focus=ID）
const focusId = ref(route.query.focus ? Number(route.query.focus) : null);
const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const users = ref([]);
const categories = ref({});
const query = reactive({ date: '', category: '', page: 1, pageSize: 10 });

const currentFields = computed(() => categories.value[form.category]?.fields || {});

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const params = {
            page: query.page,
            pageSize: query.pageSize,
            date: query.date || undefined,
            category: query.category || undefined,
        };
        const res = await dutyLogApi.list(params);
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}

function rowClass({ row }) {
    return focusId.value && row.id === focusId.value ? 'row-focus' : '';
}

function statusTagType(label) {
    if (label === '已完成') return 'success';
    if (label === '已交接') return 'info';
    return 'warning';
}

/** 交接按钮置灰时的原因提示 */
function handoverTip(row) {
    if (row.status === 'done') return '该日志已完成，无需交接';
    if (row.handoff_done) {
        const at = row.handed_over_at ? row.handed_over_at.slice(5, 16) : '—';
        return `已于 ${at} 交接给 ${row.handover_to || '次日值班人'}，等待其确认，无需重复交接`;
    }
    return '只有日志创建人或管理员可以交接';
}

function search() {
    load(1);
}

async function loadOptions() {
    const [userRes, schemaRes] = await Promise.all([
        http.get('/users/options'),
        dutyLogApi.schema(),
    ]);
    users.value = userRes.data || [];
    categories.value = schemaRes.data || {};
}

const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, saving: false, editId: null });
const form = reactive({ log_date: '', creator_id: '', status: 'pending', category: 'phone', detail: {} });

const rules = {
    log_date: [{ required: true, message: '请选择日期', trigger: 'change' }],
    creator_id: [{ required: true, message: '请选择创建人', trigger: 'change' }],
    status: [{ required: true, message: '请选择状态', trigger: 'change' }],
};

function emptyDetail() {
    const detail = {};
    const meta = categories.value[form.category]?.fields || {};
    Object.keys(meta).forEach((k) => {
        detail[k] = meta[k]?.type === 'images' ? [] : null;
    });
    return detail;
}

function openCreate() {
    dialog.isEdit = false;
    dialog.editId = null;
    Object.assign(form, {
        log_date: new Date().toISOString().slice(0, 10),
        creator_id: auth.user?.id,
        status: 'pending',
        category: 'phone',
    });
    form.detail = emptyDetail();
    dialog.visible = true;
}

function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    Object.assign(form, {
        log_date: row.log_date,
        creator_id: row.creator_id,
        status: row.status,
        category: row.category,
    });
    const detail = {};
    const meta = categories.value[row.category]?.fields || {};
    Object.keys(meta).forEach((k) => {
        const isImages = meta[k]?.type === 'images';
        detail[k] = isImages ? (Array.isArray(row.detail?.[k]) ? [...row.detail[k]] : []) : (row.detail?.[k] ?? null);
    });
    form.detail = detail;
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        const payload = {
            log_date: form.log_date,
            creator_id: form.creator_id,
            status: form.status,
            category: form.category,
            detail: form.detail,
        };
        if (dialog.isEdit) {
            await dutyLogApi.update(dialog.editId, payload);
            ElMessage.success('已保存');
        } else {
            await dutyLogApi.store(payload);
            ElMessage.success('已添加');
        }
        dialog.visible = false;
        load(query.page);
    } finally {
        dialog.saving = false;
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除 ${row.log_date} ${row.category_label} 的日志吗？`, '提示', { type: 'warning' });
    await dutyLogApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

async function onHandover(row) {
    await ElMessageBox.confirm(`确定交接 ${row.log_date} ${row.category_label} 的日志吗？交接后将通知次日值班人。`, '交接确认', { type: 'info' });
    const res = await dutyLogApi.handover(row.id);
    ElMessage.success(res.message || '交接完成');
    load(query.page);
}

// ===== 异常情况图片上传 =====
const imageList = computed(() => (Array.isArray(form.detail.images) ? form.detail.images : [])
    .map((n) => ({ name: n, url: `/api/duty-logs/image?name=${n}` })));

function beforeUploadImage(file) {
    const okType = ['image/jpeg', 'image/png'].includes(file.type);
    if (!okType) { ElMessage.error('仅支持 jpg / png 图片'); return false; }
    if (file.size > 5 * 1024 * 1024) { ElMessage.error('单张图片不能超过 5MB'); return false; }
    return true;
}

async function doUploadImage({ file, onSuccess, onError }) {
    const fd = new FormData();
    fd.append('file', file);
    try {
        const res = await dutyLogApi.upload(fd);
        if (!Array.isArray(form.detail.images)) form.detail.images = [];
        if (form.detail.images.length >= 9) {
            ElMessage.warning('最多上传 9 张图片');
            onError?.(new Error('limit'));
            return;
        }
        form.detail.images.push(res.data.name);
        onSuccess?.(res);
    } catch (e) {
        onError?.(e);
    }
}

function onRemoveImage(file) {
    const name = file.name || (file.url || '').split('/').pop();
    form.detail.images = (form.detail.images || []).filter((n) => n !== name);
}

onMounted(() => {
    loadOptions();
    load();
});
</script>

<style scoped>
.dl-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 16px;
    line-height: 1.9;
}

.dl-kv {
    font-size: 13px;
    color: #606266;
    white-space: nowrap;
}

.dl-k {
    color: #909399;
    margin-right: 6px;
}

.dl-tabs {
    margin: 4px 0 14px;
}

.dl-tabs :deep(.el-tabs__nav-wrap::after) {
    height: 1px;
}

.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 20px;
}

.form-grid-2 :deep(.el-form-item) {
    min-width: 0;
}

.form-grid-2 .full-col {
    grid-column: 1 / -1;
}

.dl-thumb {
    width: 34px;
    height: 34px;
    border-radius: 4px;
    border: 1px solid #e4e7ed;
    margin-right: 4px;
    vertical-align: middle;
    cursor: pointer;
}

.dl-upload {
    width: 100%;
}

.dl-upload :deep(.el-upload--picture-card) {
    width: 72px;
    height: 72px;
    border-radius: 6px;
    color: #909399;
}

.dl-upload :deep(.el-upload-list--picture-card .el-upload-list__item) {
    width: 72px;
    height: 72px;
    border-radius: 6px;
}

.dl-upload-tip {
    font-size: 12px;
    color: #909399;
    line-height: 1.6;
    margin-top: 4px;
}

/* 从待办跳转定位的行高亮 */
:deep(.row-focus) > td {
    background: #e6f4ff !important;
}
</style>
