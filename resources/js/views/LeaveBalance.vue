<template>
  <div class="qt-page">
    <!-- 筛选条（原型：人员 + 剩余天数 + 查询 + 添加假期） -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">人员</span>
        <el-input v-model="query.keyword" placeholder="请输入" clearable style="width:160px"
          @keyup.enter="search" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">剩余天数</span>
        <el-select v-model="query.remain" placeholder="全部" clearable style="width:130px">
          <el-option label="大于0" value="gt0" />
          <el-option label="等于0" value="eq0" />
        </el-select>
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;添加假期
      </el-button>
    </div>

    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column prop="username" label="人员" min-width="140" />
        <el-table-column label="年假天数" min-width="120" align="center">
          <template #default="{ row }">{{ row.total_days }}</template>
        </el-table-column>
        <el-table-column label="剩余天数" min-width="120" align="center">
          <template #default="{ row }">
            <el-tag :type="row.remain_days > 0 ? 'success' : 'info'" disable-transitions>{{ row.remain_days }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" min-width="160" />
        <el-table-column prop="updated_at" label="修改时间" min-width="160" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无年假记录" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page"
          @current-change="(p) => load(p)" />
      </div>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑假期' : '添加假期'"
      width="520px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="人员" prop="user_id">
          <el-select v-model="form.user_id" placeholder="请选择" style="width:100%" filterable
            :disabled="dialog.isEdit">
            <el-option v-for="u in userOptions" :key="u.id" :label="u.username" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="年假天数" prop="total_days">
          <el-input-number v-model="form.total_days" :min="0" :max="999" :step="0.5" style="width:100%" />
        </el-form-item>
        <el-form-item label="剩余天数" prop="remain_days">
          <el-input-number v-model="form.remain_days" :min="0" :max="999" :step="0.5" style="width:100%" />
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
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { leaveBalanceApi } from '../api/leaveBalance';
import http from '../api';
import QtIcon from '../components/QtIcon.vue';

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const query = reactive({ keyword: '', remain: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await leaveBalanceApi.list({
            page: query.page,
            pageSize: query.pageSize,
            keyword: query.keyword || undefined,
            remain: query.remain || undefined,
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

const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, saving: false, editId: null });
const form = reactive({ user_id: '', total_days: 5, remain_days: 5 });
const userOptions = ref([]);

const rules = {
    user_id: [{ required: true, message: '请选择人员', trigger: 'change' }],
    total_days: [{ required: true, message: '请填写年假天数', trigger: 'change' }],
    remain_days: [{ required: true, message: '请填写剩余天数', trigger: 'change' }],
};

async function loadUserOptions(exceptId = 0) {
    if (exceptId) {
        // 编辑时把当前人员也放进选项
        const [optRes, allRes] = await Promise.all([
            leaveBalanceApi.options(exceptId),
            http.get('/users/options'),
        ]);
        const editable = (allRes.data || []).filter((u) => u.id === exceptId);
        userOptions.value = [...editable, ...(optRes.data || [])];
    } else {
        const res = await leaveBalanceApi.options();
        userOptions.value = res.data || [];
    }
}

async function openCreate() {
    dialog.isEdit = false;
    dialog.editId = null;
    Object.assign(form, { user_id: '', total_days: 5, remain_days: 5 });
    await loadUserOptions();
    dialog.visible = true;
}

async function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    Object.assign(form, {
        user_id: row.user_id,
        total_days: row.total_days,
        remain_days: row.remain_days,
    });
    await loadUserOptions(row.user_id);
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();
    if (form.remain_days > form.total_days) {
        ElMessage.warning('剩余天数不能大于年假天数，已自动修正');
        form.remain_days = form.total_days;
    }
    dialog.saving = true;
    try {
        const payload = {
            user_id: form.user_id,
            total_days: form.total_days,
            remain_days: form.remain_days,
        };
        if (dialog.isEdit) {
            const res = await leaveBalanceApi.update(dialog.editId, payload);
            ElMessage.success(res.message || '已保存');
        } else {
            const res = await leaveBalanceApi.store(payload);
            ElMessage.success(res.message || '已添加');
        }
        dialog.visible = false;
        load(query.page);
    } finally {
        dialog.saving = false;
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除 ${row.username} 的年假记录吗？`, '提示', { type: 'warning' });
    await leaveBalanceApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(() => load());
</script>
