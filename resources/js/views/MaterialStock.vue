<template>
  <div class="qt-page">
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">类型</span>
        <el-select v-model="query.type" placeholder="全部" clearable style="width:130px">
          <el-option v-for="t in options.types" :key="t.value" :label="t.label" :value="t.value" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">管理人</span>
        <el-select v-model="query.manager" placeholder="全部" clearable style="width:130px">
          <el-option v-for="m in options.managers" :key="m" :label="m" :value="m" />
        </el-select>
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出 Excel</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;{{ isIn ? '添加入库' : '添加出库' }}
      </el-button>
    </div>

    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column label="类型" width="110">
          <template #default="{ row }">
            <el-tag :type="row.type === 'fixed' ? 'info' : 'primary'" size="small">{{ row.type_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="category" label="名称类别" min-width="110" />
        <el-table-column prop="qty" label="数量" width="80" align="center" />
        <el-table-column label="资产编号" width="110" align="center">
          <template #default="{ row }"><span>{{ row.code || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="stock_date" :label="isIn ? '入库日期' : '出库日期'" width="110" align="center" />
        <el-table-column prop="manager" label="管理人" width="90" align="center" />
        <el-table-column prop="location" :label="isIn ? '存放地点' : '使用地点'" min-width="110" />
        <el-table-column label="备注" min-width="120">
          <template #default="{ row }"><span>{{ row.remark || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="操作" width="110" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="onDelete(row)">删除</el-button>
          </template>
        </el-table-column>
        <template #empty><el-empty :description="isIn ? '暂无入库记录' : '暂无出库记录'" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page" @current-change="(p) => load(p)" />
      </div>
    </div>

    <el-dialog v-model="dialog.visible" :title="(dialog.isEdit ? '编辑' : '添加') + (isIn ? '入库' : '出库')"
      width="640px" top="8vh" :close-on-click-modal="false" destroy-on-close>
      <!-- 新增时双 Tab（固定资产 / 耗材类）；编辑时锁定类型 -->
      <el-tabs v-if="!dialog.isEdit" v-model="form.type" class="stock-tabs">
        <el-tab-pane label="固定资产" name="fixed" />
        <el-tab-pane label="耗材类" name="consumable" />
      </el-tabs>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <div class="form-grid-2">
          <el-form-item label="名称类别" prop="category">
            <el-input v-model.trim="form.category" :placeholder="form.type === 'fixed' ? '如：办公桌' : '如：棉被'" />
          </el-form-item>
          <el-form-item label="数量" prop="qty">
            <el-input-number v-model="form.qty" :min="1" :max="99999" style="width:100%" />
          </el-form-item>
          <el-form-item v-if="form.type === 'fixed'" label="资产编号" prop="code">
            <el-input v-model.trim="form.code" placeholder="如：GZ-001" />
          </el-form-item>
          <el-form-item :label="isIn ? '入库日期' : '出库日期'" prop="stock_date">
            <el-date-picker v-model="form.stock_date" type="date" value-format="YYYY-MM-DD"
              placeholder="请选择" style="width:100%" />
          </el-form-item>
          <el-form-item label="管理人" prop="manager">
            <el-select v-model="form.manager" placeholder="请选择" style="width:100%">
              <el-option v-for="m in options.managers" :key="m" :label="m" :value="m" />
            </el-select>
          </el-form-item>
          <el-form-item :label="isIn ? '存放地点' : '使用地点'" prop="location">
            <el-select v-model="form.location" placeholder="请选择" filterable style="width:100%">
              <el-option v-for="l in options.locations" :key="l" :label="l" :value="l" />
            </el-select>
          </el-form-item>
        </div>
        <el-form-item label="备注" prop="remark">
          <el-input v-model.trim="form.remark" type="textarea" :rows="2" placeholder="请输入备注" />
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
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { materialStockApi } from '../api/material';
import QtIcon from '../components/QtIcon.vue';

const props = defineProps({
    direction: { type: String, required: true }, // 'in' | 'out'
});

const isIn = computed(() => props.direction === 'in');

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const options = reactive({ types: [], managers: [], locations: [] });

const query = reactive({ type: '', manager: '', page: 1, pageSize: 10 });

const dialog = reactive({ visible: false, isEdit: false, saving: false, id: null });
const formRef = ref();
const blank = {
    type: 'fixed', category: '', qty: 1, code: '',
    stock_date: new Date().toISOString().slice(0, 10), manager: '', location: '', remark: '',
};
const form = reactive({ ...blank });

const rules = {
    category: [{ required: true, message: '请输入名称类别', trigger: 'blur' }],
    qty: [{ required: true, message: '请填写数量', trigger: 'blur' }],
    stock_date: [{ required: true, message: '请选择日期', trigger: 'change' }],
    manager: [{ required: true, message: '请选择管理人', trigger: 'change' }],
    location: [{ required: true, message: '请选择地点', trigger: 'change' }],
};

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await materialStockApi.list(props.direction, { ...query });
        rows.value = res.data.list;
        total.value = res.data.total;
    } finally {
        loading.value = false;
    }
}

function search() {
    load(1);
}

async function onExport() {
    try {
        const res = await materialStockApi.export(props.direction, {
            type: query.type || undefined,
            manager: query.manager || undefined,
        });
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${isIn.value ? '入库记录' : '出库记录'}导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        ElMessage.success('导出成功');
    } catch {
        ElMessage.error('导出失败，请重试');
    }
}

function openCreate() {
    Object.assign(form, blank, { stock_date: new Date().toISOString().slice(0, 10) });
    dialog.isEdit = false;
    dialog.id = null;
    dialog.visible = true;
}

function openEdit(row) {
    Object.assign(form, {
        type: row.type, category: row.category, qty: row.qty, code: row.code || '',
        stock_date: row.stock_date, manager: row.manager, location: row.location, remark: row.remark || '',
    });
    dialog.isEdit = true;
    dialog.id = row.id;
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        if (dialog.isEdit) {
            await materialStockApi.update(props.direction, dialog.id, { ...form });
        } else {
            await materialStockApi.store(props.direction, { ...form });
        }
        ElMessage.success(dialog.isEdit ? '已保存' : '已添加');
        dialog.visible = false;
        load(query.page);
    } finally {
        dialog.saving = false;
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除该条${isIn.value ? '入库' : '出库'}记录（${row.category}）吗？`, '提示', { type: 'warning' });
    await materialStockApi.destroy(props.direction, row.id);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(async () => {
    const opt = await materialStockApi.options();
    Object.assign(options, opt.data);
    load();
});
</script>

<style scoped>
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 16px;
}
.stock-tabs {
    margin-top: -8px;
}
</style>
