import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { autofocus } from './directives/autofocus'

createApp(App).use(router).directive('autofocus', autofocus).mount('#app')
