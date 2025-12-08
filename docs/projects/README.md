# Projects Domain

## Purpose

Projects are containers for keyword research. Each user can create multiple projects to organize their SEO work (e.g., "My SaaS Blog" or "Client: Acme Corp"). Projects will eventually hold topical maps with clustered keywords.

## Backend

- **Project model**: belongs to a user, has `name` field. Located at `app/Models/Project.php`.
- **ProjectController**: full CRUD with ownership checks. Users can only access their own projects.

### API Endpoints

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/projects` | List user's projects |
| POST | `/api/projects` | Create new project |
| GET | `/api/projects/{id}` | View project |
| PUT | `/api/projects/{id}` | Update project |
| DELETE | `/api/projects/{id}` | Delete project |

### Validation

- `name`: required, string, max 255 characters

## Frontend

- **Service**: `resources/js/services/projects.js` wraps project API calls.
- **Pages**:
  - `ProjectsIndex.vue` — grid of project cards with empty state
  - `ProjectCreate.vue` — form to create a new project
  - `ProjectShow.vue` — project detail with topical map placeholder
  - `ProjectEdit.vue` — edit form with delete option

## Database Schema

```sql
projects
├── id
├── user_id (foreign key → users)
├── name
├── created_at
└── updated_at
```

## Future Additions

Projects will gain additional fields and relationships as features are built:
- Seed keyword input
- Keywords relationship (topical map data)
- Keyword count for display
