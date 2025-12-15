# SEO Keyword Management App - Backend

---

## Backend PRD (Laravel)

### Overview
Build a RESTful API for managing projects and hierarchical keyword clusters with support for nested structures, reordering, and filtering.

### Data Models

**Project**
- `id`, `user_id`, `name`, `timestamps`

**Keyword**
- `id`, `project_id`, `parent_id` (nullable, self-referencing for nesting)
- `name` (string)
- `volume` (integer, nullable)
- `intent` (enum: info, commercial, transactional, navigational)
- `status` (enum: active, draft, planned)
- `keyword_type` (enum: product, service, benefit, price, competitor)
- `content_type` (enum: pillar_page, article, tutorial, comparison, landing_page)
- `strategic_role` (text, nullable)
- `strategic_opportunity` (text, nullable)
- `position` (integer, for ordering within parent)
- `timestamps`

**KeywordCompetitor**
- `id`, `keyword_id`, `name`, `url`, `rank` (integer)

### API Endpoints

**Projects**
- `GET /projects` - List user's projects
- `POST /projects` - Create project
- `GET /projects/{id}` - Get project with keyword tree
- `PUT /projects/{id}` - Update project
- `DELETE /projects/{id}` - Delete project (cascade keywords)

**Keywords**
- `GET /projects/{id}/keywords` - Get keyword tree (nested structure)
  - Query params: `status`, `intent`, `keyword_type`, `search`
- `POST /projects/{id}/keywords` - Create keyword
- `GET /keywords/{id}` - Get single keyword with competitors
- `PUT /keywords/{id}` - Update keyword
- `DELETE /keywords/{id}` - Delete keyword (handle children: cascade or promote)
- `PATCH /keywords/{id}/move` - Move keyword to new parent and/or position
- `PATCH /keywords/reorder` - Bulk reorder keywords within same parent

**Stats Endpoint**
- `GET /projects/{id}/stats` - Return aggregated stats
  - Pillar count (root-level keywords)
  - Total keywords
  - Combined volume
  - Status breakdown (active/draft/planned counts)

### Key Implementation Notes

- Use `parent_id` with recursive relationships for nesting (`hasMany('keywords')` on Keyword model)
- Consider eager loading with `with('children')` recursive scope for tree retrieval
- Position field uses integer ordering; reorder endpoint should renumber positions
- Move endpoint needs to update `parent_id` and recalculate positions in both source and destination lists
- Filter queries should work across the tree (return matching keywords with their ancestors for context)
- Add `is_pillar` accessor (true when `parent_id` is null)

### Policies
- Users can only access their own projects and keywords
- Standard Laravel policies for authorization

---