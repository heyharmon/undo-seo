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

    /**
     * Explore keywords without saving them (for preview).
     * @param {string} query - Search query (comma-separated for ideas)
     * @param {string} source - 'ideas' or 'suggestions'
     */
    async explore(projectId, query, source) {
        return await api.post(`/projects/${projectId}/explore`, {
            query: query,
            source: source,
        })
    },

    /**
     * Add selected keywords to the project.
     * @param {Array} keywords - Array of keyword objects with keyword, search_volume, difficulty
     */
    async addKeywords(projectId, keywords) {
        return await api.post(`/projects/${projectId}/add-keywords`, {
            keywords: keywords,
        })
    },
}

export default keywords
