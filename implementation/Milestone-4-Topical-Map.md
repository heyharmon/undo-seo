__MILESTONE 4 OF 5__

Topical Map

*Estimated effort: 1\-2 days*

# Overview

Implement the ability to save keywords to a project's topical map and view/manage saved keywords\. The topical map is the user's curated list of target keywords — the output of their research\. By the end of this milestone, users have a complete keyword research workflow: expand → review → save → view saved\.

# Prerequisites

- Milestone 4 complete \(keyword research UI working\)

# Deliverables

1. API endpoints for saving/removing keywords from projects
2. Wire up "Save to Map" buttons in keyword results
3. Topical map display component \(list of saved keywords\)
4. Remove keywords from map functionality
5. Visual feedback for saved vs\. unsaved state

# Technical Requirements

## 1\. API Endpoints

__Method__

__URI__

__Purpose__

POST

/api/projects/\{id\}/keywords

Save keyword\(s\) to project

DELETE

/api/projects/\{id\}/keywords/\{kwId\}

Remove keyword from project

GET

/api/projects/\{id\}/keywords

List saved keywords \(optional if using Inertia props\)

## 2\. Save Keyword Logic

__POST /api/projects/\{id\}/keywords__

Request body:

- keyword\_id \(integer\) — ID of the keyword in the keywords table
- Or alternatively: keyword\_ids \(array\) — for bulk save

Logic:

1. Verify user owns the project
2. Verify keyword exists in keywords table
3. Check if keyword already saved \(don't duplicate\)
4. Insert into saved\_keywords pivot table
5. Return success with updated list of saved keyword IDs

## 3\. Remove Keyword Logic

__DELETE /api/projects/\{id\}/keywords/\{keywordId\}__

Logic:

1. Verify user owns the project
2. Delete the saved\_keywords record
3. Return success \(the keyword remains in the global keywords table\)

## 4\. Update Projects/Show\.vue

The project detail page now has two sections:

__Section A: Keyword Research \(existing from Milestone 4\)__

- Seed input and results table
- Now: wire up "Save to Map" button to call POST endpoint
- Show checkmark or "Saved" state if keyword is already in map
- Disable save button for already\-saved keywords \(or allow unsave\)

__Section B: Topical Map \(new\)__

- Display all saved keywords for this project
- Show same columns: keyword, volume, difficulty badge
- Add "Remove" action per keyword
- Show count of saved keywords
- Empty state: "Your topical map is empty\. Save keywords from your research above\."

## 5\. Page Layout Suggestion

Consider a two\-panel or tabbed layout:

__Option A: Vertical sections__

- Keyword Research on top
- Topical Map below \(collapsible or always visible\)

__Option B: Tabs__

- Tab 1: Research
- Tab 2: My Topical Map

__Option C: Side panel__

- Research fills main area
- Topical map in a collapsible side panel

Choose what feels best for the flow\. User will frequently switch between research and reviewing their map\.

## 6\. Data Flow

Pass saved keywords to the page via Inertia props:

- In ProjectController@show, load project with its saved keywords
- Pass savedKeywords \(with keyword data via eager loading\) to the Vue component
- Also pass savedKeywordIds as a simple array for quick lookup in the results table
- After save/remove, update local state or refetch via Inertia reload

# Acceptance Criteria

The milestone is complete when:

1. User can click "Save to Map" on a keyword in search results
2. Saved keyword appears in the Topical Map section
3. Already\-saved keywords show a different state in search results \(saved indicator\)
4. User can remove a keyword from the topical map
5. Removed keyword is removed from the map but still available in future searches
6. Topical map shows keyword count
7. Topical map persists — user can leave and return to see saved keywords
8. Cannot save same keyword twice \(idempotent\)
9. Empty state shows when no keywords saved yet

# Notes

- Consider optimistic UI updates — show saved state immediately, then sync with server
- Sorting/filtering the topical map is nice\-to\-have; can use same logic as search results
- "Save All Easy" bulk action would be cool but defer to V2
- This completes the core product loop — after this milestone, the product is functionally usable

