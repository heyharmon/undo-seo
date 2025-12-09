import api from './api'

const keywords = {
    /**
     * Generate a topical map for a project.
     * @param {string} source - 'ideas' (semantic) or 'suggestions' (long-tail)
     */
    async generateMap(projectId, seedKeyword, source = 'suggestions') {
        return await api.post(`/projects/${projectId}/generate-map`, {
            seed_keyword: seedKeyword,
            source: source,
        })
    },

    async getClusters(projectId) {
        return await api.get(`/projects/${projectId}/clusters`)
    },

    async getCluster(projectId, clusterId) {
        return await api.get(`/projects/${projectId}/clusters/${clusterId}`)
    },

    /**
     * Get keyword suggestions (long-tail variations) for the topical map.
     */
    async getSuggestions(projectId) {
        return await api.post(`/projects/${projectId}/suggestions`)
    },

    /**
     * Get keyword ideas (semantically related) for the topical map.
     */
    async getIdeas(projectId) {
        return await api.post(`/projects/${projectId}/ideas`)
    },

    async getClusterSuggestions(projectId, clusterId) {
        return await api.post(`/projects/${projectId}/clusters/${clusterId}/suggestions`)
    },
}

export default keywords
