<template>
  <div class="qt-page">
    <!-- 工具条 -->
    <div class="qt-filter-bar">
      <div class="qt-filter">
        <span class="qt-filter-label">月份</span>
        <el-date-picker v-model="month" type="month" placeholder="选择月份" value-format="YYYY-MM"
          style="width:140px" @change="load" />
      </div>
      <el-button type="primary" @click="load"><QtIcon name="refresh" :size="13" />&nbsp;刷新</el-button>
      <el-button type="primary" style="margin-left:auto;" @click="$router.push('/schedule')">
        <QtIcon name="plus" :size="13" />&nbsp;新增排班
      </el-button>
    </div>

    <!-- 日历（原型：当月天数 7 列平铺，格内「人名 + 状态」，空格显示 —） -->
    <div class="qt-table-wrap" v-loading="loading">
      <div class="cal-grid">
        <div v-for="day in calendarDays" :key="day.date" class="cal-day" :class="{ 'is-today': day.isToday }">
          <div class="cal-date">{{ day.dayOfMonth }} 日</div>
          <template v-if="day.cells.length">
            <div v-for="(cell, idx) in day.cells" :key="idx" class="cal-cell">
              <span class="cal-name">{{ cell.username }}</span>
              <span class="cal-shift">{{ cell.shift_name }}</span>
              <el-tag :type="cell.status === 'leave' ? 'warning' : 'success'" size="small" disable-transitions>
                {{ cell.status === 'leave' ? '请假' : '正常' }}
              </el-tag>
            </div>
          </template>
          <div v-else class="cal-cell cal-empty">—</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { scheduleApi } from '../api/schedule';
import QtIcon from '../components/QtIcon.vue';
import dayjs from 'dayjs';

const loading = ref(false);
const month = ref(dayjs().format('YYYY-MM'));
const days = ref([]);

const calendarDays = computed(() => {
    if (!month.value) return [];
    const [year, mon] = month.value.split('-').map(Number);
    const daysInMonth = dayjs(`${year}-${String(mon).padStart(2, '0')}-01`).daysInMonth();

    const map = new Map((days.value || []).map((d) => [d.date, d.cells]));
    const result = [];
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${year}-${String(mon).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        // 每格最多展示 4 人；请假优先展示，避免被截断
        const raw = map.get(dateStr) || [];
        const cells = [...raw]
            .sort((a, b) => (a.status === 'leave' ? 0 : 1) - (b.status === 'leave' ? 0 : 1))
            .slice(0, 4);
        result.push({
            date: dateStr,
            dayOfMonth: d,
            isToday: dayjs(dateStr).isSame(dayjs(), 'day'),
            cells,
            total: raw.length,
        });
    }
    return result;
});

async function load() {
    if (!month.value) return;
    loading.value = true;
    try {
        const [year, mon] = month.value.split('-').map(Number);
        const res = await scheduleApi.calendar({ year, month: mon });
        days.value = res.data.days || [];
    } catch (e) {
        ElMessage.error(e?.message || '加载失败');
    } finally {
        loading.value = false;
    }
}

onMounted(() => load());
</script>

<style scoped>
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    padding: 12px;
}

.cal-day {
    border: 1px solid var(--border);
    border-radius: 6px;
    /* 固定高度：日期行 + 最多 4 条人员行，多余不展示 */
    height: 148px;
    padding: 6px;
    background: #fff;
    overflow: hidden;
}

.cal-day.is-today {
    border-color: var(--primary);
    background: #f0f7ff;
}

.cal-date {
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.cal-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    height: 24px;
    padding: 0 6px;
    border-radius: 4px;
    background: var(--bg-soft);
    margin-bottom: 3px;
    overflow: hidden;
}

.cal-cell :deep(.el-tag) {
    height: 18px;
    padding: 0 5px;
    font-size: 11px;
    flex: none;
}

.cal-cell .cal-name {
    font-weight: 500;
    white-space: nowrap;
}

.cal-cell .cal-shift {
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

.cal-cell.cal-empty {
    color: var(--text-muted);
    justify-content: center;
}
</style>
