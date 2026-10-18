// SVG 图标库（移植自原型 assets/icons.js，保持视觉一致）
export const ICONS = {
  dashboard: '<rect x="3" y="3" width="7.5" height="7.5" rx="1.5"></rect><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"></rect><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"></rect><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"></rect>',
  user: '<circle cx="12" cy="7.5" r="3.5"></circle><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"></path>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
  clock: '<circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 3"></path>',
  calendar: '<rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path>',
  shift: '<rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M3 10h18"></path><path d="M8 14h2"></path><path d="M14 14h2"></path>',
  schedule: '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path><path d="M9 16l2 2 4-4"></path>',
  duty: '<path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>',
  leave: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M9 13h6"></path><path d="M9 17h4"></path>',
  home: '<path d="M3 12L12 3l9 9"></path><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"></path>',
  bed: '<path d="M4 18v-6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v6"></path><path d="M4 18h16"></path><path d="M4 14h16"></path><path d="M7 10V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v3"></path>',
  key: '<circle cx="7.5" cy="15.5" r="5.5"></circle><path d="M21 2l-9.6 9.6"></path><path d="M15.5 7.5l3 3L22 7l-3-3"></path>',
  card: '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18"></path>',
  tool: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path>',
  box: '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><path d="M3.3 7l8.7 5 8.7-5"></path><path d="M12 22V12"></path>',
  bell: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
  search: '<circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.35-4.35"></path>',
  plus: '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
  edit: '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>',
  trash: '<polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>',
  refresh: '<path d="M23 4v6h-6"></path><path d="M1 20v-6h6"></path><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"></path><path d="M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>',
  download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>',
  upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line>',
  eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>',
  list: '<line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><circle cx="4" cy="6" r="1"></circle><circle cx="4" cy="12" r="1"></circle><circle cx="4" cy="18" r="1"></circle>',
  check: '<polyline points="20 6 9 17 4 12"></polyline>',
  close: '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
  warning: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></path><line x1="12" y1="17" x2="12.01" y2="17"></line>',
  info: '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>',
  back: '<line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>',
  chevron: '<polyline points="6 9 12 15 18 9"></polyline>',
  history: '<path d="M3 3v5h5"></path><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"></path><path d="M12 7v5l4 2"></path>',
  chart: '<line x1="4" y1="20" x2="4" y2="12"></line><line x1="10" y1="20" x2="10" y2="6"></line><line x1="16" y1="20" x2="16" y2="14"></line><line x1="21" y1="20" x2="21" y2="9"></line><line x1="3" y1="20" x2="22" y2="20"></line>',
};

export function iconSvg(name, size = 16, sw = 1.8) {
  const p = ICONS[name] || '';
  return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="${sw}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${p}</svg>`;
}
