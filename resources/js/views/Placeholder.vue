<template>
  <div class="qt-page">
    <div class="qt-table-wrap">
      <el-empty :description="`「${label}」模块开发中，当前版本为最小闭环（登录 + 用户管理）`" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { MENU_TREE } from '../config/menus';

const route = useRoute();

const label = computed(() => {
    const key = route.params.key;
    const all = MENU_TREE.flatMap((g) => (g.children ? g.children : [g]));
    return all.find((m) => m.key === key)?.label || '模块';
});
</script>
