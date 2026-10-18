<template>
  <div class="qt-app">
    <aside class="qt-sidebar" :class="{ collapsed }">
      <div class="qt-sidebar-logo">
        <span style="font-size:17px;">🏠</span>
        <span v-show="!collapsed">宿舍物资管理系统</span>
      </div>

      <nav class="qt-sidebar-menu">
        <template v-for="item in visibleMenu" :key="item.label">
          <!-- 单项菜单 -->
          <el-tooltip v-if="!item.children" :disabled="!collapsed" :content="item.label" placement="right">
            <router-link class="qt-menu-item"
              :class="{ active: activeKey === item.key }" :to="menuPath(item.key)">
              <QtIcon :name="item.icon" :size="16" /><span v-show="!collapsed">{{ item.label }}</span>
            </router-link>
          </el-tooltip>

          <!-- 分组菜单：标题可点击折叠/展开子项 -->
          <template v-else>
            <div v-show="!collapsed" class="qt-menu-group-title qt-menu-group-toggle"
              @click="toggleGroup(item.label)">
              <QtIcon :name="item.icon" :size="13" />&nbsp;{{ item.label }}
              <QtIcon class="qt-group-chevron" :class="{ closed: isGroupClosed(item.label) }"
                name="chevron" :size="12" />
            </div>
            <div v-show="collapsed || !isGroupClosed(item.label)" class="qt-menu-sub">
              <el-tooltip v-for="c in item.children" :key="c.key" :disabled="!collapsed" :content="c.label" placement="right">
                <router-link class="qt-menu-item"
                  :class="{ active: activeKey === c.key }" :to="menuPath(c.key)">
                  <QtIcon :name="c.icon" :size="15" /><span v-show="!collapsed">{{ c.label }}</span>
                </router-link>
              </el-tooltip>
            </div>
          </template>
        </template>
      </nav>

      <!-- 收缩/展开开关 -->
      <div class="qt-sidebar-toggle" :title="collapsed ? '展开菜单' : '收起菜单'" @click="toggleCollapse">
        <QtIcon name="back" :size="15" :style="{ transform: collapsed ? 'rotate(180deg)' : 'none', transition: 'transform .2s' }" />
        <span v-show="!collapsed">收起菜单</span>
      </div>
    </aside>

    <div class="qt-main-area">
      <header class="qt-topbar">
        <div class="qt-topbar-left">
          <span class="qt-topbar-title">{{ pageTitle }}</span>
        </div>
        <div class="qt-topbar-right">
          <el-dropdown trigger="click" @command="handleCommand">
            <div class="qt-user-trigger">
              <div class="qt-user-avatar"><QtIcon name="user" :size="14" /></div>
              <span class="qt-user-name">{{ auth.user?.username }}</span>
              <span class="qt-user-role">{{ auth.user?.identity_label }}</span>
              <QtIcon name="chevron" :size="12" style="transform:rotate(180deg);opacity:.6;" />
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="logout">
                  <QtIcon name="back" :size="13" />&nbsp;退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </header>

      <main class="qt-content">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessageBox } from 'element-plus';
import { useAuthStore } from '../stores/auth';
import { MENU_TREE } from '../config/menus';
import { menuPath } from '../router';
import QtIcon from '../components/QtIcon.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

/** 侧边栏收缩状态，记住用户上次选择 */
const collapsed = ref(localStorage.getItem('qt.sidebar.collapsed') === '1');
function toggleCollapse() {
    collapsed.value = !collapsed.value;
    localStorage.setItem('qt.sidebar.collapsed', collapsed.value ? '1' : '0');
}

/** 菜单分组折叠状态（默认全部展开，记录被收起的分组名），记住用户选择 */
const closedGroups = ref(new Set(JSON.parse(localStorage.getItem('qt.sidebar.closedGroups') || '[]')));
function isGroupClosed(label) {
    return closedGroups.value.has(label);
}
function toggleGroup(label) {
    const next = new Set(closedGroups.value);
    next.has(label) ? next.delete(label) : next.add(label);
    closedGroups.value = next;
    localStorage.setItem('qt.sidebar.closedGroups', JSON.stringify([...next]));
}

/** 按权限过滤菜单：单项看自身 key，分组过滤子项、空组隐藏（需求 §3.1） */
const visibleMenu = computed(() =>
    MENU_TREE
        .map((item) => {
            if (!item.children) return auth.hasMenu(item.perm || item.key) ? item : null;
            const children = item.children.filter((c) => auth.hasMenu(c.perm || c.key));
            return children.length ? { ...item, children } : null;
        })
        .filter(Boolean)
);

/** 当前激活菜单 key（/users → user；/m/:key → key） */
const activeKey = computed(() => route.meta.menu || route.params.key || '');

/** 顶部栏页面标题（/m/:key 占位页从 MENU_TREE 取 label） */
const pageTitle = computed(() => {
    if (route.meta.title) return route.meta.title;
    const key = route.params.key;
    if (!key) return '';
    const all = MENU_TREE.flatMap((g) => (g.children ? g.children : [g]));
    return all.find((m) => m.key === key)?.label || '模块';
});

async function handleCommand(cmd) {
    if (cmd !== 'logout') return;
    await ElMessageBox.confirm('确定退出登录吗？', '提示', { type: 'warning' });
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<style scoped>
/* 顶部栏账号下拉触发 */
.qt-user-trigger {
  display: flex; align-items: center; gap: 8px; cursor: pointer;
  padding: 6px 10px; border-radius: 4px; transition: background .15s;
  font-size: 14px; color: var(--text);
}
.qt-user-trigger:hover { background: #f5f7fa; }
.qt-user-avatar {
  width: 30px; height: 30px; border-radius: 50%; background: var(--primary);
  color: #fff; display: inline-flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.qt-user-name { font-weight: 500; }
.qt-user-role {
  font-size: 12px; color: var(--text-muted); background: #f0f2f5;
  padding: 2px 6px; border-radius: 3px;
}
</style>
