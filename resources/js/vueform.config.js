import en from '@vueform/vueform/locales/en'
import tailwind from '@vueform/vueform/dist/tailwind'
import { defineConfig } from '@vueform/vueform'
import VerticalFormTabs from './Pages/components/elements/VerticalFormTabs.vue'
import VerticalFormTab from './Pages/components/elements/VerticalFormTab.vue'

export default defineConfig({
  theme: tailwind,
  locales: { en },
  locale: 'en',
  classHelpers: true,
  templates: {
    FormTabs_vertical: VerticalFormTabs,
    FormTab_vertical: VerticalFormTab,
  }

})