<template>
  <div class="qt-page">
    <!-- 搜索条 -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">班次名称</span>
        <el-input v-model.trim="query.keyword" placeholder="请输入" clearable style="width:200px" @keyup.enter="search" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">班次类型</span>
        <el-select v-model="query.type" placeholder="全部" clearable style="width:140px">
          <el-option label="值班" value="duty" />
          <el-option label="正常班" value="normal" />
        </el-select>
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;新增班次
      </el-button>
    </div>

    <!-- 列表 -->
    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column prop="name" label="班次名称" min-width="160" />
        <el-table-column prop="check_in_time" label="签到时间" min-width="120" />
        <el-table-column prop="check_out_time" label="签退时间" min-width="120" />
        <el-table-column label="班次类型" min-width="120">
          <template #default="{ row }">
            <el-tag :type="row.type === 'duty' ? 'warning' : 'info'" disable-transitions>{{ row.type_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" min-width="150" />
        <el-table-column prop="updated_at" label="修改时间" min-width="150" />
        <el-table-column label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无班次" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page"
          @current-change="(p) => load(p)" />
      </div>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑班次' : '新增班次'"
      width="520px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="班次名称" prop="name">
          <el-input v-model.trim="form.name" placeholder="请输入班次名称" />
        </el-form-item>
        <el-form-item label="签到时间" prop="check_in_time">
          <el-time-picker v-model="form.check_in_time" placeholder="选择签到时间" value-format="HH:mm:ss" format="HH:mm:ss" style="width:100%;" />
        </el-form-item>
        <el-form-item label="签退时间" prop="check_out_time">
          <el-time-picker v-model="form.check_out_time" placeholder="选择签退时间" value-format="HH:mm:ss" format="HH:mm:ss" style="width:100%;" />
        </el-form-item>
        <el-form-item label="班次类型" prop="type">
          <el-radio-group v-model="form.type">
            <el-radio value="normal">正常班</el-radio>
            <el-radio value="duty">值班</el-radio>
          </el-radio-group>
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
import { shiftApi } from '../api/shift';
import QtIcon from '../components/QtIcon.vue';

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const query = reactive({ keyword: '', type: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await shiftApi.list(query);
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
const form = reactive({ name: '', check_in_time: '', check_out_time: '', type: 'normal' });

const rules = {
    name: [{ required: true, message: '请输入班次名称', trigger: 'blur' }],
    check_in_time: [{ required: true, message: '请选择签到时间', trigger: 'change' }],
    check_out_time: [{ required: true, message: '请选择签退时间', trigger: 'change' }],
    type: [{ required: true, message: '请选择班次类型', trigger: 'change' }],
};

function resetForm() {
    Object.assign(form, { name: '', check_in_time: '', check_out_time: '', type: 'normal' });
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
    Object.assign(form, {
        name: row.name,
        check_in_time: row.check_in_time,
        check_out_time: row.check_out_time,
        type: row.type,
    });
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        if (dialog.isEdit) {
            await shiftApi.update(dialog.editId, form);
            ElMessage.success('已保存');
        } else {
            await shiftApi.create(form);
            ElMessage.success('已添加');
        }
        dialog.visible = false;
        load(query.page);
    } catch (e) {
        if (e?.data?.errors) {
            const errs = e.data.errors;
            formRef.value.fields?.forEach((f) => {
                const msg = errs[f.prop]?.[0];
                if (msg) f.validateMessage = msg, f.validateState = 'error';
            });
        }
    } finally {
        dialog.saving = false;
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除班次「${row.name}」吗？`, '提示', { type: 'warning' });
    await shiftApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(() => load());
</script>
