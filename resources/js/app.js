import { createApp } from 'vue'
import RegistrationForm from './components/player/RegistrationForm.vue'
import PlayerGame from './components/player/PlayerGame.vue'
import AdminPanel from './components/admin/AdminPanel.vue'
import MainScreen from './components/screen/MainScreen.vue'

// Axios global defaults
import axios from 'axios'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
const token = document.querySelector('meta[name="csrf-token"]')
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content')
}
axios.interceptors.request.use((config) => {
    const playerToken = localStorage.getItem('player_session_token') ?? sessionStorage.getItem('player_session_token')
    if (playerToken) config.headers['X-Player-Token'] = playerToken
    return config
})

const app = createApp({})

app.component('registration-form', RegistrationForm)
app.component('player-game', PlayerGame)
app.component('admin-panel', AdminPanel)
app.component('main-screen', MainScreen)

app.mount('#app')
