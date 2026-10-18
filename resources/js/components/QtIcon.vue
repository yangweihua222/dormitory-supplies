<template>
  <span class="qt-icon" v-html="svg"></span>
</template>

<script setup>
import { computed } from 'vue';
import { iconSvg } from '../icons';

const props = defineProps({
  name: { type: String, required: true },
  size: { type: Number, default: 16 },
  sw: { type: Number, default: 1.8 },
});

const svg = computed(() => iconSvg(props.name, props.size, props.sw));
</script>

<style scoped>
.qt-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
</style>
