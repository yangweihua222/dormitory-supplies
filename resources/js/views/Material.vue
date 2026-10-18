<template>
  <div class="qt-page">
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">种类</span>
        <el-select v-model="query.kind" placeholder="全部" clearable style="width:120px">
          <el-option v-for="k in options.kinds" :key="k.value" :label="k.label" :value="k.value" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">房间号</span>
        <el-select v-model="query.location" placeholder="全部" clearable filterable style="width:140px">
          <el-option v-for="l in options.locations" :key="l" :label="l" :value="l" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">用途</span>
        <el-select v-model="query.use_type" placeholder="全部" clearable style="width:110px">
          <el-option v-for="u in options.uses" :key="u.value" :label="u.label" :value="u.value" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">是否已报废</span>
        <el-select v-model="query.scrapped" placeholder="全部" clearable style="width:120px">
          <el-option label="未报废" :value="false" />
          <el-option label="已报废" :value="true" />
        </el-select>
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出 Excel</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;添加物资
      </el-button>
    </div>

    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column label="种类" min-width="110">
          <template #default="{ row }">
            <el-tag :type="row.kind === 'fixed' ? 'info' : 'primary'" size="small">{{ row.kind_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="名称" min-width="90" />
        <el-table-column prop="purchase_year" label="采购年限" width="85" align="center" />
        <el-table-column label="预计报废年限" width="100" align="center">
          <template #default="{ row }"><span>{{ row.scrap_year || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="是否已报废" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.scrapped ? 'danger' : 'success'" size="small">{{ row.scrapped ? '已报废' : '未报废' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="location" label="房间号" min-width="100" />
        <el-table-column prop="use_type_label" label="用途" width="70" align="center" />
        <el-table-column label="家具种类" width="85" align="center">
          <template #default="{ row }"><span>{{ row.furniture_kind || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="资产编号" width="90" align="center">
          <template #default="{ row }"><span>{{ row.code || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="qty" label="数量" width="65" align="center" />
        <el-table-column label="入库时间" width="100" align="center">
          <template #default="{ row }"><span>{{ row.stock_in_at || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="入库人" width="80" align="center">
          <template #default="{ row }"><span>{{ row.stock_in_by || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="出库时间" width="100" align="center">
          <template #default="{ row }"><span>{{ row.stock_out_at || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="出库人" width="80" align="center">
          <template #default="{ row }"><span>{{ row.stock_out_by || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="库存数量" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.stock < 20 ? 'warning' : 'success'" size="small">{{ row.stock }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="110" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="danger" @click="onDelete(row)">删除</el-button>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无物资" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page" @current-change="(p) => load(p)" />
      </div>
    </div>

    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑物资' : '添加物资'"
      width="720px" top="5vh" :close-on-click-modal="false" destroy-on-close class="material-dialog">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="96px">
        <div class="form-grid-2">
          <el-form-item label="种类" prop="kind">
            <el-radio-group v-model="form.kind">
              <el-radio value="fixed">固定资产</el-radio>
              <el-radio value="consumable">耗材</el-radio>
            </el-radio-group>
          </el-form-item>
          <!-- 耗材子类：占位槽，固定资产时隐藏但占位（对齐原型，切换不抖动） -->
          <div class="slot-cell">
            <el-form-item v-if="form.kind === 'consumable'" label="耗材子类" prop="consumable_sub">
              <el-select v-model="form.consumable_sub" placeholder="请选择" style="width:100%">
                <el-option v-for="s in options.consumable_subs" :key="s.value" :label="s.label" :value="s.value" />
              </el-select>
            </el-form-item>
          </div>
          <el-form-item label="名称" prop="name">
            <el-input v-model.trim="form.name" placeholder="请输入物资名称" />
          </el-form-item>
          <el-form-item label="采购年限" prop="purchase_year">
            <el-input-number v-model="form.purchase_year" :min="1990" :max="2100" style="width:100%" />
          </el-form-item>
          <el-form-item label="预计报废年限" prop="scrap_year">
            <el-input-number v-model="form.scrap_year" :min="1990" :max="2100" style="width:100%" />
          </el-form-item>
          <el-form-item label="是否已报废" prop="scrapped">
            <el-select v-model="form.scrapped" style="width:100%">
              <el-option label="未报废" :value="false" />
              <el-option label="已报废" :value="true" />
            </el-select>
          </el-form-item>
          <el-form-item label="房间号" prop="location">
            <el-select v-model="form.location" placeholder="请选择" filterable style="width:100%">
              <el-option v-for="l in options.locations" :key="l" :label="l" :value="l" />
            </el-select>
          </el-form-item>
          <el-form-item label="用途" prop="use_type">
            <el-radio-group v-model="form.use_type">
              <el-radio value="office">办公</el-radio>
              <el-radio value="life">生活</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="家具种类" prop="furniture_kind">
            <el-select v-model="form.furniture_kind" placeholder="请选择" clearable style="width:100%">
              <el-option v-for="f in options.furniture_kinds" :key="f" :label="f" :value="f" />
            </el-select>
          </el-form-item>
          <el-form-item label="资产编号" prop="code">
            <el-input v-model.trim="form.code" placeholder="如：GZ-001" />
          </el-form-item>
          <el-form-item label="数量" prop="qty">
            <el-input-number v-model="form.qty" :min="0" :max="99999" style="width:100%" />
          </el-form-item>
          <el-form-item label="入库时间" prop="stock_in_at">
            <el-date-picker v-model="form.stock_in_at" type="date" value-format="YYYY-MM-DD"
              placeholder="请选择" style="width:100%" />
          </el-form-item>
          <el-form-item label="入库人" prop="stock_in_by">
            <el-select v-model="form.stock_in_by" placeholder="请选择" clearable style="width:100%">
              <el-option v-for="u in userNames" :key="u" :label="u" :value="u" />
            </el-select>
          </el-form-item>
          <el-form-item label="出库时间" prop="stock_out_at">
            <el-date-picker v-model="form.stock_out_at" type="date" value-format="YYYY-MM-DD"
              placeholder="请选择" style="width:100%" />
          </el-form-item>
          <el-form-item label="出库人" prop="stock_out_by">
            <el-select v-model="form.stock_out_by" placeholder="请选择" clearable style="width:100%">
              <el-option v-for="u in userNames" :key="u" :label="u" :value="u" />
            </el-select>
          </el-form-item>
          <el-form-item label="库存数量" prop="stock">
            <el-input-number v-model="form.stock" :min="0" :max="99999" style="width:100%" />
          </el-form-item>
          <!-- 需求文档字段（原型表单未列）：已报废时才需填写，放末尾不影响上方配对 -->
          <el-form-item v-if="form.scrapped" label="实际报废年限" prop="actual_scrap_year">
            <el-input-number v-model="form.actual_scrap_year" :min="1990" :max="2100" style="width:100%" />
          </el-form-item>
        </div>
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
import { materialApi } from '../api/material';
import http from '../api';
import QtIcon from '../components/QtIcon.vue';

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const userNames = ref([]);
const options = reactive({ kinds: [], consumable_subs: [], uses: [], furniture_kinds: [], locations: [] });

const query = reactive({ kind: '', location: '', use_type: '', scrapped: '', page: 1, pageSize: 10 });

const dialog = reactive({ visible: false, isEdit: false, saving: false, id: null });
const formRef = ref();
const blank = {
    kind: 'fixed', consumable_sub: '', name: '', purchase_year: new Date().getFullYear(),
    scrap_year: null, scrapped: false, actual_scrap_year: null, location: '',
    use_type: 'office', furniture_kind: '', code: '', qty: 1, stock: 0,
    stock_in_at: '', stock_in_by: '', stock_out_at: '', stock_out_by: '',
};
const form = reactive({ ...blank });

const rules = {
    kind: [{ required: true, message: '请选择种类', trigger: 'change' }],
    consumable_sub: [{ required: true, message: '请选择耗材子类', trigger: 'change' }],
    name: [{ required: true, message: '请输入物资名称', trigger: 'blur' }],
    purchase_year: [{ required: true, message: '请填写采购年限', trigger: 'blur' }],
    scrapped: [{ required: true, message: '请选择', trigger: 'change' }],
    location: [{ required: true, message: '请选择房间号', trigger: 'change' }],
    use_type: [{ required: true, message: '请选择用途', trigger: 'change' }],
    qty: [{ required: true, message: '请填写数量', trigger: 'blur' }],
};

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await materialApi.list({ ...query });
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
        const res = await materialApi.export({
            kind: query.kind || undefined,
            location: query.location || undefined,
            use_type: query.use_type || undefined,
            scrapped: query.scrapped === '' ? undefined : query.scrapped,
        });
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `物资列表导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    Object.assign(form, blank, { purchase_year: new Date().getFullYear() });
    dialog.isEdit = false;
    dialog.id = null;
    dialog.visible = true;
}

function openEdit(row) {
    Object.assign(form, blank, {
        kind: row.kind, consumable_sub: row.consumable_sub || '', name: row.name,
        purchase_year: row.purchase_year, scrap_year: row.scrap_year, scrapped: row.scrapped,
        actual_scrap_year: row.actual_scrap_year, location: row.location, use_type: row.use_type,
        furniture_kind: row.furniture_kind || '', code: row.code || '', qty: row.qty, stock: row.stock,
        stock_in_at: row.stock_in_at || '', stock_in_by: row.stock_in_by || '',
        stock_out_at: row.stock_out_at || '', stock_out_by: row.stock_out_by || '',
    });
    dialog.isEdit = true;
    dialog.id = row.id;
    dialog.visible = true;
}

/** 保存（含房间配置数量校验确认流程：后端 code=1001 → 弹框 → 带 confirm_mismatch 重提） */
async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        await submit(false);
    } finally {
        dialog.saving = false;
    }
}

async function submit(confirmed) {
    const payload = { ...form, confirm_mismatch: confirmed };
    const res = dialog.isEdit
        ? await materialApi.update(dialog.id, payload)
        : await materialApi.store(payload);

    if (res.code === 1001) {
        const d = res.data;
        try {
            await ElMessageBox.confirm(
                `该物资数量（${d.qty}）与房间【${d.location}】配置的家具种类【${d.furniture_kind}】数量（${d.configured}）不一致，是否仍要添加？`,
                '数量与房间配置不一致',
                { type: 'warning', confirmButtonText: '仍要保存', cancelButtonText: '取消' },
            );
        } catch {
            return; // 用户取消：不落库、不发待办
        }
        return submit(true);
    }

    ElMessage.success(dialog.isEdit ? '已保存' : '已添加');
    dialog.visible = false;
    load(query.page);
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除物资「${row.name}」吗？`, '提示', { type: 'warning' });
    await materialApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(async () => {
    const [opt, users] = await Promise.all([
        materialApi.options(),
        http.get('/users/options'),
    ]);
    Object.assign(options, opt.data);
    userNames.value = (users.data || []).map((u) => u.username ?? u.name ?? u);
    load();
});
</script>

<style scoped>
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 16px;
}
/* 耗材子类占位槽：与表单项同高（控件 32px + 下边距 14px），隐藏时布局不抖动 */
.slot-cell {
    min-height: 46px;
}
</style>

<style>
/* 弹窗紧凑化（参照安全检查弹窗）：一屏放下保存按钮 */
.material-dialog .el-dialog__body {
    padding-top: 12px;
    padding-bottom: 12px;
}
.material-dialog .el-form-item {
    margin-bottom: 14px;
}
</style>
