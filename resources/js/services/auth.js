import api from './api'

const auth = {
    async login(credentials) {
        const response = await api.post('/login', credentials)
        if (response.token) {
            localStorage.setItem('token', response.token)
            localStorage.setItem('user', JSON.stringify(response.user))
            return response
        }
        return null
    },

    async register(userData) {
        const response = await api.post('/register', userData)
        if (response.token) {
            localStorage.setItem('token', response.token)
            localStorage.setItem('user', JSON.stringify(response.user))
            return response
        }
        return null
    },

    async logout() {
        try {
            await api.post('/logout')
        } catch (error) {
            console.error('Logout error:', error)
        } finally {
            localStorage.removeItem('token')
            localStorage.removeItem('user')
        }
    },

    getToken() {
        return localStorage.getItem('token')
    },

    getUser() {
        const user = localStorage.getItem('user')
        return user ? JSON.parse(user) : null
    },

    isAuthenticated() {
        return !!this.getToken()
    },

    getUserRole() {
        const user = this.getUser()
        return user?.role || 'guest'
    },

    isAdmin() {
        return this.getUserRole() === 'admin'
    },

    isGuest() {
        return this.getUserRole() === 'guest'
    }
}

export default auth
