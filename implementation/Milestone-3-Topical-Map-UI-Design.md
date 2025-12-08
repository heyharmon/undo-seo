# Topical Map UI Design - Implementation Guide

## Overview

This describes the full-screen, modern layout for viewing and managing a project. The interface features a sidebar navigation for topic clusters and a data table for displaying keywords within selected cluster.

## Layout Structure

### Main Container
- Full viewport height layout with no scrolling at the root level
- Header is fixed at 48px height
- Content area fills remaining vertical space
- Two-column layout: sidebar (320px) + main content area (flexible)

### Header (48px fixed height)
Compact single-row design with three sections:

**Left Section:**
- Back button with left arrow icon
- Vertical separator line (subtle border)
- Project name (small, semibold)
- Seed keyword badge (small muted background)

**Center Section:**
- Statistics display (centered, subtle text)
- Shows: X clusters • Y keywords
- Uses bullet separator between stats

**Right Section:**
- Three action buttons (small size, minimal spacing)
  - "Get Suggestions" - default style - does nothing at this stage
  - "Regenerate" - default style with refresh icon - does nothing at this stage
  - "AI Enhance" - primary/accent color with sparkles icon - does nothing at this stage

All elements aligned vertically centered with minimal padding to maximize vertical space efficiency.

## Sidebar (320px width)

### Search Bar
- Located at top of sidebar
- Full-width input with search icon
- Placeholder: "Search clusters..."
- Small border radius, subtle border
- 8px padding around the container

### Cluster List
- Scrollable area (fills remaining sidebar height)
- 2px padding around scroll container
- 0.5px vertical spacing between items

### Cluster Item Structure
Each cluster item is a clickable row with:
- Hover state (subtle background change)
- Selected state (distinct background color, slightly bolder)
- Cursor changes to pointer on hover
- Rounded corners (6px)
- Small padding (6px vertical, 8px horizontal)

**Layout (horizontal flex):**
1. Expand/collapse button (if has sub-clusters)
   - Small chevron icon (right when collapsed, down when expanded)
   - 16px icon size
   - Stops propagation to prevent selecting cluster when toggling
   
2. Cluster name
   - Small font size
   - Medium weight for normal, semibold for selected
   - Truncates with ellipsis if too long
   
3. Keyword count
   - Muted text color
   - Format: "(X)"
   - Appears after cluster name
   
4. Difficulty indicator (right-aligned)
   - Small colored circle (6px diameter)
   - No text label
   - Colors: green (easy), amber (doable), red (hard)

### Nested Clusters
- Indented 16px from parent
- Same styling as parent clusters
- Can be nested multiple levels
- Expand/collapse animates smoothly

## Main Content Area

### Data Table
- Full width and height of content area
- Scrollable vertically only
- Clean, minimal borders (horizontal lines between rows)

### Table Structure

**Header Row:**
- Sticky to top when scrolling
- Light background color
- Small font size, medium weight
- Uppercase text with tight letter spacing
- Muted text color
- Small padding (8px vertical, 16px horizontal)

**Three Columns:**

1. **Keyword Column (flexible width, left-aligned)**
   - Primary data, larger font
   - Medium font weight
   - Full color (not muted)

2. **Volume Column (fixed width ~120px, right-aligned)**
   - Number formatted with commas (e.g., "12,500")
   - Muted text color
   - Regular weight

3. **Difficulty Column (fixed width ~120px, center-aligned)**
   - Badge component with:
     - Colored background (semi-transparent)
     - Text label (Easy/Doable/Hard)
     - Small rounded pill shape
     - Padding: 4px horizontal, 2px vertical
   - Colors match sidebar indicators:
     - Easy: green background, darker green text
     - Doable: amber background, darker amber text
     - Hard: red background, darker red text

**Data Rows:**
- Hover state (very subtle background change)
- 12px vertical padding, 16px horizontal padding
- Clean horizontal border between rows (very subtle)
- All text in row should be vertically centered

### Empty States

When no keywords match filters:
- Centered message in table area
- Icon above text
- Primary message: "No keywords found"
- Secondary message: "Try adjusting your search"
- Muted text colors

## Interaction Patterns

### Cluster Selection
- Click anywhere on cluster item to select it
- Only one cluster can be selected at a time
- Selecting a cluster updates the table to show its keywords
- Selected state persists until another cluster is clicked

### Cluster Expansion
- Click chevron button to expand/collapse
- Expanding reveals sub-clusters (indented)
- Expanding does NOT automatically select the cluster
- Multiple clusters can be expanded simultaneously
- Clicking chevron does not trigger selection

### Search/Filter
- Filters clusters by name in real-time
- Case-insensitive partial match
- Preserves hierarchy (shows parents if child matches)
- Updates results as user types

### Table Sorting
- Clicking column headers sorts the table
- First click: ascending
- Second click: descending
- Third click: returns to default order
- Visual indicator (arrow) shows current sort direction

## Responsive Considerations

For screens smaller than 1024px:
- Sidebar collapses to drawer/overlay
- Hamburger menu button appears in header
- Table columns may stack or horizontal scroll
- Action buttons may reduce size or hide labels

## Performance Notes

- Table should virtualize rows if displaying >100 keywords
- Sidebar should virtualize if displaying >50 clusters
- Search/filter should debounce input (200-300ms)
- Smooth animations for expand/collapse (150-200ms duration)

## Accessibility

- All interactive elements keyboard accessible
- Tab order: header actions → sidebar search → clusters → table
- Arrow keys navigate table rows
- Enter key selects focused cluster
- Screen reader announcements for state changes
- Proper ARIA labels for icon-only buttons
- Focus indicators visible on all interactive elements

## Design Principles

1. **Minimal vertical space**: Every pixel of header counts
2. **Information density**: Show maximum data without clutter
3. **Visual hierarchy**: Difficulty badges draw eye appropriately
4. **Smooth interactions**: All state changes animate naturally
5. **Clean aesthetics**: Inspired by Stripe, Attio, Linear design systems
6. **Functional efficiency**: Two-click maximum to any keyword

## Key Visual Details

- All animations: ease-in-out timing function
- Difficulty circles: 6px diameter with solid fill
- Icon sizes: 16px for sidebar, 14px for table, 20px for header
