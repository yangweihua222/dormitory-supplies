<template>
  <div class="qt-page work-page">
    <div class="qt-filter-bar">
      <span class="qt-filter-label">统计年度</span>
      <el-select v-model="year" style="width:120px" @change="onYearChange">
        <el-option v-for="y in yearOptions" :key="y" :label="y + ' 年'" :value="y" />
      </el-select>
    </div>

    <div v-loading="loading">
      <!-- 三大块统计卡片 -->
      <div class="work-group">
        <div class="work-group-title"><QtIcon name="users" :size="16" /> 志愿服务</div>
        <div class="work-stats">
          <div class="stat-card"><div class="stat-num">{{ d.volunteer.students }}</div><div class="stat-label">服务学生总人数</div></div>
          <div class="stat-card"><div class="stat-num">{{ d.volunteer.count }}</div><div class="stat-label">全年服务次数</div></div>
          <div class="stat-card"><div class="stat-num">{{ d.volunteer.hours }}</div><div class="stat-label">服务总时长（小时）</div></div>
        </div>
      </div>
      <div class="work-group">
        <div class="work-group-title"><QtIcon name="list" :size="16" /> 消防培训</div>
        <div class="work-stats">
          <div class="stat-card"><div class="stat-num">{{ d.training.count }}</div><div class="stat-label">培训次数</div></div>
          <div class="stat-card"><div class="stat-num">{{ d.training.students }}</div><div class="stat-label">培训学生总人数</div></div>
          <div class="stat-card"><div class="stat-num">{{ d.training.hours }}</div><div class="stat-label">培训总时长（小时）</div></div>
        </div>
      </div>
      <div class="work-group">
        <div class="work-group-title"><QtIcon name="shield" :size="16" /> 安全管理</div>
        <div class="work-stats">
          <div class="stat-card"><div class="stat-num">{{ d.safety.check_count }}</div><div class="stat-label">检查次数</div></div>
          <div class="stat-card"><div class="stat-num">{{ d.safety.room_count }}</div><div class="stat-label">房间个数</div></div>
          <div class="stat-card"><div class="stat-num warn">{{ d.safety.abnormal }}</div><div class="stat-label">检查异常数量</div></div>
          <div class="stat-card"><div class="stat-num ok">{{ d.safety.rect_done }}</div><div class="stat-label">完成整改次数</div></div>
          <div class="stat-card"><div class="stat-num bad">{{ d.safety.rect_undone }}</div><div class="stat-label">未完成整改次数</div></div>
        </div>
      </div>

      <!-- 趋势 + 整改环形 -->
      <div class="work-row">
        <div class="work-card">
          <div class="work-card-header"><QtIcon name="chart" :size="17" /> 月度服务 / 培训趋势</div>
          <div class="bar-chart">
            <div v-for="m in 12" :key="m" class="bar-col">
              <div class="bar-pair">
                <div class="bar svc" :style="{ height: barH(d.svc_by_month[m - 1], maxMonthly) }" :title="`志愿服务 ${d.svc_by_month[m - 1]} 次`"></div>
                <div class="bar tr" :style="{ height: barH(d.train_by_month[m - 1], maxMonthly) }" :title="`消防培训 ${d.train_by_month[m - 1]} 次`"></div>
              </div>
              <div class="bar-label">{{ m }}月</div>
            </div>
          </div>
          <div class="work-legend">
            <span><i style="background:#409eff"></i>志愿服务次数</span>
            <span><i style="background:#67c23a"></i>消防培训次数</span>
          </div>
        </div>
        <div class="work-card">
          <div class="work-card-header"><QtIcon name="check" :size="17" /> 整改完成情况</div>
          <div class="donut-wrap">
            <svg viewBox="0 0 120 120" class="donut">
              <circle cx="60" cy="60" r="48" fill="none" stroke="#f56c6c" stroke-width="16"
                :stroke-dasharray="`${2 * Math.PI * 48}`" stroke-dashoffset="0" />
              <circle cx="60" cy="60" r="48" fill="none" stroke="#67c23a" stroke-width="16"
                :stroke-dasharray="`${rectPct / 100 * 2 * Math.PI * 48} ${2 * Math.PI * 48}`"
                transform="rotate(-90 60 60)" />
              <text x="60" y="66" text-anchor="middle" font-size="20" font-weight="600" fill="#303133">{{ rectPct }}%</text>
            </svg>
            <div class="work-legend col">
              <span><i style="background:#67c23a"></i>完成整改 {{ d.safety.rect_done }} 次</span>
              <span><i style="background:#f56c6c"></i>未完成整改 {{ d.safety.rect_undone }} 次</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 总时长对比 -->
      <div class="work-card">
        <div class="work-card-header"><QtIcon name="clock" :size="17" /> 服务 / 培训 总时长对比</div>
        <div class="hbar-list">
          <div class="hbar-row">
            <span class="hbar-label">志愿服务总时长</span>
            <div class="hbar-track"><div class="hbar-fill" style="background:#409eff" :style="{ width: hbarW(d.volunteer.hours, maxHours) }"></div></div>
            <span class="hbar-val">{{ d.volunteer.hours }} 小时</span>
          </div>
          <div class="hbar-row">
            <span class="hbar-label">消防培训总时长</span>
            <div class="hbar-track"><div class="hbar-fill" style="background:#67c23a" :style="{ width: hbarW(d.training.hours, maxHours) }"></div></div>
            <span class="hbar-val">{{ d.training.hours }} 小时</span>
          </div>
        </div>
      </div>

      <!-- 物资运营统计 -->
      <div class="work-group">
        <div class="work-group-title"><QtIcon name="box" :size="16" /> 物资运营统计（{{ year }} 年）</div>
        <div class="work-stats">
          <div class="stat-card"><div class="stat-num primary">{{ d.materials.buy_fixed + d.materials.buy_cons }}</div><div class="stat-label">当年采购总量（件）</div></div>
          <div class="stat-card"><div class="stat-num bad">{{ d.materials.scrap_fixed + d.materials.scrap_cons }}</div><div class="stat-label">当年报废总量（件）</div></div>
          <div class="stat-card"><div class="stat-num ok">{{ d.materials.in_use_total }}</div><div class="stat-label">宿舍在用物资（件）</div></div>
        </div>
      </div>
      <div class="work-row">
        <div class="work-card">
          <div class="work-card-header"><QtIcon name="chart" :size="17" /> 当年采购 vs 报废（按类型）
            <a class="qt-link export-link" @click="onExportBuyScrap"><QtIcon name="download" :size="13" /> 导出</a>
          </div>
          <div class="hbar-list">
            <div class="hbar-row">
              <span class="hbar-label">固定资产 · 采购</span>
              <div class="hbar-track"><div class="hbar-fill" style="background:#409eff" :style="{ width: hbarW(d.materials.buy_fixed, maxBuyScrap) }"></div></div>
              <span class="hbar-val">{{ d.materials.buy_fixed }} 件</span>
            </div>
            <div class="hbar-row">
              <span class="hbar-label">固定资产 · 报废</span>
              <div class="hbar-track"><div class="hbar-fill" style="background:#f56c6c" :style="{ width: hbarW(d.materials.scrap_fixed, maxBuyScrap) }"></div></div>
              <span class="hbar-val">{{ d.materials.scrap_fixed }} 件</span>
            </div>
            <div class="hbar-row">
              <span class="hbar-label">耗材类 · 采购</span>
              <div class="hbar-track"><div class="hbar-fill" style="background:#67c23a" :style="{ width: hbarW(d.materials.buy_cons, maxBuyScrap) }"></div></div>
              <span class="hbar-val">{{ d.materials.buy_cons }} 件</span>
            </div>
            <div class="hbar-row">
              <span class="hbar-label">耗材类 · 报废</span>
              <div class="hbar-track"><div class="hbar-fill" style="background:#e6a23c" :style="{ width: hbarW(d.materials.scrap_cons, maxBuyScrap) }"></div></div>
              <span class="hbar-val">{{ d.materials.scrap_cons }} 件</span>
            </div>
          </div>
        </div>
        <div class="work-card">
          <div class="work-card-header"><QtIcon name="box" :size="17" /> 宿舍在用物资（按家具种类）
            <a class="qt-link export-link" @click="onExportInUse"><QtIcon name="download" :size="13" /> 导出</a>
          </div>
          <div class="hbar-list">
            <div v-for="(k, i) in ['桌椅类', '床类', '柜类']" :key="k" class="hbar-row">
              <span class="hbar-label">{{ k }}</span>
              <div class="hbar-track"><div class="hbar-fill" :style="{ width: hbarW(d.materials.in_use[k] || 0, maxInUse), background: ['#409eff', '#67c23a', '#e6a23c'][i] }"></div></div>
              <span class="hbar-val">{{ d.materials.in_use[k] || 0 }} 件</span>
            </div>
          </div>
          <div class="work-legend"><span>统计范围：房源类型为「宿舍」的房间内物资</span></div>
        </div>
      </div>

      <!-- 到岗率 / 准点率 -->
      <div class="work-row">
        <div class="work-card">
          <div class="work-card-header"><QtIcon name="check" :size="17" /> 当月到岗率（{{ d.duty.month }}）</div>
          <div class="donut-wrap">
            <svg viewBox="0 0 120 120" class="donut">
              <circle cx="60" cy="60" r="48" fill="none" stroke="#dcdfe6" stroke-width="16" />
              <circle cx="60" cy="60" r="48" fill="none" stroke="#409eff" stroke-width="16"
                :stroke-dasharray="`${d.duty.duty_rate / 100 * 2 * Math.PI * 48} ${2 * Math.PI * 48}`"
                transform="rotate(-90 60 60)" />
              <text x="60" y="66" text-anchor="middle" font-size="20" font-weight="600" fill="#303133">{{ d.duty.duty_rate }}%</text>
            </svg>
            <div class="work-legend col">
              <span><i style="background:#409eff"></i>到岗 {{ d.duty.on_duty }} 人次</span>
              <span><i style="background:#dcdfe6"></i>应到 {{ d.duty.total }} 人次（含请假 {{ d.duty.leave }}）</span>
            </div>
          </div>
        </div>
        <div class="work-card">
          <div class="work-card-header"><QtIcon name="clock" :size="17" /> 当月准点率（{{ d.duty.month }}）</div>
          <div class="donut-wrap">
            <svg viewBox="0 0 120 120" class="donut">
              <circle cx="60" cy="60" r="48" fill="none" stroke="#dcdfe6" stroke-width="16" />
              <circle cx="60" cy="60" r="48" fill="none" stroke="#67c23a" stroke-width="16"
                :stroke-dasharray="`${d.duty.time_rate / 100 * 2 * Math.PI * 48} ${2 * Math.PI * 48}`"
                transform="rotate(-90 60 60)" />
              <text x="60" y="66" text-anchor="middle" font-size="20" font-weight="600" fill="#303133">{{ d.duty.time_rate }}%</text>
            </svg>
            <div class="work-legend col">
              <span><i style="background:#67c23a"></i>准点 {{ d.duty.on_time }} 人次</span>
              <span><i style="background:#dcdfe6"></i>迟到 / 早退 {{ d.duty.on_duty - d.duty.on_time }} 人次 · 请假不计</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 每日交接完成率 -->
      <div class="work-card">
        <div class="work-card-header"><QtIcon name="list" :size="17" /> 每日交接工作完成率</div>
        <div class="work-legend daily-legend">
          <span><i style="background:#67c23a"></i>完成率 ≥80%</span>
          <span><i style="background:#e6a23c"></i>50–79%</span>
          <span><i style="background:#f56c6c"></i>&lt;50%</span>
          <span>柱下数字：已完成 / 交接总数</span>
        </div>
        <div v-if="d.handover_days.length" class="daily-bars">
          <div v-for="day in d.handover_days" :key="day.date" class="daily-col" :class="{ today: day.is_today }">
            <div class="daily-pct">{{ day.pct }}%</div>
            <div class="daily-track">
              <div class="daily-fill" :class="rateClass(day.pct)" :style="{ height: Math.max(day.pct, 2) + '%' }"></div>
            </div>
            <div class="daily-num">{{ day.done }}/{{ day.total }}</div>
            <div class="bar-label">{{ day.is_today ? '今天' : day.label }}</div>
          </div>
        </div>
        <div v-else style="padding:24px;color:#909399;text-align:center;">暂无交接数据</div>
      </div>

      <!-- 月度考核统计（按年=全年各月平均分 / 按月=当月得分） -->
      <div class="work-card">
        <div class="work-card-header"><QtIcon name="chart" :size="17" /> 月度考核统计
          <span class="assess-ctl">
            <el-select v-model="assess.mode" size="small" style="width:140px" @change="onAssessModeChange">
              <el-option value="year" label="按年（平均分）" />
              <el-option value="month" label="按月" />
            </el-select>
            <el-select v-if="assess.mode === 'month'" v-model="assess.month" size="small" style="width:120px"
              @change="loadAssess">
              <el-option v-for="m in assess.months" :key="m" :label="m" :value="m" />
            </el-select>
          </span>
        </div>
        <div v-if="assess.rows.length" class="hbar-list">
          <div v-for="r in assess.rows" :key="r.name" class="hbar-row">
            <span class="hbar-label">{{ r.name }}</span>
            <div class="hbar-track">
              <div class="hbar-fill" :style="{ width: hbarW(r.score, maxAssess), background: assessColor(r.score) }"></div>
            </div>
            <span class="hbar-val" style="width:90px;">{{ r.score }} 分</span>
          </div>
        </div>
        <div v-else style="padding:24px;color:#909399;text-align:center;">该{{ assess.mode === 'month' ? '月份' : '年度' }}暂无考核数据</div>
        <div class="work-legend">
          <span><i style="background:#67c23a"></i>优秀 ≥85</span>
          <span><i style="background:#409eff"></i>合格 71–84</span>
          <span><i style="background:#e6a23c"></i>基本合格 61–70</span>
          <span><i style="background:#f56c6c"></i>不合格 &lt;60</span>
          <span>{{ assess.note }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { workApi } from '../api/material';
import QtIcon from '../components/QtIcon.vue';

const MathPI = Math.PI;
const loading = ref(false);
const year = ref(new Date().getFullYear());
const yearOptions = computed(() => {
    const y = new Date().getFullYear();
    return [y, y - 1, y - 2];
});

const d = reactive({
    volunteer: { students: 0, count: 0, hours: 0 },
    training: { count: 0, students: 0, hours: 0 },
    safety: { check_count: 0, room_count: 0, abnormal: 0, rect_done: 0, rect_undone: 0 },
    svc_by_month: new Array(12).fill(0),
    train_by_month: new Array(12).fill(0),
    materials: { buy_fixed: 0, buy_cons: 0, scrap_fixed: 0, scrap_cons: 0, in_use: {}, in_use_total: 0 },
    duty: { month: '', total: 0, on_duty: 0, on_time: 0, leave: 0, duty_rate: 0, time_rate: 0 },
    handover_days: [],
});

const maxMonthly = computed(() => Math.max(1, ...d.svc_by_month, ...d.train_by_month));
const maxHours = computed(() => Math.max(1, d.volunteer.hours, d.training.hours));
const maxBuyScrap = computed(() => Math.max(1, d.materials.buy_fixed, d.materials.buy_cons, d.materials.scrap_fixed, d.materials.scrap_cons));
const maxInUse = computed(() => Math.max(1, ...Object.values(d.materials.in_use)));
const rectPct = computed(() => {
    const t = d.safety.rect_done + d.safety.rect_undone;
    return t ? Math.round(d.safety.rect_done / t * 100) : 0;
});

// —— 月度考核统计 ——
const assess = reactive({ mode: 'year', month: '', months: [], rows: [], note: '' });
const maxAssess = computed(() => Math.max(1, ...assess.rows.map((r) => r.score)));

function assessColor(score) {
    if (score >= 85) return '#67c23a';
    if (score >= 71) return '#409eff';
    if (score >= 61) return '#e6a23c';
    return '#f56c6c';
}

async function loadAssess() {
    try {
        const res = await workApi.assessStats({
            year: year.value, mode: assess.mode,
            month: assess.mode === 'month' ? (assess.month || undefined) : undefined,
        });
        assess.months = res.data.months || [];
        assess.month = res.data.month || '';
        assess.rows = res.data.rows || [];
        assess.note = res.data.note || '';
    } catch { /* 统计卡片失败不阻塞整页 */ }
}

function onAssessModeChange() {
    assess.month = '';
    loadAssess();
}

function downloadBlob(res, filename) {
    const url = URL.createObjectURL(new Blob([res], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

async function onExportBuyScrap() {
    try {
        const res = await workApi.exportBuyScrap(year.value);
        downloadBlob(res, `采购报废统计_${year.value}年.xlsx`);
        ElMessage.success('导出成功');
    } catch {
        ElMessage.error('导出失败，请重试');
    }
}

async function onExportInUse() {
    try {
        const res = await workApi.exportInUse();
        downloadBlob(res, '宿舍在用物资统计.xlsx');
        ElMessage.success('导出成功');
    } catch {
        ElMessage.error('导出失败，请重试');
    }
}

function barH(v, max) {
    return v > 0 ? Math.max((v / max) * 100, 4) + '%' : '2px';
}

function hbarW(v, max) {
    return v > 0 ? Math.max((v / max) * 100, 2) + '%' : '0%';
}

function rateClass(pct) {
    if (pct >= 80) return 'good';
    if (pct >= 50) return 'mid';
    return 'low';
}

async function load() {
    loading.value = true;
    try {
        const res = await workApi.stats(year.value);
        Object.assign(d, res.data);
    } finally {
        loading.value = false;
    }
}

function onYearChange() {
    load();
    loadAssess();
}

onMounted(() => {
    load();
    loadAssess();
});
</script>

<style scoped>
.work-page {
    padding-bottom: 24px;
}
.work-group {
    margin-bottom: 18px;
}
.work-group-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 15px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 10px;
}
.work-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}
.stat-card {
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
}
.stat-num {
    font-size: 26px;
    font-weight: 700;
    color: #303133;
}
.stat-num.primary { color: #409eff; }
.stat-num.warn { color: #e6a23c; }
.stat-num.ok { color: #67c23a; }
.stat-num.bad { color: #f56c6c; }
.stat-label {
    margin-top: 6px;
    font-size: 13px;
    color: #909399;
}
.work-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
}
.work-card {
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
}
.work-row .work-card {
    margin-bottom: 0;
}
.work-card-header {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 14px;
}
.work-card-header .export-link {
    margin-left: auto;
    font-weight: normal;
    font-size: 12px;
}
.work-card-header .assess-ctl {
    margin-left: auto;
    display: flex;
    gap: 8px;
    align-items: center;
    font-weight: normal;
}
.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    height: 160px;
    padding: 0 4px;
}
.bar-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
}
.bar-pair {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: calc(100% - 22px);
}
.bar {
    width: 10px;
    border-radius: 2px 2px 0 0;
}
.bar.svc { background: #409eff; }
.bar.tr { background: #67c23a; }
.bar-label {
    font-size: 11px;
    color: #909399;
    margin-top: 4px;
}
.work-legend {
    display: flex;
    gap: 16px;
    margin-top: 10px;
    font-size: 12px;    color: #909399;
    flex-wrap: wrap;
}
.work-legend.col {
    flex-direction: column;
    gap: 8px;
    margin-top: 0;
    justify-content: center;
}
.work-legend i {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
    margin-right: 5px;
}
.donut-wrap {
    display: flex;
    align-items: center;
    gap: 24px;
    justify-content: center;
    min-height: 160px;
}
.donut {
    width: 140px;
    height: 140px;
}
.hbar-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.hbar-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
.hbar-label {
    width: 120px;
    font-size: 13px;
    color: #606266;
    text-align: right;
    flex-shrink: 0;
}
.hbar-track {
    flex: 1;
    height: 16px;
    background: #f2f3f5;
    border-radius: 3px;
    overflow: hidden;
}
.hbar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width .4s;
}
.hbar-val {
    width: 70px;
    font-size: 13px;
    color: #606266;
    flex-shrink: 0;
}
.daily-bars {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 18px;
    height: 200px;
    padding: 0 12px;
}
.daily-col {
    width: 90px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
}
.daily-pct {
    font-size: 12px;
    font-weight: 600;
    color: #606266;
    margin-bottom: 4px;
}
.daily-track {
    width: 34px;
    height: calc(100% - 62px);
    background: #f2f3f5;
    border-radius: 3px;
    display: flex;
    align-items: flex-end;
    overflow: hidden;
}
.daily-fill {
    width: 100%;
    border-radius: 3px;
}
.daily-fill.good { background: #67c23a; }
.daily-fill.mid { background: #e6a23c; }
.daily-fill.low { background: #f56c6c; }
/* 图例放图表上方时的间距 */
.daily-legend {
    margin-top: 0;
    margin-bottom: 12px;
    justify-content: center;
}
/* 今天的柱子高亮 */
.daily-col.today .bar-label {
    color: #409eff;
    font-weight: 600;
}
.daily-col.today .daily-track {
    outline: 1px dashed #409eff;
    outline-offset: 2px;
}
.daily-num {
    font-size: 11px;
    color: #909399;
    margin-top: 4px;
}
</style>
