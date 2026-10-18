<template>
  <div class="qt-page">
    <!-- ================= 视图 1：房间列表 ================= -->
    <template v-if="view === 'list'">
      <div class="qt-filter-bar">
        <div class="qt-filter">
          <span class="qt-filter-label">房间号</span>
          <el-input v-model.trim="query.room_no" placeholder="请输入" clearable style="width:160px" @keyup.enter="search" />
        </div>
        <div class="qt-filter">
          <span class="qt-filter-label">状态</span>
          <el-select v-model="query.full_status" placeholder="全部" clearable style="width:120px">
            <el-option label="已满" value="full" />
            <el-option label="有空床" value="available" />
          </el-select>
        </div>
        <div class="qt-filter">
          <span class="qt-filter-label">类型</span>
          <el-select v-model="query.biz_type" placeholder="全部" clearable style="width:120px">
            <el-option label="入住" value="checkin" />
            <el-option label="退宿" value="checkout" />
            <el-option label="调宿" value="transfer" />
          </el-select>
        </div>
        <el-button type="primary" @click="search"><QtIcon name="search" :size="13" />&nbsp;查询</el-button>
        <el-button @click="onExport"><QtIcon name="download" :size="13" />&nbsp;导出</el-button>
        <el-button type="primary" style="margin-left:auto;" @click="openCreate('checkin')">
          <QtIcon name="plus" :size="13" />&nbsp;住宿登记
        </el-button>
      </div>

      <div class="qt-table-wrap">
        <el-table :data="rows" v-loading="loading" style="width:100%">
          <el-table-column label="房间号" min-width="150">
            <template #default="{ row }">
              <a class="qt-link" style="font-weight:500;" @click="openCreate('checkin', row)">{{ row.room_no }}</a>
            </template>
          </el-table-column>
          <el-table-column prop="count_text" label="入住人数 / 床位数" min-width="150" />
          <el-table-column label="状态是否满" min-width="120">
            <template #default="{ row }">
              <el-tag :type="row.is_full ? 'success' : 'primary'" disable-transitions>{{ row.status_text }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" min-width="150" />
          <el-table-column prop="updated_at" label="修改时间" min-width="150" />
          <el-table-column label="操作" width="220" fixed="right">
            <template #default="{ row }">
              <a class="qt-link" @click="openDetail(row)"><QtIcon name="eye" :size="13" /> 详情</a>
              <a class="qt-link" @click="openHistory(row)"><QtIcon name="history" :size="13" /> 历史</a>
              <a class="qt-link" @click="openCreate('checkin', row)"><QtIcon name="plus" :size="13" /> 入住</a>
            </template>
          </el-table-column>
          <template #empty><el-empty description="暂无房间" /></template>
        </el-table>

        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
          <el-pagination background layout="total, prev, pager, next" :total="total"
            :page-size="query.pageSize" :current-page="query.page"
            @current-change="(p) => load(p)" />
        </div>
      </div>
    </template>

    <!-- ================= 视图 2：房间详情（当前在住） ================= -->
    <template v-else-if="view === 'detail'">
      <div style="display:flex;gap:10px;margin-bottom:14px;">
        <el-button @click="backToList"><QtIcon name="back" :size="13" />&nbsp;返回房间列表</el-button>
        <el-button style="margin-left:auto;" @click="openHistory(currentRoom)"><QtIcon name="history" :size="13" />&nbsp;查看历史记录</el-button>
        <el-button type="primary" @click="openCreate('checkin', currentRoom)"><QtIcon name="plus" :size="13" />&nbsp;新入住</el-button>
      </div>

      <div class="qt-table-wrap">
        <div class="room-head">
          <QtIcon name="home" :size="17" />
          <span>{{ currentRoom.room_no }}</span>
          <span class="room-sep">|</span><span>床位数 {{ currentRoom.beds }}</span>
          <span class="room-sep">|</span><span>当前在住 {{ detailRows.length }} 人</span>
          <span class="room-sep">|</span>
          <el-tag :type="detailRows.length >= currentRoom.beds ? 'success' : 'primary'" disable-transitions>
            {{ detailRows.length >= currentRoom.beds ? '已满' : '有空床' }}
          </el-tag>
        </div>
        <div class="room-tip">展示该房间当前在住学生；每行操作列均为 编辑 / 调宿 / 退宿 / 交接。</div>

        <el-table :data="detailRows" v-loading="loading" style="width:100%">
          <el-table-column label="业务类型" width="100">
            <template #default="{ row }">
              <el-tag :type="row.biz_type_tag" disable-transitions>{{ row.biz_type_label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="student_no" label="学号" width="110" />
          <el-table-column prop="name" label="姓名" width="100" />
          <el-table-column prop="gender_label" label="性别" width="70" />
          <el-table-column prop="room_no" label="房间号" width="120" />
          <el-table-column label="原房间号" width="120">
            <template #default="{ row }"><span>{{ row.original_room_no || '—' }}</span></template>
          </el-table-column>
          <el-table-column label="入住时间" width="110">
            <template #default="{ row }"><span>{{ row.check_in_at || '—' }}</span></template>
          </el-table-column>
          <el-table-column label="退宿时间" width="110">
            <template #default="{ row }">
              <el-tag v-if="!row.check_out_at" type="success" disable-transitions>在住</el-tag>
              <span v-else>{{ row.check_out_at }}</span>
            </template>
          </el-table-column>
          <el-table-column label="安全员责任书" width="120">
            <template #default="{ row }"><ValueTag :value="row.safety_duty_label" good="已签" /></template>
          </el-table-column>
          <el-table-column label="家具确认单" width="110">
            <template #default="{ row }"><ValueTag :value="row.furniture_confirm_label" good="已签" /></template>
          </el-table-column>
          <el-table-column label="外国人住宿登记" width="130">
            <template #default="{ row }"><ValueTag :value="row.foreigner_label" good="已登记" /></template>
          </el-table-column>
          <el-table-column label="床垫" width="80">
            <template #default="{ row }"><ValueTag :value="row.mattress_label" good="已发" /></template>
          </el-table-column>
          <el-table-column label="床品" width="80">
            <template #default="{ row }"><ValueTag :value="row.bedding_label" good="已购" /></template>
          </el-table-column>
          <el-table-column label="门禁系统" width="100">
            <template #default="{ row }"><ValueTag :value="row.access_control_label" good="已授权" /></template>
          </el-table-column>
          <el-table-column label="住宿协议状态" width="120">
            <template #default="{ row }"><ValueTag :value="row.agreement_label" good="已签" /></template>
          </el-table-column>
          <el-table-column label="手续办理是否完成" width="140">
            <template #default="{ row }"><ValueTag :value="row.is_procedure_done_label" good="是" /></template>
          </el-table-column>
          <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip>
            <template #default="{ row }"><span>{{ row.remark || '—' }}</span></template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="150" />
          <el-table-column prop="updated_at" label="修改时间" width="150" />
          <el-table-column label="操作" width="230" fixed="right">
            <template #default="{ row }">
              <a class="qt-link" @click="openEdit(row)"><QtIcon name="edit" :size="13" /> 编辑</a>
              <a class="qt-link" @click="openTransfer(row)"><QtIcon name="shift" :size="13" /> 调宿</a>
              <a class="qt-link danger" @click="openCheckout(row)"><QtIcon name="close" :size="13" /> 退宿</a>
              <a v-if="row.can_hand_over" class="qt-link" @click="onHandover(row)">
                <QtIcon name="refresh" :size="13" /> 交接
              </a>
              <span v-else class="qt-link muted"><QtIcon name="refresh" :size="13" /> 交接</span>
            </template>
          </el-table-column>
          <template #empty><el-empty description="该房间暂无在住学生" /></template>
        </el-table>
      </div>
    </template>

    <!-- ================= 视图 3：历史记录 ================= -->
    <template v-else>
      <div style="display:flex;gap:10px;margin-bottom:14px;">
        <el-button @click="backToList"><QtIcon name="back" :size="13" />&nbsp;返回房间列表</el-button>
        <el-button @click="openDetail(currentRoom)"><QtIcon name="home" :size="13" />&nbsp;查看房间详情</el-button>
      </div>

      <div class="qt-table-wrap">
        <div class="room-head"><QtIcon name="history" :size="17" /><span>{{ currentRoom.room_no }} · 历史住宿记录（住过的学生）</span></div>
        <div class="room-tip">展示曾在该房间住宿、已退宿或调宿离开的学生。</div>

        <el-table :data="historyRows" v-loading="loading" style="width:100%">
          <el-table-column label="业务类型" width="100">
            <template #default="{ row }">
              <el-tag :type="row.biz_type_tag" disable-transitions>{{ row.biz_type_label }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="student_no" label="学号" width="110" />
          <el-table-column prop="name" label="姓名" width="100" />
          <el-table-column prop="gender_label" label="性别" width="70" />
          <el-table-column label="原房间号" width="120">
            <template #default="{ row }"><span>{{ row.original_room_no || '—' }}</span></template>
          </el-table-column>
          <el-table-column label="入住时间" width="110">
            <template #default="{ row }"><span>{{ row.check_in_at || '—' }}</span></template>
          </el-table-column>
          <el-table-column label="退宿时间" width="110">
            <template #default="{ row }"><span>{{ row.check_out_at || '—' }}</span></template>
          </el-table-column>
          <el-table-column label="安全员责任书" width="120">
            <template #default="{ row }"><ValueTag :value="row.safety_duty_label" good="已签" /></template>
          </el-table-column>
          <el-table-column label="家具确认单" width="110">
            <template #default="{ row }"><ValueTag :value="row.furniture_confirm_label" good="已签" /></template>
          </el-table-column>
          <el-table-column label="外国人住宿登记" width="130">
            <template #default="{ row }"><ValueTag :value="row.foreigner_label" good="已登记" /></template>
          </el-table-column>
          <el-table-column label="床垫" width="80">
            <template #default="{ row }"><ValueTag :value="row.mattress_label" good="已发" /></template>
          </el-table-column>
          <el-table-column label="床品" width="80">
            <template #default="{ row }"><ValueTag :value="row.bedding_label" good="已购" /></template>
          </el-table-column>
          <el-table-column label="手续办理是否完成" width="140">
            <template #default="{ row }"><ValueTag :value="row.is_procedure_done_label" good="是" /></template>
          </el-table-column>
          <el-table-column label="个人物品是否清理完成" width="170">
            <template #default="{ row }"><ValueTag :value="row.is_belongings_cleared_label" good="是" /></template>
          </el-table-column>
          <el-table-column label="门禁系统" width="100">
            <template #default="{ row }"><ValueTag :value="row.access_control_label" good="已授权" /></template>
          </el-table-column>
          <el-table-column label="住宿协议状态" width="120">
            <template #default="{ row }"><ValueTag :value="row.agreement_label" good="已签" /></template>
          </el-table-column>
          <el-table-column prop="remark" label="备注" min-width="160" show-overflow-tooltip>
            <template #default="{ row }"><span>{{ row.remark || '—' }}</span></template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="150" />
          <el-table-column prop="updated_at" label="修改时间" width="150" />
          <template #empty><el-empty description="该房间暂无历史住宿记录" /></template>
        </el-table>
      </div>
    </template>

    <!-- ================= 登记弹窗（入住 / 调宿 / 退宿） ================= -->
    <el-dialog v-model="dialog.visible" :title="dialogTitle" width="720px"
      :close-on-click-modal="false" destroy-on-close>
      <el-tabs v-if="dialog.showTabs" v-model="form.biz_type">
        <el-tab-pane label="入住" name="checkin" />
        <el-tab-pane label="调宿" name="transfer" />
        <el-tab-pane label="退宿" name="checkout" />
      </el-tabs>

      <el-form ref="formRef" :model="form" :rules="rules" label-width="130px">
        <el-form-item label="学号" prop="student_no">
          <el-input v-model.trim="form.student_no" placeholder="请输入学号" />
        </el-form-item>
        <el-form-item label="姓名" prop="name">
          <el-input v-model.trim="form.name" placeholder="请输入姓名" />
        </el-form-item>

        <!-- 入住 / 退宿：房间号 -->
        <el-form-item v-if="form.biz_type !== 'transfer'" label="房间号" prop="room_id">
          <el-select v-model="form.room_id" placeholder="请选择房间号" :disabled="form.lockRoom" style="width:100%">
            <el-option v-for="r in rooms" :key="r.id" :label="r.room_no" :value="r.id" />
          </el-select>
        </el-form-item>

        <!-- 调宿：原房间号 + 新房间号 -->
        <template v-else>
          <el-form-item label="原房间号">
            <el-select v-model="form.original_room_id" disabled style="width:100%">
              <el-option v-for="r in rooms" :key="r.id" :label="r.room_no" :value="r.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="新房间号" prop="room_id">
            <el-select v-model="form.room_id" placeholder="请选择新房间号" style="width:100%">
              <el-option v-for="r in rooms" :key="r.id" :label="r.room_no" :value="r.id" />
            </el-select>
          </el-form-item>
        </template>

        <!-- 时间字段 -->
        <el-form-item v-if="form.biz_type === 'checkout'" label="入住时间">
          <el-date-picker v-model="form.check_in_at" type="date" value-format="YYYY-MM-DD" placeholder="可选" style="width:100%" />
        </el-form-item>
        <el-form-item v-if="form.biz_type === 'checkout'" label="退宿时间" prop="check_out_at">
          <el-date-picker v-model="form.check_out_at" type="date" value-format="YYYY-MM-DD" placeholder="请选择退宿时间" style="width:100%" />
        </el-form-item>
        <el-form-item v-else :label="form.biz_type === 'transfer' ? '调宿时间' : '入住时间'" prop="check_in_at">
          <el-date-picker v-model="form.check_in_at" type="date" value-format="YYYY-MM-DD"
            :placeholder="form.biz_type === 'transfer' ? '请选择调宿时间' : '请选择入住时间'" style="width:100%" />
        </el-form-item>

        <el-form-item label="性别" prop="gender">
          <el-radio-group v-model="form.gender">
            <el-radio value="male">男</el-radio>
            <el-radio value="female">女</el-radio>
          </el-radio-group>
        </el-form-item>

        <!-- 入住专属字段 -->
        <template v-if="form.biz_type === 'checkin'">
          <el-form-item label="安全员责任书" prop="safety_duty_status">
            <el-radio-group v-model="form.safety_duty_status">
              <el-radio value="signed">已签</el-radio><el-radio value="unsigned">未签</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="家具确认单" prop="furniture_confirm">
            <el-radio-group v-model="form.furniture_confirm">
              <el-radio value="signed">已签</el-radio><el-radio value="unsigned">未签</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="外国人住宿登记" prop="foreigner_registration">
            <el-radio-group v-model="form.foreigner_registration">
              <el-radio value="registered">已登记</el-radio><el-radio value="unregistered">未登记</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="床垫" prop="mattress">
            <el-radio-group v-model="form.mattress">
              <el-radio value="issued">已发</el-radio><el-radio value="unissued">未发</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="床品" prop="bedding">
            <el-radio-group v-model="form.bedding">
              <el-radio value="purchased">已购</el-radio><el-radio value="unpurchased">未购</el-radio>
            </el-radio-group>
          </el-form-item>
        </template>

        <!-- 退宿 / 调宿专属字段 -->
        <template v-else>
          <el-form-item v-if="form.biz_type === 'checkout'" label="退宿转单" prop="has_checkout_order">
            <el-radio-group v-model="form.has_checkout_order">
              <el-radio :value="true">有</el-radio><el-radio :value="false">无</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item v-else label="调整申请" prop="has_transfer_application">
            <el-radio-group v-model="form.has_transfer_application">
              <el-radio :value="true">有</el-radio><el-radio :value="false">无</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="个人物品是否清理完成" prop="is_belongings_cleared">
            <el-radio-group v-model="form.is_belongings_cleared">
              <el-radio :value="true">是</el-radio><el-radio :value="false">否</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="家具" prop="furniture_state">
            <el-radio-group v-model="form.furniture_state">
              <el-radio value="complete">齐全完好</el-radio>
              <el-radio value="missing">家具缺失</el-radio>
              <el-radio value="damaged">家具破损</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="门禁系统" prop="access_control">
            <el-radio-group v-model="form.access_control">
              <el-radio value="authorized">已授权</el-radio><el-radio value="unauthorized">未授权</el-radio>
            </el-radio-group>
          </el-form-item>
        </template>

        <el-form-item label="住宿协议状态" prop="agreement_status">
          <el-radio-group v-model="form.agreement_status">
            <el-radio value="signed">已签</el-radio><el-radio value="unsigned">未签</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="手续办理是否完成" prop="is_procedure_done">
          <el-radio-group v-model="form.is_procedure_done">
            <el-radio :value="true">是</el-radio><el-radio :value="false">否</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model.trim="form.remark" type="textarea" :rows="2" placeholder="选填" />
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
import { computed, h, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox, ElTag } from 'element-plus';
import { accommodationApi } from '../api/accommodation';
import { roomApi } from '../api/room';
import QtIcon from '../components/QtIcon.vue';

/** 值标签：好值绿、坏值黄、空显示 — */
const ValueTag = (props) => {
    if (!props.value) return h('span', { class: 'cell-muted' }, '—');
    return h(ElTag, {
        type: props.value === props.good ? 'success' : 'warning',
        disableTransitions: true,
        size: 'small',
    }, () => props.value);
};
ValueTag.props = ['value', 'good'];

const view = ref('list');
const rows = ref([]);
const detailRows = ref([]);
const historyRows = ref([]);
const total = ref(0);
const loading = ref(false);
const rooms = ref([]);
const currentRoom = ref({ id: null, room_no: '', beds: 0 });

const query = reactive({ room_no: '', full_status: '', biz_type: '', page: 1, pageSize: 10 });

async function load(page = 1) {
    query.page = page;
    loading.value = true;
    try {
        const res = await accommodationApi.list(query);
        rows.value = res.data.list || [];
        total.value = res.data.total || 0;
    } finally {
        loading.value = false;
    }
}
function search() { load(1); }

function openDetail(row) {
    currentRoom.value = { id: row.id, room_no: row.room_no, beds: row.beds };
    view.value = 'detail';
    loadDetail();
}
function openHistory(row) {
    currentRoom.value = { id: row.id, room_no: row.room_no, beds: row.beds };
    view.value = 'history';
    loadHistory();
}
function backToList() {
    view.value = 'list';
    load(query.page);
}

async function loadDetail() {
    loading.value = true;
    try {
        const res = await accommodationApi.detail(currentRoom.value.id);
        detailRows.value = res.data.list || [];
        currentRoom.value.beds = res.data.room?.beds ?? currentRoom.value.beds;
    } finally {
        loading.value = false;
    }
}
async function loadHistory() {
    loading.value = true;
    try {
        const res = await accommodationApi.history(currentRoom.value.id);
        historyRows.value = res.data.list || [];
    } finally {
        loading.value = false;
    }
}

// ============ 登记弹窗 ============
const formRef = ref();
const dialog = reactive({ visible: false, isEdit: false, saving: false, editId: null, showTabs: true });

const form = reactive({
    biz_type: 'checkin',
    room_id: null,
    original_room_id: null,
    original_room_no: '',
    source_id: null,
    lockRoom: false,
    student_no: '',
    name: '',
    gender: '',
    check_in_at: '',
    check_out_at: '',
    safety_duty_status: '',
    furniture_confirm: '',
    foreigner_registration: '',
    mattress: '',
    bedding: '',
    access_control: '',
    agreement_status: '',
    has_checkout_order: null,
    is_belongings_cleared: null,
    furniture_state: '',
    access_action: '',
    has_transfer_application: null,
    is_procedure_done: false,
    remark: '',
});

const dialogTitle = computed(() => {
    if (dialog.isEdit) {
        return { checkin: '编辑住宿登记', transfer: '编辑调宿', checkout: '编辑退宿' }[form.biz_type] || '编辑住宿登记';
    }
    return { checkin: '新增住宿登记', transfer: '新增调宿登记', checkout: '新增退宿登记' }[form.biz_type] || '新增住宿登记';
});

const rules = {
    student_no: [{ required: true, message: '请填写学号', trigger: 'blur' }],
    name: [{ required: true, message: '请填写姓名', trigger: 'blur' }],
    room_id: [{ required: true, message: '请选择房间号', trigger: 'change' }],
    gender: [{ required: true, message: '请选择性别', trigger: 'change' }],
    check_in_at: [{ required: true, message: '请选择时间', trigger: 'change' }],
    check_out_at: [{ required: true, message: '请选择退宿时间', trigger: 'change' }],
};

function resetForm() {
    Object.assign(form, {
        room_id: null, original_room_id: null, original_room_no: '', source_id: null, lockRoom: false,
        student_no: '', name: '', gender: '', check_in_at: '', check_out_at: '',
        safety_duty_status: '', furniture_confirm: '', foreigner_registration: '',
        mattress: '', bedding: '', access_control: '', agreement_status: '',
        has_checkout_order: null, is_belongings_cleared: null, furniture_state: '',
        access_action: '', has_transfer_application: null, is_procedure_done: false, remark: '',
    });
}

/** 新增：biz 指定业务；room 存在则锁定房间号 */
function openCreate(biz = 'checkin', room = null) {
    dialog.isEdit = false;
    dialog.editId = null;
    dialog.showTabs = !room; // 从房间号入口进来的锁定该房间，不显示页签
    resetForm();
    form.biz_type = biz;
    if (room) {
        form.room_id = room.id;
        form.lockRoom = true;
        if (biz === 'transfer') form.original_room_id = room.id;
    }
    dialog.visible = true;
}

/** 行内「调宿」：带回原房间为原房间号 */
function openTransfer(row) {
    dialog.isEdit = false;
    dialog.editId = null;
    dialog.showTabs = false;
    resetForm();
    form.biz_type = 'transfer';
    form.source_id = row.id;
    form.original_room_id = row.room_id;
    form.original_room_no = row.room_no;
    form.student_no = row.student_no;
    form.name = row.name;
    form.gender = row.gender;
    form.check_in_at = row.check_in_at;
    form.room_id = null;
    dialog.visible = true;
}

/** 行内「退宿」：带回原房间为房间号 */
function openCheckout(row) {
    dialog.isEdit = false;
    dialog.editId = null;
    dialog.showTabs = false;
    resetForm();
    form.biz_type = 'checkout';
    form.source_id = row.id;
    form.room_id = row.room_id;
    form.lockRoom = true;
    form.student_no = row.student_no;
    form.name = row.name;
    form.gender = row.gender;
    form.check_in_at = row.check_in_at;
    form.check_out_at = row.check_out_at || '';
    dialog.visible = true;
}

/** 行内「编辑」：三个页签可切换，默认激活行类型 */
function openEdit(row) {
    dialog.isEdit = true;
    dialog.editId = row.id;
    dialog.showTabs = true;
    resetForm();
    Object.assign(form, {
        biz_type: row.biz_type,
        room_id: row.room_id,
        source_id: row.id,
        student_no: row.student_no,
        name: row.name,
        gender: row.gender,
        check_in_at: row.check_in_at || '',
        check_out_at: row.check_out_at || '',
        safety_duty_status: row.safety_duty_status || '',
        furniture_confirm: row.furniture_confirm || '',
        foreigner_registration: row.foreigner_registration || '',
        mattress: row.mattress || '',
        bedding: row.bedding || '',
        access_control: row.access_control || '',
        agreement_status: row.agreement_status || '',
        has_checkout_order: row.has_checkout_order,
        is_belongings_cleared: row.is_belongings_cleared,
        furniture_state: row.furniture_state || '',
        access_action: row.access_action || '',
        has_transfer_application: row.has_transfer_application,
        is_procedure_done: row.is_procedure_done,
        remark: row.remark || '',
    });
    dialog.visible = true;
}

function buildPayload() {
    return {
        biz_type: form.biz_type,
        room_id: form.room_id,
        source_id: form.source_id || undefined,
        original_room_no: form.biz_type === 'transfer' ? form.original_room_no : null,
        student_no: form.student_no,
        name: form.name,
        gender: form.gender,
        check_in_at: form.check_in_at || null,
        check_out_at: form.check_out_at || null,
        safety_duty_status: form.safety_duty_status || null,
        furniture_confirm: form.furniture_confirm || null,
        foreigner_registration: form.foreigner_registration || null,
        mattress: form.mattress || null,
        bedding: form.bedding || null,
        access_control: form.access_control || null,
        agreement_status: form.agreement_status || null,
        has_checkout_order: form.has_checkout_order,
        is_belongings_cleared: form.is_belongings_cleared,
        furniture_state: form.furniture_state || null,
        access_action: form.access_action || null,
        has_transfer_application: form.has_transfer_application,
        is_procedure_done: !!form.is_procedure_done,
        remark: form.remark || null,
    };
}

async function onSave() {
    await formRef.value.validate();
    dialog.saving = true;
    try {
        if (dialog.isEdit) {
            await accommodationApi.update(dialog.editId, buildPayload());
            ElMessage.success('已保存');
        } else {
            const res = await accommodationApi.store(buildPayload());
            ElMessage.success(res.message || '已保存');
        }
        dialog.visible = false;
        refresh();
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

function refresh() {
    if (view.value === 'detail') loadDetail();
    else if (view.value === 'history') loadHistory();
    else load(query.page);
}

async function onHandover(row) {
    await ElMessageBox.confirm(
        `确定交接 ${row.room_no} ${row.name} 的${row.biz_type_label}记录吗？交接后将通知次日值班人员。`,
        '交接确认', { type: 'info' }
    );
    const res = await accommodationApi.handover(row.id);
    ElMessage.success(res.message || '交接完成');
    refresh();
}

async function onExport() {
    try {
        const res = await accommodationApi.export({
            room_no: query.room_no || undefined,
            biz_type: query.biz_type || undefined,
            full_status: query.full_status || undefined,
        });
        const blob = new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `住宿管理导出_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    const opt = await roomApi.all();
    rooms.value = opt.data || [];
    load();
});
</script>

<style scoped>
.room-head {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 15px;
    font-weight: 500;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
}
.room-sep { color: var(--border); font-weight: 400; }
.room-tip { font-size: 12px; color: var(--text-muted); padding: 8px 16px; }
.cell-muted { color: var(--text-muted); }
</style>
