<template>
  <div>
    <!-- گرفتن emit -->
    <Menu @select="currentComponent = $event" />

    <!-- نمایش کامپوننت‌ها -->
    <div class="content">
      <Parvande v-if="currentComponent === 'Parvande'" />
      <anbar v-if="currentComponent === 'Anbar'" />
    </div>
  </div>
</template>

<script>
import Menu from "../components/menu.vue"
import Parvande from "../components/parvande.vue"
import anbar from "../components/anbar.vue";

export default {
  components: {
    Menu,
    Parvande,
    anbar,
  },
  data() {
    return {
      currentComponent: null
    }
  }
}
</script>


<style>
</style>