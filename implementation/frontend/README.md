# SEO Keyword Management App - Backend

---

## Frontend PRD (Vue)

### Overview
Build a keyword cluster management interface with a nested tree table, drag-and-drop reordering, filtering, and a slide-out detail panel.

### Views

**Projects List**
- Simple list/grid of user's projects
- Create/edit/delete projects

**Keyword Clusters View** (main view per screenshot 1)
- Stats bar at top: Pillars count, Total Keywords, Combined Volume, Status breakdown
- Filter bar: Search input, Intent dropdown, Status dropdown, Expand/Collapse all
- Nested tree table with columns: Keyword, Volume, Intent, Status
- Expandable/collapsible rows for nested keywords
- Click row to open detail panel

**Keyword Detail Panel** (slide-out per screenshot 2)
- Form fields for all keyword properties
- Competitors list with rank, name, URL (add/remove/reorder)
- Save/Cancel buttons

### Components

**KeywordTree**
- Recursive component for rendering nested keyword rows
- Props: keywords array, depth level
- Emits: select, expand/collapse, drag events

**KeywordRow**
- Single keyword row with indent based on depth
- Expand/collapse toggle for items with children
- Drag handle
- Status badge (colored dot)
- Intent badge

**KeywordDetailPanel**
- Slide-out panel (right side)
- Form for editing all keyword fields
- Competitor management sub-component

**StatsBar**
- Display computed stats from API

**FilterBar**
- Search input (debounced)
- Dropdown filters for intent, status
- Expand/Collapse all buttons

### State Management
- Use Pinia store for:
  - Current project
  - Keywords tree
  - Filter state
  - Selected keyword (for detail panel)
  - Loading states

### Drag & Drop
- Use Vue Draggable Plus or similar library
- Support reordering within same level
- Support moving keywords to different parent (drop on another keyword to nest under it)
- Visual indicators for drop targets
- Emit position changes to API

### Key Interactions

1. **Expand/Collapse**: Toggle children visibility, persist state locally
2. **Filtering**: Apply filters → re-fetch or client-side filter → maintain tree structure for matches
3. **Add Keyword**: Modal or inline form, specify parent (or root level)
4. **Edit Keyword**: Open detail panel, edit, save
5. **Delete Keyword**: Confirm dialog, handle children (ask user or cascade)
6. **Reorder**: Drag within list → PATCH reorder endpoint
7. **Move**: Drag to different parent → PATCH move endpoint

### UI Notes
- Indent nested rows (16-24px per level)
- Collapse arrows only show when keyword has children
- Status colors: Active (green), Draft (gray), Planned (blue)
- Intent badges: Info (neutral), Commercial (orange/amber)
- Keep detail panel open while navigating tree; update content on new selection

---