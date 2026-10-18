<template>
  <div class="qt-page">
    <!-- 搜索条（原型：房间编码 / 房间号 / 类型 + 查询 + 添加房源） -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">房间编码</span>
        <el-input v-model.trim="query.room_code" placeholder="请输入" clearable style="width:160px" @keyup.enter="search" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">房间号</span>
        <el-input v-model.trim="query.room_no" placeholder="请输入" clearable style="width:160px" @keyup.enter="search" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">类型</span>
        <el-select v-model="query.type" placeholder="全部" clearable style="width:140px">
          <el-option v-for="t in types" :key="t.value" :label="t.label" :value="t.value" />
        </el-select>
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;添加房源
      </el-button>
    </div>

    <!-- 列表 -->
    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column prop="room_code" label="房间编码" min-width="120" />
        <el-table-column prop="room_no" label="房间号" min-width="140" />
        <el-table-column label="类型" min-width="110">
          <template #default="{ row }">
            <el-tag type="info" disable-transitions>{{ row.type_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="beds" label="床位数" min-width="90" />
        <el-table-column label="房间物资" min-width="180">
          <template #default="{ row }">
            <span>{{ row.furniture_text || '—' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="140" show-overflow-tooltip>
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
        <template #empty><el-empty description="暂无房源" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page"
          @current-change="(p) => load(p)" />
      </div>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑房源' : '添加房源'"
      width="620px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-form-item label="房间编码" prop="room_code">
          <el-input v-model.trim="form.room_code" placeholder="如：A-301" />
        </el-form-item>
        <el-form-item label="房间号" prop="room_no">
          <el-input v-model.trim="form.room_no" placeholder="如：A栋301室" />
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="form.type" placeholder="请选择" style="width:100%">
            <el-option v-for="t in types" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="床位数" prop="beds">
          <el-input-number v-model="form.beds" :min="0" :max="99" />
        </el-form-item>

        <el-form-item label="房间物资">
          <div class="furn-box">
            <div v-for="(f, i) in form.furnitures" :key="i" class="furn-row">
              <el-select v-model="f.kind" placeholder="家具种类" style="width:150px">
                <el-option v-for="k in furnitureKinds" :key="k" :label="k" :value="k" />
              </el-select>
              <span class="furn-x">×</span>
              <el-input-number v-model="f.count" :min="0" :max="999" />
              <el-button text type="danger" @click="removeFurn(i)">删除</el-button>
            </div>
            <el-button size="small" type="primary" plain @click="addFurn">+ 增加</el-button>
            <div class="furn-tip">可点击「增加」登记多套家具，每套包含家具种类与数量</div>
          </div>
        </el-form-item>

        <el-form-item label="备注">
          <el-input v-model.trim="form.remark" type="textarea" :rows="2" placeholder="请输入备注信息" />
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
import { roomApi } from '../api/room';
import QtIcon from '../components/QtIcon.vue';

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const types = ref([]);
const furnitureKinds = ref([]);
const query = reactive({ room_code: '', room_no: '', type: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await roomApi.list(query);
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}
function search() { load(1); }

const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, saving: false, editId: null });
const form = reactive({ room_code: '', room_no: '', type: '', beds: 4, remark: '', furnitures: [] });

const rules = {
    room_code: [{ required: true, message: '请填写房间编码', trigger: 'blur' }],
    room_no: [{ required: true, message: '请填写房间号', trigger: 'blur' }],
    type: [{ required: true, message: '请选择类型', trigger: 'change' }],
};

function addFurn() { form.furnitures.push({ kind: '', count: 1 }); }
function removeFurn(i) { form.furnitures.splice(i, 1); }

function openCreate() {
    dialog.isEdit = false;
    dialog.editId = null;
    Object.assign(form, { room_code: '', room_no: '', type: types.value[0]?.value || '', beds: 4, remark: '', furnitures: [] });
    addFurn();
    dialog.visible = true;
}

function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    Object.assign(form, {
        room_code: row.room_code,
        room_no: row.room_no,
        type: row.type,
        beds: row.beds,
        remark: row.remark || '',
        furnitures: (row.furnitures || []).map((f) => ({ kind: f.kind, count: f.count })),
    });
    if (!form.furnitures.length) addFurn();
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();
    const payload = {
        room_code: form.room_code,
        room_no: form.room_no,
        type: form.type,
        beds: form.beds,
        remark: form.remark || null,
        furnitures: form.furnitures.filter((f) => f.kind).map((f) => ({ kind: f.kind, count: f.count ?? 0 })),
    };
    dialog.saving = true;
    try {
        if (dialog.isEdit) {
            await roomApi.update(dialog.editId, payload);
            ElMessage.success('已保存');
        } else {
            await roomApi.store(payload);
            ElMessage.success('已添加');
        }
        dialog.visible = false;
        load(query.page);
    } catch (e) {
        if (e?.data?.errors) {
            const errs = e.data.errors;
            formRef.value.fields?.forEach((f) => {
                const msg = errs[f.prop]?.[0];
                if (msg) { f.validateMessage = msg; f.validateState = 'error'; }
            });
        }
    } finally {
        dialog.saving = false;
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除房间「${row.room_no}」吗？`, '提示', { type: 'warning' });
    await roomApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(async () => {
    const opt = await roomApi.options();
    types.value = opt.data.types || [];
    furnitureKinds.value = opt.data.furniture_kinds || [];
    load();
});
</script>

<style scoped>
.furn-box { width: 100%; }
.furn-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.furn-x { color: var(--text-muted); }
.furn-tip { margin-top: 6px; font-size: 12px; color: var(--text-muted); }
</style>
