import home from '@/views/home.vue'


const routes = [
  {
    path: '/home',
    name: 'home',
    component: home,
    meta: { requireAuth: true }
  },

]