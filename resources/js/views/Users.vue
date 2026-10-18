<template>
  <div class="qt-page">
    <!-- 搜索条（原型：姓名/工号 + 身份 + 查询 + 添加用户） -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">姓名/工号</span>
        <el-input v-model.trim="query.keyword" placeholder="请输入" clearable style="width:200px"
          @keyup.enter="search" />
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">身份</span>
        <el-select v-model="query.identity" placeholder="全部" clearable style="width:140px">
          <el-option v-for="o in dicts.identity" :key="o.value" :label="o.label" :value="o.value" />
        </el-select>
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;添加用户
      </el-button>
    </div>

    <!-- 用户列表 -->
    <div class="qt-table-wrap">
      <el-table :data="rows" v-loading="loading" style="width:100%">
        <el-table-column label="用户名" min-width="140">
          <template #default="{ row }">
            <div class="qt-flex">
              <span class="qt-avatar">{{ row.username.charAt(0) }}</span>
              <span>{{ row.username }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="employee_no" label="工号" width="90" />
        <el-table-column prop="phone" label="手机号" width="130" />
        <el-table-column label="权限" min-width="260">
          <template #default="{ row }">
            <span v-for="p in row.permLabels" :key="p" class="qt-chip">{{ p }}</span>
          </template>
        </el-table-column>
        <el-table-column label="身份" width="100">
          <template #default="{ row }">
            <el-tag :type="identityTagType(row.identity)" disable-transitions>{{ row.identity_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="150" />
        <el-table-column prop="updated_at" label="修改时间" width="150" />
        <el-table-column label="操作" width="130" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="暂无用户" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page"
          @current-change="(p) => load(p)" />
      </div>
    </div>

    <!-- 新增/编辑弹窗（需求 §3.4：宽 880px，权限内联平铺 3 列常开） -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑用户' : '新增用户'"
      width="880px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="用户名" prop="username">
              <el-input v-model.trim="form.username" placeholder="请输入用户名" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="工号" prop="employee_no">
              <el-input v-model.trim="form.employee_no" placeholder="请输入工号" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="手机号" prop="phone">
              <el-input v-model.trim="form.phone" placeholder="请输入手机号" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="登录密码" prop="password">
              <el-input v-model="form.password" type="password" show-password
                :placeholder="dialog.isEdit ? '编辑时留空则不修改' : '请输入登录密码（至少 6 位）'" />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="权限" prop="menu_keys">
              <div class="qt-perm-grid">
                <el-checkbox v-for="opt in PERMISSION_OPTIONS" :key="opt.label"
                  :model-value="isPermChecked(opt)" @change="(v) => togglePerm(opt, v)">
                  {{ opt.label }}
                </el-checkbox>
              </div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:6px;width:100%;">
                管理员默认为全部权限，可多选
              </div>
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="身份" prop="identity">
              <el-radio-group v-model="form.identity" @change="onIdentityChange">
                <el-radio v-for="o in dicts.identity" :key="o.value" :value="o.value">{{ o.label }}</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
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
import http from '../api';
import { useAuthStore } from '../stores/auth';
import { PERMISSION_OPTIONS, keysToPermissionLabels } from '../config/menus';
import QtIcon from '../components/QtIcon.vue';

const auth = useAuthStore();

// ---------- 列表 ----------
const rows = ref([]);
const total = ref(0);
const loading = ref(false);
const query = reactive({ keyword: '', identity: '', page: 1, pageSize: 10 });
const dicts = reactive({ identity: [] });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await http.get('/users', { params: query });
        rows.value = res.data.list.map((u) => ({
            ...u,
            permLabels: keysToPermissionLabels(u.menu_keys),
        }));
        total.value = res.data.total;
    } finally {
        loading.value = false;
    }
}

function search() {
    load(1);
}

function identityTagType(identity) {
    return { admin: 'danger', supervisor: 'warning', staff: 'info' }[identity] || 'info';
}

// ---------- 弹窗表单 ----------
const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, saving: false, editId: null });
const form = reactive({
    username: '', employee_no: '', phone: '', password: '',
    identity: 'staff', menu_keys: [],
});

const rules = {
    username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
    employee_no: [{ required: true, message: '请输入工号', trigger: 'blur' }],
    phone: [{ required: true, message: '请输入手机号', trigger: 'blur' }],
    password: [
        {
            validator: (r, v, cb) => {
                if (!dialog.isEdit && !v) return cb(new Error('请设置登录密码'));
                if (v && v.length < 6) return cb(new Error('密码至少 6 位'));
                cb();
            },
            trigger: 'blur',
        },
    ],
    identity: [{ required: true, message: '请选择身份', trigger: 'change' }],
    menu_keys: [
        {
            validator: (r, v, cb) => (v.length ? cb() : cb(new Error('请至少勾选一项菜单权限'))),
            trigger: 'change',
        },
    ],
};

/** 权限项勾选状态：该项全部 key 都在 menu_keys 里才算勾上 */
function isPermChecked(opt) {
    return opt.keys.every((k) => form.menu_keys.includes(k));
}

function togglePerm(opt, checked) {
    const set = new Set(form.menu_keys);
    opt.keys.forEach((k) => (checked ? set.add(k) : set.delete(k)));
    form.menu_keys = [...set];
    formRef.value?.validateField('menu_keys');
}

/** 全部菜单 key（新增默认全选——需求 §3.4 管理员新建用户时默认全部权限） */
function allKeys() {
    return PERMISSION_OPTIONS.flatMap((o) => o.keys);
}

/** 身份切到「管理员」时自动补全全部权限 */
function onIdentityChange(v) {
    if (v === 'admin') {
        form.menu_keys = [...new Set([...form.menu_keys, ...allKeys()])];
    }
}

function resetForm() {
    Object.assign(form, {
        username: '', employee_no: '', phone: '', password: '',
        identity: 'staff', menu_keys: allKeys(),
    });
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
        username: row.username,
        employee_no: row.employee_no,
        phone: row.phone || '',
        password: '',
        identity: row.identity,
        menu_keys: [...row.menu_keys],
    });
    dialog.visible = true;
}

async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        const payload = { ...form, password: form.password || undefined };
        if (dialog.isEdit) {
            await http.put(`/users/${dialog.editId}`, payload);
            ElMessage.success('已保存');
        } else {
            await http.post('/users', payload);
            ElMessage.success('已添加');
        }
        dialog.visible = false;
        load(query.page);
    } catch (e) {
        // 422：后端 message 已提示，这里把字段错误映射到表单
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
    if (row.id === auth.user?.id) {
        return ElMessage.warning('不能删除当前登录账号');
    }
    await ElMessageBox.confirm(`确定删除用户「${row.username}」吗？`, '提示', { type: 'warning' });
    await http.delete(`/users/${row.id}`);
    ElMessage.success('已删除');
    load(query.page);
}

onMounted(async () => {
    load();
    const res = await http.get('/dicts');
    dicts.identity = res.data.identity;
});
</script>
