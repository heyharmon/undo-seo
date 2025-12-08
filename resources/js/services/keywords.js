import api from './api'

const keywords = {
    async generateMap(projectId, seedKeyword, includeSuggestions = false) {
        return await api.post(`/projects/${projectId}/generate-map`, {
            seed_keyword: seedKeyword,
            include_suggestions: includeSuggestions,
        })
    },

    async getClusters(projectId) {
        return await api.get(`/projects/${projectId}/clusters`)
    },

    async getCluster(projectId, clusterId) {
        return await api.get(`/projects/${projectId}/clusters/${clusterId}`)
    },

    async getSuggestions(projectId) {
        return await api.post(`/projects/${projectId}/suggestions`)
    },

    async getClusterSuggestions(projectId, clusterId) {
        return await api.post(`/projects/${projectId}/clusters/${clusterId}/suggestions`)
    },
}

export default keywords
