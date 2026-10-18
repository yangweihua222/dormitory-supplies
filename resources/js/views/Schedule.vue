<template>
  <div class="qt-page">
    <!-- 搜索条 -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">日期范围</span>
        <el-date-picker v-model="query.dateRange" type="daterange" range-separator="至"
          start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD"
          style="width:240px" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">人员</span>
        <el-select v-model="query.user_id" placeholder="全部" clearable style="width:140px">
          <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">班次</span>
        <el-select v-model="query.shift_id" placeholder="全部" clearable style="width:140px">
          <el-option v-for="s in shifts" :key="s.id" :label="s.name" :value="s.id" />
        </el-select>
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出 Excel</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openBatch">
        <QtIcon name="plus" :size="13" />&nbsp;批量排班
      </el-button>
      <el-button type="primary" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;新增排班
      </el-button>
      <el-button @click="downloadTemplate">
        <QtIcon name="download" :size="13" />&nbsp;下载模板
      </el-button>
      <el-button @click="openImport">
        <QtIcon name="upload" :size="13" />&nbsp;导入 Excel
      </el-button>
    </div>

    <!-- 列表 -->
    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column prop="date" label="日期" min-width="120" />
        <el-table-column prop="username" label="人员" min-width="120" />
        <el-table-column prop="shift_name" label="班次" min-width="120" />
        <el-table-column prop="shift_check_in" label="签到时间" min-width="120" />
        <el-table-column prop="shift_check_out" label="签退时间" min-width="120" />
        <el-table-column label="状态" min-width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'leave' ? 'danger' : 'success'" disable-transitions>{{ row.status_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无排班" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page"
          @current-change="(p) => load(p)" />
      </div>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑排班' : '新增排班'"
      width="520px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="人员" prop="user_id">
          <el-select v-model="form.user_id" placeholder="请选择" style="width:100%" filterable>
            <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="日期" prop="date">
          <el-date-picker v-model="form.date" type="date" placeholder="选择日期" value-format="YYYY-MM-DD" style="width:100%;" />
        </el-form-item>
        <el-form-item label="班次" prop="shift_id">
          <el-select v-model="form.shift_id" placeholder="请选择" style="width:100%">
            <el-option v-for="s in shifts" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取 消</el-button>
        <el-button type="primary" :loading="dialog.saving" @click="onSave">保 存</el-button>
      </template>
    </el-dialog>

    <!-- 批量排班弹窗 -->
    <el-dialog v-model="batchDialog.visible" title="批量排班" width="520px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="batchFormRef" :model="batchForm" :rules="batchRules" label-width="100px">
        <el-form-item label="人员" prop="user_ids">
          <el-select v-model="batchForm.user_ids" multiple placeholder="请选择" style="width:100%" filterable>
            <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="日期范围" prop="dateRange">
          <el-date-picker v-model="batchForm.dateRange" type="daterange" range-separator="至"
            start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD"
            style="width:100%" />
        </el-form-item>
        <el-form-item label="班次" prop="shift_id">
          <el-select v-model="batchForm.shift_id" placeholder="请选择" style="width:100%">
            <el-option v-for="s in shifts" :key="s.id" :label="s.name" :value="s.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="batchDialog.visible = false">取 消</el-button>
        <el-button type="primary" :loading="batchDialog.saving" @click="onBatchSave">保 存</el-button>
      </template>
    </el-dialog>
    <!-- 导入弹窗 -->
    <el-dialog v-model="importDialog.visible" title="导入排班" width="560px" :close-on-click-modal="false" destroy-on-close>
      <el-alert type="info" :closable="false" show-icon style="margin-bottom:14px;">
        <template #title>
          请先 <a class="qt-link" @click="downloadTemplate">下载导入模板</a>，按模板格式填写后再上传。
        </template>
        <div style="font-size:12px;line-height:1.8;color:#909399;">
          人员名称、日期（YYYY-MM-DD）、班次名称三列必填；同一人同一日期以本次导入为准（覆盖）。
        </div>
      </el-alert>
      <el-upload drag ref="importUploadRef" :auto-upload="false" :limit="1"
        accept=".xlsx,.xls" :on-change="onImportFileChange" :on-remove="onImportFileRemove">
        <div style="padding:16px 0;color:#909399;">
          <QtIcon name="upload" :size="34" style="display:block;margin:0 auto 8px;" />
          <div>将 Excel 文件拖到此处，或点击选择</div>
          <div style="font-size:12px;margin-top:4px;">仅支持 .xlsx / .xls 文件</div>
        </div>
      </el-upload>
      <template #footer>
        <el-button @click="importDialog.visible = false">取 消</el-button>
        <el-button type="primary" :loading="importDialog.saving" :disabled="!importFile" @click="onImport">开始导入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { scheduleApi } from '../api/schedule';
import { shiftApi } from '../api/shift';
import http from '../api';
import QtIcon from '../components/QtIcon.vue';

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const users = ref([]);
const shifts = ref([]);
const query = reactive({ dateRange: [], user_id: '', shift_id: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const params = {
            page: query.page,
            pageSize: query.pageSize,
            user_id: query.user_id || undefined,
            shift_id: query.shift_id || undefined,
        };
        if (query.dateRange && query.dateRange.length === 2) {
            params.start_date = query.dateRange[0];
            params.end_date = query.dateRange[1];
        }
        const res = await scheduleApi.list(params);
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
        const res = await scheduleApi.export({
            start_date: query.dateRange?.[0] || undefined,
            end_date: query.dateRange?.[1] || undefined,
            user_id: query.user_id || undefined,
            shift_id: query.shift_id || undefined,
        });
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `排班导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    const [userRes, shiftRes] = await Promise.all([
        http.get('/users/options'),
        http.get('/shifts/options'),
    ]);
    users.value = userRes.data || [];
    shifts.value = shiftRes.data || [];
}

const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, saving: false, editId: null });
const form = reactive({ user_id: '', date: '', shift_id: '' });

const rules = {
    user_id: [{ required: true, message: '请选择人员', trigger: 'change' }],
    date: [{ required: true, message: '请选择日期', trigger: 'change' }],
    shift_id: [{ required: true, message: '请选择班次', trigger: 'change' }],
};

function resetForm() {
    Object.assign(form, { user_id: '', date: '', shift_id: '' });
}

function openCreate() {
    dialog.isEdit = false;
    dialog.editId = null;
    resetForm();
    dialog.visible = true;
}

function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    Object.assign(form, { user_id: row.user_id, date: row.date, shift_id: row.shift_id });
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        if (dialog.isEdit) {
            await scheduleApi.update(dialog.editId, form);
            ElMessage.success('已保存');
        } else {
            await scheduleApi.create(form);
            ElMessage.success('已添加');
        }
        dialog.visible = false;
        load(query.page);
    } finally {
        dialog.saving = false;
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除 ${row.username} ${row.date} 的排班吗？`, '提示', { type: 'warning' });
    await scheduleApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

const batchFormRef = ref();
const batchDialog = reactive({ visible: false, saving: false });
const batchForm = reactive({ user_ids: [], dateRange: [], shift_id: '' });

const batchRules = {
    user_ids: [{ required: true, message: '请选择人员', trigger: 'change', type: 'array' }],
    dateRange: [{ required: true, message: '请选择日期范围', trigger: 'change', type: 'array' }],
    shift_id: [{ required: true, message: '请选择班次', trigger: 'change' }],
};

function openBatch() {
    Object.assign(batchForm, { user_ids: [], dateRange: [], shift_id: '' });
    batchDialog.visible = true;
}

async function onBatchSave() {
    await batchFormRef.value.validate();
    batchDialog.saving = true;
    try {
        const res = await scheduleApi.batch({
            user_ids: batchForm.user_ids,
            start_date: batchForm.dateRange[0],
            end_date: batchForm.dateRange[1],
            shift_id: batchForm.shift_id,
        });
        ElMessage.success(`新增 ${res.data.created} 条，跳过 ${res.data.skipped} 条`);
        batchDialog.visible = false;
        load(1);
    } finally {
        batchDialog.saving = false;
    }
}

// ===== Excel 导入 =====
const importUploadRef = ref();
const importFile = ref(null);
const importDialog = reactive({ visible: false, saving: false });

async function downloadTemplate() {
    try {
        const res = await scheduleApi.template();
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = '排班导入模板.xlsx';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch (e) {
        ElMessage.error('模板下载失败，请重试');
    }
}

function openImport() {
    importFile.value = null;
    importDialog.visible = true;
}

function onImportFileChange(file) {
    importFile.value = file.raw || null;
}

function onImportFileRemove() {
    importFile.value = null;
}

async function onImport() {
    if (!importFile.value) return;
    importDialog.saving = true;
    try {
        const formData = new FormData();
        formData.append('file', importFile.value);
        const res = await scheduleApi.importExcel(formData);
        const { created, updated, failed, errors } = res.data;
        let msg = `新增 ${created} 条，覆盖 ${updated} 条`;
        if (failed > 0) {
            msg += `，失败 ${failed} 条`;
        }
        if (failed > 0) {
            ElMessageBox.alert(
                (errors || []).map((e) => `<div>${e}</div>`).join('') + (failed > (errors || []).length ? `<div>……其余 ${failed - errors.length} 条略</div>` : ''),
                msg,
                { dangerouslyUseHTMLString: true, type: 'warning' }
            );
        } else {
            ElMessage.success(msg);
            importDialog.visible = false;
        }
        load(1);
    } finally {
        importDialog.saving = false;
    }
}

onMounted(() => {
    loadOptions();
    load();
});
</script>
