<template>
  <div class="qt-page">
    <!-- 搜索条（原型：报修地点 / 报修类别 / 日期 + 查询 + 导出 + 新增报修） -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">报修地点</span>
        <el-input v-model.trim="query.location" placeholder="请输入" clearable style="width:160px" @keyup.enter="search" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">报修类别</span>
        <el-select v-model="query.category" placeholder="全部" clearable style="width:140px">
          <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">日期</span>
        <el-date-picker v-model="query.date" type="date" value-format="YYYY-MM-DD" placeholder="请选择" style="width:160px" />
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出</el-button>
      <el-button type="primary" style="margin-left:auto;" :disabled="isFollower" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;新增报修
      </el-button>
    </div>

    <!-- 列表 -->
    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column prop="report_date" label="日期" min-width="110" />
        <el-table-column prop="location" label="报修地点" min-width="120" />
        <el-table-column label="报修类别" min-width="100">
          <template #default="{ row }">
            <el-tag type="info" disable-transitions>{{ row.category_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="sub_category" label="报修项目" min-width="150" />
        <el-table-column prop="description" label="问题描述" min-width="200" show-overflow-tooltip>
          <template #default="{ row }"><span>{{ row.description || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="repair_date" label="维修日期" min-width="110">
          <template #default="{ row }"><span>{{ row.repair_date || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="appoint_text" label="预约时间" min-width="130">
          <template #default="{ row }"><span>{{ row.appoint_text || '—' }}</span></template>
        </el-table-column>
        <el-table-column prop="agree_text" label="同意代修" min-width="100" />
        <el-table-column prop="contact" label="报修人联系方式" min-width="140" />
        <el-table-column prop="report_text" label="上报平台" min-width="100" />
        <el-table-column label="跟修人" min-width="100">
          <template #default="{ row }"><span>{{ row.follower || '—' }}</span></template>
        </el-table-column>
        <el-table-column label="维修情况" min-width="100">
          <template #default="{ row }">
            <el-tag :type="row.repair_status_type" disable-transitions>{{ row.repair_status_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" min-width="150" />
        <el-table-column prop="updated_at" label="修改时间" min-width="150" />
        <el-table-column label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <a class="qt-link" :class="{ danger: !isFollower, muted: isFollower }" @click="onDelete(row)">
              <QtIcon name="trash" :size="13" /> 删除
            </a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无报修记录" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page"
          @current-change="(p) => load(p)" />
      </div>
    </div>

    <!-- 新增/编辑弹窗 -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑报修' : '报修登记'"
      width="700px" :close-on-click-modal="false" destroy-on-close>
      <el-alert v-if="restrictTip" :title="restrictTip" type="warning" :closable="false" show-icon
        style="margin-bottom:14px;" />

      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
        <div class="form-grid-2">
          <el-form-item label="报修日期" prop="report_date" :class="{ 'is-locked': lockOthers }">
            <el-date-picker v-model="form.report_date" type="date" value-format="YYYY-MM-DD"
              placeholder="请选择" style="width:100%" :disabled="lockOthers" />
          </el-form-item>
          <el-form-item label="报修地点" prop="location">
            <el-input v-model.trim="form.location" placeholder="手动填入，如：A栋301室" :disabled="lockOthers" />
          </el-form-item>
          <el-form-item label="报修类别" prop="category">
            <el-select v-model="form.category" placeholder="请选择" style="width:100%"
              :disabled="lockOthers" @change="onCategoryChange">
              <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
            </el-select>
          </el-form-item>
          <el-form-item label="报修项目" prop="sub_category">
            <el-select v-model="form.sub_category" placeholder="请选择" style="width:100%" :disabled="lockOthers">
              <el-option v-for="s in subOptions" :key="s" :label="s" :value="s" />
            </el-select>
          </el-form-item>
        </div>

        <el-form-item label="问题描述" prop="description">
          <el-input v-model.trim="form.description" type="textarea" :rows="2"
            placeholder="请详细描述报修问题" :disabled="lockOthers" />
        </el-form-item>

        <div class="form-grid-2">
          <el-form-item label="维修日期" prop="repair_date">
            <el-date-picker v-model="form.repair_date" type="date" value-format="YYYY-MM-DD"
              placeholder="请选择" style="width:100%" :disabled="lockOthers" />
          </el-form-item>
          <el-form-item label="预约时间" prop="appoint_period">
            <el-radio-group v-model="form.appoint_period" :disabled="lockOthers">
              <el-radio v-for="p in periods" :key="p.value" :value="p.value">{{ p.label }}</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="是否同意代修" prop="is_agree_proxy">
            <el-radio-group v-model="form.is_agree_proxy" :disabled="lockOthers">
              <el-radio :value="true">同意</el-radio>
              <el-radio :value="false">不同意</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="报修人联系方式" prop="contact">
            <el-input v-model.trim="form.contact" placeholder="请输入手机号" :disabled="lockOthers" />
          </el-form-item>
          <el-form-item label="是否上报平台" prop="is_reported_platform">
            <el-radio-group v-model="form.is_reported_platform" :disabled="lockOthers">
              <el-radio :value="true">是</el-radio>
              <el-radio :value="false">否</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="跟修人" prop="follower_id">
            <el-select v-model="form.follower_id" placeholder="请选择" clearable style="width:100%" :disabled="lockOthers">
              <el-option v-for="u in followers" :key="u.id" :label="u.name" :value="u.id" />
            </el-select>
          </el-form-item>
        </div>

        <el-form-item label="维修情况" prop="repair_status">
          <el-select v-model="form.repair_status" placeholder="请选择" style="width:200px" :disabled="!canEditStatus">
            <el-option v-for="s in statuses" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
          <span v-if="!canEditStatus" class="field-tip">无权限修改（仅管理员 / 维修负责人可改）</span>
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
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { repairApi } from '../api/repair';
import { useAuthStore } from '../stores/auth';
import QtIcon from '../components/QtIcon.vue';

const auth = useAuthStore();

const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const categories = ref([]);
const subMap = ref({});
const statuses = ref([]);
const periods = ref([]);
/** 跟修人下拉（全量用户，仅作分工记录） */
const followers = ref([]);
/** 维修待办固定接收人名单（维修负责人 + 管理员） */
const recipients = ref([]);
const recipientsText = computed(() => (recipients.value.length ? recipients.value.join('、') : '维修负责人'));

/** 维修负责人：只能改「维修情况」，其余字段锁定，无新增/删除权限 */
const isFollower = ref(false);
/** 「跟修人」默认值：维修负责人（需求 §6） */
const defaultFollowerId = ref(null);
/** 能否修改「维修情况」 */
const canEditStatus = ref(true);
/** 维修负责人时锁定其余字段 */
const lockOthers = computed(() => isFollower.value);
const restrictTip = computed(() => {
    if (isFollower.value) return '当前账号为维修负责人：仅可修改「维修情况」，其余字段不可编辑。';
    if (!canEditStatus.value) return '当前账号无权限修改「维修情况」（仅管理员 / 维修负责人可改），其余字段可正常编辑。';
    return '';
});

const query = reactive({ location: '', category: '', date: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await repairApi.list(query);
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}
function search() { load(1); }

const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, saving: false, editId: null });
const form = reactive({
    report_date: '', location: '', category: '', sub_category: '', description: '',
    repair_date: '', appoint_period: 'am', is_agree_proxy: true, contact: '',
    is_reported_platform: false, follower_id: null, repair_status: 'pending',
});

const subOptions = computed(() => (subMap.value[form.category] || []));

const rules = {
    report_date: [{ required: true, message: '请选择报修日期', trigger: 'change' }],
    location: [{ required: true, message: '请填写报修地点', trigger: 'blur' }],
    category: [{ required: true, message: '请选择报修类别', trigger: 'change' }],
    sub_category: [{ required: true, message: '请选择报修项目', trigger: 'change' }],
    description: [{ required: true, message: '请填写问题描述', trigger: 'blur' }],
    appoint_period: [{ required: true, message: '请选择预约时间', trigger: 'change' }],
    contact: [{ required: true, message: '请填写报修人联系方式', trigger: 'blur' }],
};

function onCategoryChange() {
    const list = subMap.value[form.category] || [];
    form.sub_category = list[0] || '';
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

function openCreate() {
    if (isFollower.value) return;
    dialog.isEdit = false;
    dialog.editId = null;
    Object.assign(form, {
        report_date: today(), location: '', category: categories.value[0]?.value || '',
        sub_category: '', description: '', repair_date: '', appoint_period: 'am',
        is_agree_proxy: true, contact: '', is_reported_platform: false,
        follower_id: defaultFollowerId.value, repair_status: 'pending',
    });
    onCategoryChange();
    dialog.visible = true;
}

function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    Object.assign(form, {
        report_date: row.report_date,
        location: row.location,
        category: row.category,
        sub_category: row.sub_category,
        description: row.description || '',
        repair_date: row.repair_date || '',
        appoint_period: row.appoint_period || 'am',
        is_agree_proxy: !!row.is_agree_proxy,
        contact: row.contact || '',
        is_reported_platform: !!row.is_reported_platform,
        follower_id: row.follower_id ?? null,
        repair_status: row.repair_status,
    });
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();

    // 维修负责人只提交「维修情况」；无权限者不提交该字段
    const payload = isFollower.value
        ? { repair_status: form.repair_status }
        : {
            report_date: form.report_date,
            location: form.location,
            category: form.category,
            sub_category: form.sub_category,
            description: form.description,
            repair_date: form.repair_date || null,
            appoint_period: form.appoint_period,
            is_agree_proxy: form.is_agree_proxy,
            contact: form.contact,
            is_reported_platform: form.is_reported_platform,
            follower_id: form.follower_id ?? null,
            ...(canEditStatus.value ? { repair_status: form.repair_status } : {}),
        };

    dialog.saving = true;
    try {
        if (dialog.isEdit) {
            await repairApi.update(dialog.editId, payload);
            ElMessage.success('已保存');
        } else {
            await repairApi.store(payload);
            ElMessage.success(form.follower_id ? `已添加，已通知${recipientsText.value}` : '已添加');
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
    if (isFollower.value) {
        ElMessage.warning('当前账号无删除权限（仅可修改维修情况）');
        return;
    }
    await ElMessageBox.confirm(`确定删除「${row.location}」的报修记录吗？`, '提示', { type: 'warning' });
    await repairApi.destroy(row.id);
    ElMessage.success('已删除');
    load(query.page);
}

async function onExport() {
    try {
        const res = await repairApi.export({
            location: query.location || undefined,
            category: query.category || undefined,
            date: query.date || undefined,
        });
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `报修管理导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        ElMessage.success('导出成功');
    } catch {
        ElMessage.error('导出失败，请重试');
    }
}

onMounted(async () => {
    const opt = await repairApi.options();
    categories.value = opt.data.categories || [];
    subMap.value = opt.data.sub_categories || {};
    statuses.value = opt.data.statuses || [];
    periods.value = opt.data.periods || [];
    followers.value = opt.data.followers || [];
    recipients.value = opt.data.repair_recipients || [];
    defaultFollowerId.value = opt.data.default_follower_id ?? null;
    canEditStatus.value = !!opt.data.can_edit_repair_status;
    isFollower.value = !!opt.data.is_repair_follower;
    load();
});
</script>

<style scoped>
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 16px;
}
.field-tip {
    margin-left: 10px;
    font-size: 12px;
    color: var(--text-muted);
}
.field-tip.line {
    display: block;
    margin-left: 0;
    margin-top: 4px;
    line-height: 1.5;
}
.qt-link.muted {
    color: var(--text-muted);
    cursor: not-allowed;
}
</style>
