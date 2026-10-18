<template>
  <div class="qt-page">
    <el-tabs v-model="activeTab" class="card-tabs">
      <el-tab-pane label="借用" name="borrow" />
      <el-tab-pane label="代售" name="resell" />
      <el-tab-pane label="退宿" name="checkout" />
    </el-tabs>

    <!-- 借用 -->
    <template v-if="activeTab === 'borrow'">
      <div class="qt-filter-bar">
        <div class="qt-filter">
          <span class="qt-filter-label">借卡人</span>
          <el-input v-model.trim="query.keyword" placeholder="请输入" clearable style="width:160px" @keyup.enter="search" />
        </div>
        <div class="qt-filter">
          <span class="qt-filter-label">房间号</span>
          <el-input v-model.trim="query.room_no" placeholder="请输入" clearable style="width:160px" @keyup.enter="search" />
        </div>
        <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
        <el-button type="primary" style="margin-left:auto;" @click="openCreate">
          <QtIcon name="plus" :size="13" />&nbsp;借用登记
        </el-button>
      </div>

      <div class="qt-table-wrap">
        <el-table :data="rows" v-loading="loading" style="width:100%">
          <el-table-column prop="happened_at" label="日期" min-width="120" />
          <el-table-column prop="total_card" label="房间总卡" min-width="120" />
          <el-table-column prop="room_no" label="房间号" min-width="140" />
          <el-table-column prop="name" label="借卡人姓名" min-width="130" />
          <el-table-column prop="student_no" label="学号" min-width="130" />
          <el-table-column prop="contact" label="联系方式" min-width="140" />
          <el-table-column label="房卡交回情况" min-width="130">
            <template #default="{ row }">
              <el-tag :type="row.is_returned ? 'success' : 'warning'" disable-transitions>{{ row.is_returned_label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="应归还时间" min-width="160">
            <template #default="{ row }">
              <span v-if="row.remind_at" class="cell-remind">{{ row.remind_at }}</span>
              <span v-else class="cell-muted">—</span>
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
          <template #empty><el-empty description="暂无借用记录" /></template>
        </el-table>
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
          <el-pagination background layout="total, prev, pager, next" :total="total"
            :page-size="query.pageSize" :current-page="query.page"
            @current-change="(p) => load(p)" />
        </div>
      </div>
    </template>

    <!-- 代售 -->
    <template v-else-if="activeTab === 'resell'">
      <div class="qt-filter-bar">
        <div class="qt-filter">
          <span class="qt-filter-label">房间号</span>
          <el-input v-model.trim="query.room_no" placeholder="请输入" clearable style="width:160px" @keyup.enter="search" />
        </div>
        <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
        <el-button type="primary" style="margin-left:auto;" @click="openCreate">
          <QtIcon name="plus" :size="13" />&nbsp;代售登记
        </el-button>
      </div>

      <div class="qt-table-wrap">
        <el-table :data="rows" v-loading="loading" style="width:100%">
          <el-table-column prop="happened_at" label="日期" min-width="120" />
          <el-table-column prop="room_no" label="房间号" min-width="160" />
          <el-table-column prop="student_no" label="学号" min-width="160" />
          <el-table-column prop="contact" label="联系方式" min-width="160" />
          <el-table-column label="支付方式" min-width="140">
            <template #default="{ row }">
              <el-tag type="primary" disable-transitions>{{ row.pay_method_label }}</el-tag>
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
          <template #empty><el-empty description="暂无代售记录" /></template>
        </el-table>
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
          <el-pagination background layout="total, prev, pager, next" :total="total"
            :page-size="query.pageSize" :current-page="query.page"
            @current-change="(p) => load(p)" />
        </div>
      </div>
    </template>

    <!-- 退宿 -->
    <template v-else>
      <div class="qt-filter-bar">
        <div class="qt-filter">
          <span class="qt-filter-label">房间号</span>
          <el-input v-model.trim="query.room_no" placeholder="请输入" clearable style="width:160px" @keyup.enter="search" />
        </div>
        <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
        <el-button type="primary" style="margin-left:auto;" @click="openCreate">
          <QtIcon name="plus" :size="13" />&nbsp;退宿登记
        </el-button>
      </div>

      <div class="qt-table-wrap">
        <el-table :data="rows" v-loading="loading" style="width:100%">
          <el-table-column prop="happened_at" label="日期" min-width="120" />
          <el-table-column prop="room_no" label="房间号" min-width="150" />
          <el-table-column prop="student_no" label="学号" min-width="150" />
          <el-table-column prop="quantity" label="数量" min-width="100" />
          <el-table-column label="房卡交回情况" min-width="130">
            <template #default="{ row }">
              <el-tag :type="row.is_returned ? 'success' : 'warning'" disable-transitions>{{ row.is_returned_label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="卡状态" min-width="120">
            <template #default="{ row }">
              <el-tag :type="row.card_status === 'disabled' ? 'info' : 'warning'" disable-transitions>{{ row.card_status_label }}</el-tag>
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
          <template #empty><el-empty description="暂无退宿房卡记录" /></template>
        </el-table>
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
          <el-pagination background layout="total, prev, pager, next" :total="total"
            :page-size="query.pageSize" :current-page="query.page"
            @current-change="(p) => load(p)" />
        </div>
      </div>
    </template>

    <!-- 登记弹窗（原型：弹窗内带 借用/代售/退宿 三个 tab） -->
    <el-dialog v-model="dialog.visible" :title="dialogTitle" width="560px"
      :close-on-click-modal="false" destroy-on-close>
      <el-tabs v-model="formTab" class="dialog-tabs">
        <el-tab-pane label="借用" name="borrow" />
        <el-tab-pane label="代售" name="resell" />
        <el-tab-pane label="退宿" name="checkout" />
      </el-tabs>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
        <el-form-item label="日期" prop="happened_at">
          <el-date-picker v-model="form.happened_at" type="date" value-format="YYYY-MM-DD"
            placeholder="请选择日期" style="width:100%" />
        </el-form-item>

        <el-form-item v-if="formTab === 'borrow'" label="房间总卡" prop="total_card">
          <el-radio-group v-model="form.total_card">
            <el-radio value="是">是</el-radio>
            <el-radio value="否">否</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="房间号" prop="room_id">
          <el-select v-model="form.room_id" placeholder="请选择房间号" style="width:100%">
            <el-option v-for="r in rooms" :key="r.id" :label="r.room_no" :value="r.id" />
          </el-select>
        </el-form-item>

        <el-form-item v-if="formTab === 'borrow'" label="借卡人姓名" prop="name">
          <el-input v-model.trim="form.name" placeholder="请输入借卡人姓名" />
        </el-form-item>

        <el-form-item v-if="formTab !== 'checkout'" label="联系方式" prop="contact">
          <el-input v-model.trim="form.contact" placeholder="请输入手机号" />
        </el-form-item>

        <el-form-item label="学号" prop="student_no">
          <el-input v-model.trim="form.student_no" placeholder="请输入学号" />
        </el-form-item>

        <el-form-item v-if="formTab === 'resell'" label="支付方式" prop="pay_method">
          <el-radio-group v-model="form.pay_method">
            <el-radio value="alipay">支付宝</el-radio>
            <el-radio value="wechat">微信</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item v-if="formTab !== 'borrow'" label="数量" prop="quantity">
          <el-input-number v-model="form.quantity" :min="1" :max="99" />
        </el-form-item>

        <el-form-item v-if="formTab !== 'resell'" label="房卡交回情况" prop="is_returned">
          <div style="width:100%">
            <el-radio-group v-model="form.is_returned">
              <el-radio :value="true">已还</el-radio>
              <el-radio :value="false">未还</el-radio>
            </el-radio-group>
            <div v-if="formTab === 'borrow'" class="card-tip" :class="{ 'is-active': form.is_returned === false }">
              {{ remindTip }}
            </div>
          </div>
        </el-form-item>

        <el-form-item v-if="formTab === 'checkout'" label="卡状态" prop="card_status">
          <el-radio-group v-model="form.card_status">
            <el-radio value="disabled">停用</el-radio>
            <el-radio value="active">未停用</el-radio>
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
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { roomCardApi } from '../api/roomCard';
import { roomApi } from '../api/room';
import QtIcon from '../components/QtIcon.vue';

const activeTab = ref('borrow');
/** 弹窗内 tab（原型 FORMS.card：借用/代售/退宿 在弹窗里切换） */
const formTab = ref('borrow');
const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const rooms = ref([]);
const query = reactive({ keyword: '', room_no: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await roomCardApi.list({ type: activeTab.value, ...query });
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}
function search() { load(1); }
watch(activeTab, () => { query.keyword = ''; query.room_no = ''; load(1); });

const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, saving: false, editId: null });
const form = reactive({
    happened_at: '', room_id: null, total_card: '', student_no: '', name: '',
    contact: '', is_returned: null, pay_method: '', quantity: 1, card_status: '',
});

/** 「房卡交回情况」下方小字：1 小时后提醒归还（提醒对象是本条数据的登记人） */
const remindTip = ref('');
function calcRemindTip() {
    const t = new Date(Date.now() + 60 * 60 * 1000);
    const hh = String(t.getHours()).padStart(2, '0');
    const mm = String(t.getMinutes()).padStart(2, '0');
    remindTip.value = `选择「未还」后，于 1 小时后（${hh}:${mm}）提醒登记人归还房卡`;
}

const dialogTitle = computed(() => (dialog.isEdit ? '编辑房卡登记' : '房卡登记'));

const rules = {
    happened_at: [{ required: true, message: '请选择日期', trigger: 'change' }],
    room_id: [{ required: true, message: '请选择房间号', trigger: 'change' }],
    student_no: [{ required: true, message: '请填写学号', trigger: 'blur' }],
    contact: [{ required: true, message: '请填写联系方式', trigger: 'blur' }],
    total_card: [{ required: true, message: '请选择房间总卡', trigger: 'change' }],
    name: [{ required: true, message: '请填写借卡人姓名', trigger: 'blur' }],
    pay_method: [{ required: true, message: '请选择支付方式', trigger: 'change' }],
    quantity: [{ required: true, message: '请填写数量', trigger: 'change' }],
    is_returned: [{ required: true, message: '请选择房卡交回情况', trigger: 'change' }],
    card_status: [{ required: true, message: '请选择卡状态', trigger: 'change' }],
};

function resetForm() {
    Object.assign(form, {
        happened_at: '', room_id: null, total_card: '', student_no: '', name: '',
        contact: '', is_returned: null, pay_method: '', quantity: 1, card_status: '',
    });
}

function openCreate() {
    dialog.isEdit = false;
    dialog.editId = null;
    resetForm();
    formTab.value = activeTab.value;
    calcRemindTip();
    dialog.visible = true;
}

function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    formTab.value = row.type || activeTab.value;
    Object.assign(form, {
        happened_at: row.happened_at,
        room_id: row.room_id,
        total_card: row.total_card || '',
        student_no: row.student_no || '',
        name: row.name || '',
        contact: row.contact || '',
        is_returned: row.is_returned,
        pay_method: row.pay_method || '',
        quantity: row.quantity ?? 1,
        card_status: row.card_status || '',
    });
    calcRemindTip();
    dialog.visible = true;
}

function buildPayload() {
    const tab = formTab.value;
    const p = {
        type: tab,
        happened_at: form.happened_at,
        room_id: form.room_id,
        student_no: form.student_no,
        contact: form.contact,
    };
    if (tab === 'borrow') {
        p.total_card = form.total_card;
        p.name = form.name;
        p.is_returned = form.is_returned;
    } else if (tab === 'resell') {
        p.pay_method = form.pay_method;
        p.quantity = form.quantity;
    } else {
        p.quantity = form.quantity;
        p.is_returned = form.is_returned;
        p.card_status = form.card_status;
    }
    return p;
}

async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        if (dialog.isEdit) {
            const res = await roomCardApi.update(dialog.editId, buildPayload());
            ElMessage.success(res?.message || '已保存');
        } else {
            const res = await roomCardApi.store(buildPayload());
            ElMessage.success(res?.message || '已添加');
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
    await ElMessageBox.confirm('确定删除这条房卡记录吗？', '提示', { type: 'warning' });
    await roomCardApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(async () => {
    const opt = await roomApi.all();
    rooms.value = opt.data || [];
    load();
});
</script>

<style scoped>
.card-tabs { margin-bottom: 4px; }
.card-tip {
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.6;
    color: var(--el-text-color-secondary);
}
.card-tip.is-active { color: var(--el-color-primary); }
.cell-remind { color: var(--el-color-warning); font-size: 12px; }
.cell-muted { color: var(--el-text-color-placeholder); }
</style>
