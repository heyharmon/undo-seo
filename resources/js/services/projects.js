import api from './api'

const projects = {
    async getAll() {
        return await api.get('/projects')
    },

    async get(id) {
        return await api.get(`/projects/${id}`)
    },

    async create(data) {
        return await api.post('/projects', data)
    },

    async update(id, data) {
        return await api.put(`/projects/${id}`, data)
    },

    async delete(id) {
        return await api.delete(`/projects/${id}`)
    }
}

export default projects
