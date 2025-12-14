import api from '@/services/api'

export default {
    // Projects
    async getProjects() {
        return api.get('/projects')
    },

    async getProject(id) {
        return api.get(`/projects/${id}`)
    },

    async createProject(data) {
        return api.post('/projects', data)
    },

    async updateProject(id, data) {
        return api.put(`/projects/${id}`, data)
    },

    async deleteProject(id) {
        return api.delete(`/projects/${id}`)
    },

    async getProjectStats(id) {
        return api.get(`/projects/${id}/stats`)
    },

    // Keywords
    async getKeywords(projectId, filters = {}) {
        const params = new URLSearchParams()
        if (filters.status) params.append('status', filters.status)
        if (filters.intent) params.append('intent', filters.intent)
        if (filters.keyword_type) params.append('keyword_type', filters.keyword_type)
        if (filters.search) params.append('search', filters.search)
        
        const queryString = params.toString()
        return api.get(`/projects/${projectId}/keywords${queryString ? `?${queryString}` : ''}`)
    },

    async getKeyword(id) {
        return api.get(`/keywords/${id}`)
    },

    async createKeyword(projectId, data) {
        return api.post(`/projects/${projectId}/keywords`, data)
    },

    async updateKeyword(id, data) {
        return api.put(`/keywords/${id}`, data)
    },

    async deleteKeyword(id) {
        return api.delete(`/keywords/${id}`)
    },

    async moveKeyword(id, data) {
        return api.patch(`/keywords/${id}/move`, data)
    },

    async reorderKeywords(keywords) {
        return api.patch('/keywords/reorder', { keywords })
    }
}

