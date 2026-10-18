<template>
  <div class="qt-page">
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">员工姓名</span>
        <el-input v-model.trim="query.name" placeholder="请输入" clearable style="width:150px" @keyup.enter="search" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">考核月份</span>
        <el-date-picker v-model="query.month" type="month" value-format="YYYY-MM" placeholder="请选择" style="width:140px" />
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出 Excel</el-button>
      <el-button v-if="auth.isAdmin" type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;创建申诉
      </el-button>
    </div>
    <div v-if="!auth.isAdmin" class="scope-hint">数据范围：仅展示本人申诉记录；如需发起申诉请联系管理员登记。</div>

    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column type="index" label="序号" width="60" />
        <el-table-column prop="name" label="员工姓名" min-width="100" />
        <el-table-column prop="month" label="考核月份" min-width="100" />
        <el-table-column prop="matter" label="申诉事项" min-width="280" show-overflow-tooltip />
        <el-table-column prop="appeal_date" label="申诉日期" min-width="140" />
        <el-table-column label="处理结果" min-width="200" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.result || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="自我整改措施" min-width="180" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.rectify || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="状态" min-width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'done' ? 'success' : 'warning'" effect="light">{{ row.status_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" min-width="140" />
        <el-table-column prop="updated_at" label="修改时间" min-width="140" />
        <el-table-column v-if="auth.isAdmin" label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无申诉记录" /></template>
      </el-table>
      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page" @current-change="(p) => load(p)" />
      </div>
    </div>

    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑申诉' : '创建申诉'" width="640px"
      :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
        <div class="form-grid-2">
          <el-form-item label="员工姓名" prop="user_id">
            <el-select v-model="form.user_id" placeholder="请选择" style="width:100%">
              <el-option v-for="u in users" :key="u.id" :label="u.username" :value="u.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="考核月份" prop="month">
            <el-date-picker v-model="form.month" type="month" value-format="YYYY-MM" placeholder="请选择" style="width:100%" />
          </el-form-item>
        </div>
        <el-form-item label="申诉事项" prop="matter">
          <el-input v-model.trim="form.matter" type="textarea" :rows="3" placeholder="请描述申诉事项（对哪项扣分/加分有异议、事实依据）" />
        </el-form-item>
        <el-form-item label="申诉日期" prop="appeal_date">
          <el-date-picker v-model="form.appeal_date" type="datetime" value-format="YYYY-MM-DD HH:mm"
            placeholder="请选择" style="width:100%" />
        </el-form-item>
        <el-form-item label="处理结果" prop="result">
          <el-input v-model.trim="form.result" type="textarea" :rows="2" placeholder="选填；填写后状态自动变为「已完成」" />
        </el-form-item>
        <el-form-item label="自我整改措施" prop="rectify">
          <el-input v-model.trim="form.rectify" type="textarea" :rows="2" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="dialog.saving" @click="onSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import http from '../api';
import { appealApi } from '../api/assess';
import { useAuthStore } from '../stores/auth';
import QtIcon from '../components/QtIcon.vue';

const auth = useAuthStore();
const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const users = ref([]);

const query = reactive({ name: '', month: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await appealApi.list(query);
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}
function search() { load(1); }

async function onExport() {
    try {
        const res = await appealApi.export({ name: query.name || undefined, month: query.month || undefined });
        const url = URL.createObjectURL(new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = `考核申诉导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        ElMessage.success('导出成功');
    } catch {
        ElMessage.error('导出失败，请重试');
    }
}

const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, editId: null, saving: false });
const form = reactive({ user_id: null, month: '', matter: '', appeal_date: '', result: '', rectify: '' });
const rules = {
    user_id: [{ required: true, message: '请选择员工', trigger: 'change' }],
    month: [{ required: true, message: '请选择考核月份', trigger: 'change' }],
    matter: [{ required: true, message: '请填写申诉事项', trigger: 'blur' }],
    appeal_date: [{ required: true, message: '请选择申诉日期', trigger: 'change' }],
};

function nowStr() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

function openCreate() {
    dialog.isEdit = false;
    dialog.editId = null;
    Object.assign(form, {
        user_id: null, month: new Date().toISOString().slice(0, 7),
        matter: '', appeal_date: nowStr(), result: '', rectify: '',
    });
    dialog.visible = true;
}
function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    Object.assign(form, {
        user_id: row.user_id, month: row.month, matter: row.matter,
        appeal_date: row.appeal_date, result: row.result || '', rectify: row.rectify || '',
    });
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        const payload = { ...form, result: form.result || undefined, rectify: form.rectify || undefined };
        if (dialog.isEdit) {
            await appealApi.update(dialog.editId, payload);
            ElMessage.success('已保存');
        } else {
            await appealApi.store(payload);
            ElMessage.success('已创建');
        }
        dialog.visible = false;
        load(query.page);
    } catch (e) {
        ElMessage.error(e?.data?.message || '保存失败');
    } finally {
        dialog.saving = false;
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除「${row.name} / ${row.month}」的这条申诉记录吗？删除后不可恢复。`, '提示', { type: 'warning' });
    await appealApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(async () => {
    const us = await http.get('/users/options');
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
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 16px;
}
</style>
