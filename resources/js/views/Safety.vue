<template>
  <div class="qt-page">
    <!-- 筛选栏（原型：类别多选 / 楼层 / 检查结果 / 整改状态 / 日期，无导入导出） -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">检查类别</span>
        <el-select v-model="query.categories" multiple collapse-tags collapse-tags-tooltip
          placeholder="全部" clearable style="min-width:220px" @change="search">
          <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">楼层</span>
        <el-select v-model="query.floor" placeholder="全部" clearable style="width:110px" @change="search">
          <el-option v-for="f in floors" :key="f" :label="`${f}层`" :value="f" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">检查结果</span>
        <el-select v-model="query.result" placeholder="全部" clearable style="width:110px" @change="search">
          <el-option v-for="r in results" :key="r.value" :label="r.label" :value="r.value" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">整改状态</span>
        <el-select v-model="query.rectify" placeholder="全部" clearable style="width:110px" @change="search">
          <el-option label="已完成" value="done" />
          <el-option label="未完成" value="pending" />
        </el-select>
      </div>
      <div class="qt-filter">
        <span class="qt-filter-label">日期</span>
        <el-date-picker v-model="query.date" type="date" value-format="YYYY-MM-DD" placeholder="请选择"
          style="width:150px" @change="search" />
      </div>
      <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
      <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出 Excel</el-button>
      <el-button @click="reset"><QtIcon name="refresh" :size="13" />&nbsp;重置</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="openCreate">
        <QtIcon name="plus" :size="13" />&nbsp;新增检查
      </el-button>
    </div>

    <div class="qt-table-wrap">
      <!-- 表格（原型：日期 / 检查类别 / 分类字段详情 / 创建修改时间 / 操作） -->
      <el-table :data="pagedRows" v-loading="loading" style="width:100%">
        <el-table-column prop="check_date" label="日期" width="105" />
        <el-table-column label="检查类别" width="150">
          <template #default="{ row }">
            <el-tag type="info" disable-transitions>{{ row.category_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="分类字段详情" min-width="420">
          <template #default="{ row }">
            <div class="dl-fields">
              <span v-for="f in detailFields(row)" :key="f.k" class="dl-kv">
                <span class="dl-k">{{ f.k }}</span><span>{{ f.v }}</span>
              </span>
              <span v-if="row.images?.length" class="dl-kv">
                <span class="dl-k">现场图片</span>
                <span class="img-list">
                  <el-image v-for="(img, i) in row.images" :key="i" :src="img" :preview-src-list="row.images"
                    fit="cover" class="thumb" :preview-teleported="true" />
                </span>
              </span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="150" />
        <el-table-column prop="updated_at" label="修改时间" width="150" />
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
            <a class="qt-link danger" @click="onDelete(row)"><QtIcon name="trash" :size="13" /> 删除</a>
          </template>
        </el-table-column>
        <template #empty><el-empty description="无匹配记录" /></template>
      </el-table>

      <div style="display:flex;justify-content:flex-end;margin-top:14px;">
        <el-pagination background layout="total, prev, pager, next" :total="total"
          :page-size="query.pageSize" :current-page="query.page" @current-change="(p) => (query.page = p)" />
      </div>
    </div>

    <!-- 新增 / 编辑（原型：弹窗首字段「检查类别」独占整行，下拉切换字段组） -->
    <el-dialog v-model="dialog.visible" :title="dialog.isEdit ? '编辑安全检查' : '新增安全检查登记'"
      width="800px" top="5vh" class="safety-dialog" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
        <el-form-item label="检查类别" prop="category">
          <el-select v-model="form.category" placeholder="请选择" style="width:100%"
            @change="onCategoryChange">
            <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
          </el-select>
        </el-form-item>

        <!-- ===== 检查类（宿舍消防安全检查：逐房间用电安全表单，对齐原型） ===== -->
        <template v-if="kind === 'check'">
          <div class="form-grid-2">
            <el-form-item label="日期" prop="check_date">
              <el-date-picker v-model="form.check_date" type="date" value-format="YYYY-MM-DD" placeholder="请选择"
                style="width:100%" />
            </el-form-item>
            <el-form-item label="房间号" prop="room_id">
              <el-select v-model="form.room_id" filterable placeholder="请选择房间号" style="width:100%">
                <el-option v-for="r in rooms" :key="r.id" :label="r.room_no" :value="r.id" />
              </el-select>
            </el-form-item>
          </div>
          <el-form-item label="检查人员" prop="inspector_ids">
            <el-select v-model="form.inspector_ids" multiple collapse-tags collapse-tags-tooltip
              placeholder="可多选" style="width:100%">
              <el-option v-for="u in users" :key="u.id" :label="u.name" :value="u.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="外来排查人员" prop="external_inspector">
            <el-input v-model.trim="form.external_inspector" placeholder="如：物业外包人员张三" />
          </el-form-item>

          <div class="check-list-title">用电安全检查项</div>
          <div class="check-list">
            <div v-for="it in fireItems" :key="it.key" class="check-row">
              <span class="check-label required">{{ it.label }}</span>
              <el-radio-group v-model="form[it.key]">
                <el-radio :value="true">是</el-radio>
                <el-radio :value="false">否</el-radio>
              </el-radio-group>
            </div>
          </div>

          <div class="form-grid-2" style="margin-top:12px;">
            <el-form-item label="检查结果" prop="result">
              <el-radio-group v-model="form.result" @change="onResultChange">
                <el-radio value="normal">正常</el-radio>
                <el-radio value="abnormal">异常</el-radio>
              </el-radio-group>
            </el-form-item>
            <el-form-item label="整改状态">
              <el-radio-group v-model="form.rectify" :disabled="form.result !== 'abnormal'">
                <el-radio value="done">已完成</el-radio>
                <el-radio value="pending">未完成</el-radio>
              </el-radio-group>
            </el-form-item>
          </div>
          <el-form-item label="备注" prop="remark">
            <el-input v-model.trim="form.remark" type="textarea" :rows="2" placeholder="选填" />
          </el-form-item>
        </template>

        <!-- ===== 事件类字段（公共秩序/财产安全/人身安全/环境卫生安全；突发类多一个「事件类别」） ===== -->
        <template v-else>
          <div class="form-grid-2">
            <el-form-item label="日期" prop="check_date">
              <el-date-picker v-model="form.check_date" type="date" value-format="YYYY-MM-DD" placeholder="请选择"
                style="width:100%" />
            </el-form-item>
            <el-form-item v-if="kind === 'emergency'" label="事件类别" prop="event_category">
              <el-select v-model="form.event_category" placeholder="请选择" style="width:100%">
                <el-option v-for="e in eventCategories" :key="e.value" :label="e.label" :value="e.value" />
              </el-select>
            </el-form-item>
          </div>
          <el-form-item label="事件描述" prop="event_desc">
            <el-input v-model.trim="form.event_desc" type="textarea" :rows="2" placeholder="请输入事件描述" />
          </el-form-item>
          <div class="form-grid-2">
            <el-form-item label="时间" prop="event_time">
              <el-date-picker v-model="form.event_time" type="datetime" value-format="YYYY-MM-DD HH:mm"
                placeholder="如：2026-08-24 20:30" style="width:100%" />
            </el-form-item>
            <el-form-item label="地点" prop="place">
              <el-input v-model.trim="form.place" placeholder="如：A栋大堂" />
            </el-form-item>
            <el-form-item label="人物" prop="person">
              <el-input v-model.trim="form.person" placeholder="请输入涉及人物" />
            </el-form-item>
          </div>
          <el-form-item label="经过" prop="process">
            <el-input v-model.trim="form.process" type="textarea" :rows="3" placeholder="请输入事件经过" />
          </el-form-item>
          <el-form-item label="图片">
            <el-upload list-type="picture-card" :file-list="fileList" :http-request="uploadImage"
              :on-remove="removeImage" accept="image/jpeg,image/png">
              <QtIcon name="plus" :size="16" />
            </el-upload>
            <div class="hint">支持 jpg / png，最多 9 张</div>
          </el-form-item>
        </template>
      </el-form>
      <template #footer>
        <el-button @click="dialog.visible = false">取消</el-button>
        <el-button type="primary" @click="onSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { safetyApi } from '../api/safety';
import QtIcon from '../components/QtIcon.vue';

const rawRows = ref([]);
const total = ref(0);
const loading = ref(false);
const categories = ref([]);
const results = ref([]);
const eventCategories = ref([]);
const users = ref([]);
const rooms = ref([]);
const floors = ref([]);
const fireItems = ref([]);

const query = reactive({ categories: [], floor: '', result: '', rectify: '', date: '', page: 1, pageSize: 10 });

const kindMap = computed(() => Object.fromEntries(categories.value.map((c) => [c.value, c.kind])));

const pagedRows = computed(() => {
    const start = (query.page - 1) * query.pageSize;
    return rawRows.value.slice(start, start + query.pageSize);
});

async function load() {
    loading.value = true;
    try {
        const params = {
            result: query.result || undefined,
            rectify: query.rectify || undefined,
            floor: query.floor || undefined,
            date: query.date || undefined,
            page: 1, pageSize: 500,
        };
        if (query.categories.length) params.categories = query.categories;
        const res = await safetyApi.list(params);
        rawRows.value = res.data.list || [];
        total.value = rawRows.value.length;
        if (query.page > 1 && (query.page - 1) * query.pageSize >= rawRows.value.length) query.page = 1;
    } finally {
        loading.value = false;
    }
}
function search() {
    query.page = 1;
    load();
}

async function onExport() {
    try {
        const res = await safetyApi.export({
            categories: query.categories.length ? query.categories : undefined,
            floor: query.floor || undefined,
            result: query.result || undefined,
            rectify: query.rectify || undefined,
            date: query.date || undefined,
        });
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `安全检查导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    Object.assign(query, { categories: [], floor: '', result: '', rectify: '', date: '', page: 1 });
    load();
}

/** 行内「分类字段详情」（原型 dl-fields 口径：字段值直接铺在行内） */
function detailFields(row) {
    const out = [];
    if (row.kind === 'check') {
        out.push({ k: '房间号', v: row.room_no || '—' });
        fireItems.value.forEach((it) => {
            const cell = row.fire_items?.[it.key];
            out.push({ k: it.label, v: cell?.text ?? '—' });
        });
        out.push({ k: '检查结果', v: row.result_label || '—' });
        if (row.result === 'abnormal') out.push({ k: '整改状态', v: row.rectify_label || '—' });
        out.push({ k: '外来排查人员', v: row.external_inspector || '—' });
        out.push({ k: '备注', v: row.remark || '—' });
        out.push({ k: '检查人员', v: row.inspectors_text || '—' });
        return out;
    }
    if (row.kind === 'emergency') out.push({ k: '事件类别', v: row.event_category_label || '—' });
    out.push(
        { k: '事件描述', v: row.event_desc || '—' },
        { k: '时间', v: row.event_time || '—' },
        { k: '地点', v: row.place || '—' },
        { k: '人物', v: row.person || '—' },
        { k: '经过', v: row.process || '—' },
    );
    return out;
}

/* ---------- 新增 / 编辑 ---------- */
const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, editId: null });
const form = reactive({
    category: '', check_date: '',
    room_id: null, external_inspector: '', result: 'normal', rectify: '', remark: '', inspector_ids: [],
    event_category: '', event_desc: '', event_time: '', place: '', person: '', process: '', images: [],
});
const fileList = ref([]);

const kind = computed(() => kindMap.value[form.category] || 'check');

const rules = computed(() => {
    const base = {
        category: [{ required: true, message: '请选择检查类别', trigger: 'change' }],
        check_date: [{ required: true, message: '请选择日期', trigger: 'change' }],
    };
    if (kind.value === 'check') {
        return {
            ...base,
            room_id: [{ required: true, message: '请选择房间号', trigger: 'change' }],
            inspector_ids: [{ required: true, type: 'array', min: 1, message: '请选择检查人员', trigger: 'change' }],
            result: [{ required: true, message: '请选择检查结果', trigger: 'change' }],
        };
    }
    const rules2 = {
        ...base,
        event_desc: [{ required: true, message: '请填写事件描述', trigger: 'blur' }],
        event_time: [{ required: true, message: '请选择时间', trigger: 'change' }],
    };
    if (kind.value === 'emergency') {
        rules2.event_category = [{ required: true, message: '请选择事件类别', trigger: 'change' }];
    }
    return rules2;
});

function today() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function blankCheckFields() {
    const fields = { room_id: null, external_inspector: '', result: 'normal', rectify: '', remark: '', inspector_ids: [] };
    fireItems.value.forEach((it) => { fields[it.key] = false; });
    return fields;
}

function openCreate() {
    dialog.isEdit = false;
    dialog.editId = null;
    Object.assign(form, {
        category: categories.value[0]?.value || '', check_date: today(),
        ...blankCheckFields(),
        event_category: '', event_desc: '', event_time: '', place: '', person: '', process: '', images: [],
    });
    fileList.value = [];
    dialog.visible = true;
}

function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    const fields = blankCheckFields();
    if (row.kind === 'check') {
        fields.room_id = row.room_id;
        fields.external_inspector = row.external_inspector || '';
        fields.result = row.result || 'normal';
        fields.rectify = row.rectify || '';
        fields.remark = row.remark || '';
        fields.inspector_ids = row.inspector_ids || [];
        fireItems.value.forEach((it) => {
            fields[it.key] = row.fire_items?.[it.key]?.value ?? false;
        });
    }
    Object.assign(form, {
        category: row.category,
        check_date: row.check_date,
        ...fields,
        event_category: row.event_category || '',
        event_desc: row.event_desc || '',
        event_time: row.event_time || '',
        place: row.place || '',
        person: row.person || '',
        process: row.process || '',
        images: row.images || [],
    });
    fileList.value = (row.images || []).map((url, i) => ({ name: `图片${i + 1}`, url }));
    dialog.visible = true;
}

/** 切换类别重渲字段组（原型行为：切换后另一套字段清空，互不污染） */
function onCategoryChange() {
    fileList.value = [];
    form.images = [];
    Object.assign(form, blankCheckFields(), {
        event_category: '', event_desc: '', event_time: '', place: '', person: '', process: '',
    });
}

/** 检查结果 = 正常时，整改状态禁用并清空（原型联动） */
function onResultChange(v) {
    if (v === 'normal') form.rectify = '';
}

async function uploadImage({ file, onSuccess, onError }) {
    try {
        const fd = new FormData();
        fd.append('file', file);
        const res = await safetyApi.upload(fd);
        form.images = [...(form.images || []), res.data.name];
        fileList.value = form.images.map((n, i) => ({
            name: `图片${i + 1}`,
            url: `/api/safety-checks/image?name=${n}`,
        }));
        onSuccess?.(res.data);
    } catch (e) {
        ElMessage.error('图片上传失败');
        onError?.(e);
    }
}

function removeImage(file) {
    const name = (file?.url || '').split('name=')[1];
    form.images = (form.images || []).filter((n) => n !== name);
}

function payload() {
    if (kind.value === 'check') {
        const data = {
            category: form.category, check_date: form.check_date,
            room_id: form.room_id,
            external_inspector: form.external_inspector || null,
            result: form.result,
            rectify: form.result === 'abnormal' ? (form.rectify || 'pending') : null,
            remark: form.remark || null,
            inspector_ids: form.inspector_ids,
        };
        fireItems.value.forEach((it) => { data[it.key] = form[it.key]; });
        return data;
    }
    const data = {
        category: form.category, check_date: form.check_date,
        event_desc: form.event_desc, event_time: form.event_time,
        place: form.place || null, person: form.person || null, process: form.process || null,
        images: form.images,
    };
    if (kind.value === 'emergency') {
        data.event_category = form.event_category;
    }
    return data;
}

async function onSave() {
    await formRef.value.validate();
    try {
        if (dialog.isEdit) {
            await safetyApi.update(dialog.editId, payload());
            ElMessage.success('已保存');
        } else {
            await safetyApi.store(payload());
            ElMessage.success('已添加');
        }
        dialog.visible = false;
        load();
    } catch (e) {
        ElMessage.error(e?.data?.message || '保存失败');
    }
}

async function onDelete(row) {
    await ElMessageBox.confirm(`确定删除这条${row.category_label}记录吗？`, '提示', { type: 'warning' });
    await safetyApi.destroy(row.id);
    ElMessage.success('已删除');
    load();
}

onMounted(async () => {
    const opt = await safetyApi.options();
    categories.value = opt.data.categories || [];
    results.value = opt.data.results || [];
    eventCategories.value = opt.data.event_categories || [];
    users.value = opt.data.users || [];
    rooms.value = opt.data.rooms || [];
    floors.value = opt.data.floors || [];
    fireItems.value = opt.data.fire_items || [];
    load();
});
</script>

<style scoped>
.dl-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 24px;
    font-size: 13px;
    line-height: 1.8;
}
.dl-kv {
    display: inline-flex;
    gap: 8px;
}
.dl-k {
    color: var(--text-muted);
    flex-shrink: 0;
}
.img-list {
    display: flex;
    gap: 6px;
}
.thumb {
    width: 42px;
    height: 42px;
    border-radius: 4px;
}
/* 用电安全检查项（原型同款标题 + 边框圆角盒；为免滚动改两列布局，每项 label 居左、radio 靠行右端） */
.check-list-title {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin: 2px 0 6px;
    padding-left: 2px;
}
.check-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    column-gap: 24px;
    border: 1px solid #e8eaed;
    border-radius: 8px;
    padding: 2px 16px;
    background: #fafbfc;
}
.check-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px dashed #eef0f2;
}
.check-row:nth-last-child(-n+2) {
    border-bottom: none;
}
.check-label {
    font-size: 13px;
    font-weight: 500;
    color: #1f2937;
}
.check-label.required::before {
    content: '*';
    color: var(--el-color-danger);
    margin-right: 4px;
}
/* 弹窗紧凑化：保证一屏内看到保存按钮 */
:deep(.safety-dialog .el-dialog__header) {
    padding-bottom: 12px;
}
:deep(.safety-dialog .el-dialog__body) {
    padding-top: 10px;
    padding-bottom: 10px;
}
:deep(.safety-dialog .el-form-item) {
    margin-bottom: 14px;
}
:deep(.safety-dialog .el-dialog__footer) {
    padding-top: 8px;
}
.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 16px;
}
.hint {
    margin-top: 10px;
    font-size: 12px;
    color: var(--text-muted);
}
</style>
