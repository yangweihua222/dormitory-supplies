<template>
  <div class="qt-page">
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">培训人员</span>
        <el-input v-model.trim="query.name" placeholder="请输入姓名" clearable style="width:160px"
          @keyup.enter="search" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">年级</span>
        <el-select v-model="query.grade" placeholder="全部" clearable style="width:120px">
          <el-option v-for="g in grades" :key="g" :label="g" :value="g" />
        </el-select>
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出 Excel</el-button>
      <el-button @click="downloadTemplate" style="margin-left:auto;">
        <QtIcon name="download" :size="13" />&nbsp;下载模板
      </el-button>
      <el-button @click="importDialog.visible = true">
        <QtIcon name="upload" :size="13" />&nbsp;导入 Excel
      </el-button>
      <el-button type="primary" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;添加培训
      </el-button>
    </div>

    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column prop="train_date" label="培训时间" min-width="120" />
        <el-table-column prop="trainer_name" label="培训人员姓名" min-width="120" />
        <el-table-column prop="student_no" label="学号" min-width="130">
          <template #default="{ row }"><span>{{ row.student_no || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="grade" label="年级" min-width="90">
          <template #default="{ row }"><span>{{ row.grade || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="train_content" label="培训内容" min-width="280" show-overflow-tooltip />
        <el-table-column label="培训时长" min-width="100" align="center">
          <template #default="{ row }"><span>{{ row.train_hours }} 小时</span></template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.remark || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" min-width="150" />
        <el-table-column prop="updated_at" label="修改时间" min-width="150" />
        <el-table-column label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无消防培训记录" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page" @current-change="(p) => load(p)" />
      </div>
    </div>

    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑培训记录' : '消防培训登记'"
      width="620px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <div class="form-grid-2">
          <el-form-item label="培训时间" prop="train_date">
            <el-date-picker v-model="form.train_date" type="date" value-format="YYYY-MM-DD"
              placeholder="请选择" style="width:100%" />
          </el-form-item>
          <el-form-item label="培训人员" prop="trainer_name">
            <el-input v-model.trim="form.trainer_name" placeholder="请输入姓名" />
          </el-form-item>
          <el-form-item label="学号" prop="student_no">
            <el-input v-model.trim="form.student_no" placeholder="选填" />
          </el-form-item>
          <el-form-item label="年级" prop="grade">
            <el-select v-model="form.grade" placeholder="请选择" clearable style="width:100%">
              <el-option v-for="g in grades" :key="g" :label="g" :value="g" />
            </el-select>
          </el-form-item>
          <el-form-item label="培训时长" prop="train_hours">
            <el-input-number v-model="form.train_hours" :min="0" :max="999" :step="0.5" step-strictly
              style="width:100%" />
          </el-form-item>
        </div>
        <el-form-item label="培训内容" prop="train_content">
          <el-input v-model.trim="form.train_content" type="textarea" :rows="3" placeholder="请描述培训内容" />
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model.trim="form.remark" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" @click="onSave">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="importDialog.visible" title="导入消防培训" width="480px" :close-on-click-modal="false">
      <el-form label-width="100px">
        <el-form-item label="Excel 文件">
          <el-upload :auto-upload="false" :limit="1" accept=".xlsx,.xls"
            :on-change="(f) => (importDialog.file = f.raw)">
            <el-button><QtIcon name="upload" :size="13" />&nbsp;选择文件</el-button>
          </el-upload>
        </el-form-item>
      </el-form>
      <div class="hint">请按列顺序填写后上传：培训时间、培训人员姓名、学号、年级、培训内容、培训时长、备注。</div>
      <template #footer>
        <el-button @click="importDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="importDialog.saving" @click="onImport">开始导入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { fireTrainingApi } from '../api/fire';
import QtIcon from '../components/QtIcon.vue';

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const grades = ref([]);

const query = reactive({ name: '', grade: '', date: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await fireTrainingApi.list(query);
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}
function search() { load(1); }

async function onExport() {
    try {
        const res = await fireTrainingApi.export({
            name: query.name || undefined,
            grade: query.grade || undefined,
            date: query.date || undefined,
        });
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `消防培训导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        ElMessage.success('导出成功');
    } catch {
        ElMessage.error('导出失败，请重试');
    }
}
function reset() {
    Object.assign(query, { name: '', grade: '', date: '', page: 1 });
    load(1);
}

const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, editId: null });
const form = reactive({
    train_date: '', trainer_name: '', student_no: '', grade: '',
    train_content: '', train_hours: 2, remark: '',
});

const rules = {
    train_date: [{ required: true, message: '请选择培训时间', trigger: 'change' }],
    trainer_name: [{ required: true, message: '请填写培训人员姓名', trigger: 'blur' }],
    train_content: [{ required: true, message: '请填写培训内容', trigger: 'blur' }],
    train_hours: [{ required: true, message: '请填写培训时长', trigger: 'change' }],
};

function today() {
    return new Date().toISOString().slice(0, 10);
}

function openCreate() {
    dialog.isEdit = false;
    dialog.editId = null;
    Object.assign(form, {
        train_date: today(), trainer_name: '', student_no: '', grade: '',
        train_content: '', train_hours: 2, remark: '',
    });
    dialog.visible = true;
}

function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    Object.assign(form, {
        train_date: row.train_date,
        trainer_name: row.trainer_name,
        student_no: row.student_no || '',
        grade: row.grade || '',
        train_content: row.train_content,
        train_hours: row.train_hours,
        remark: row.remark || '',
    });
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();
    try {
        if (dialog.isEdit) {
            await fireTrainingApi.update(dialog.editId, { ...form });
            ElMessage.success('已保存');
        } else {
            await fireTrainingApi.store({ ...form });
            ElMessage.success('已添加');
        }
        dialog.visible = false;
        load(query.page);
    } catch (e) {
        ElMessage.error(e?.data?.message || '保存失败');
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除「${row.trainer_name}」的培训记录吗？`, '提示', { type: 'warning' });
    await fireTrainingApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

const importDialog = reactive({ visible: false, saving: false, file: null });

async function downloadTemplate() {
    try {
        const res = await fireTrainingApi.template();
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = '消防培训导入模板.xlsx';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch (e) {
        ElMessage.error('模板下载失败，请重试');
    }
}

async function onImport() {
    if (!importDialog.file) {
        ElMessage.warning('请先选择 Excel 文件');
        return;
    }
    const fd = new FormData();
    fd.append('file', importDialog.file);
    importDialog.saving = true;
    try {
        const res = await fireTrainingApi.import(fd);
        ElMessage.success(res.message || '导入成功');
        importDialog.visible = false;
        importDialog.file = null;
        load(1);
    } catch (e) {
        ElMessage.error(e?.data?.message || '导入失败');
    } finally {
        importDialog.saving = false;
    }
}

onMounted(async () => {
    const opt = await fireTrainingApi.options();
    grades.value = opt.data.grades || [];
    load();
});
</script>

<style scoped>
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 16px;
}
.hint {
    font-size: 12px;
    color: var(--text-muted);
}
</style>
