__MILESTONE 3 OF 5__

Keyword Research UI

*Estimated effort: 2 days*

# Overview

Build the frontend interface for keyword research\. This is the heart of the product — where users enter a seed keyword and immediately see related keywords labeled as Easy, Doable, or Hard\. 

__This is the aha moment: __users should feel instant clarity about which keywords they can target\.

# Prerequisites

- Milestone 3 complete \(API endpoint working, returning keyword data\)

# Deliverables

1. Seed keyword input component
2. Keyword results table/list with sorting and filtering
3. Visual difficulty labels \(Easy/Doable/Hard badges\)
4. Loading states
5. "Save to Map" button per keyword \(wired up in Milestone 5\)

# Technical Requirements

## 1\. Update Projects/Show\.vue

Replace the placeholder content with the keyword research interface\. The project detail page becomes the main workspace\.

## 2\. Seed Keyword Input

- Text input with placeholder: "Enter a seed keyword \(e\.g\., dog training\)"
- "Expand" or "Find Keywords" button
- Disable button while loading
- Allow submit on Enter key
- Clear previous results when new search begins

## 3\. Keyword Results Display

Display results in a table or card list\. Table recommended for scannability\.

__Columns:__

__Column__

__Details__

Keyword

The keyword text

Volume

Monthly search volume\. Format with commas \(e\.g\., 12,500\)

Difficulty

Badge showing Easy \(green\), Doable \(yellow\), or Hard \(red\)

Actions

"Save to Map" button \(or \+ icon\)

## 4\. Difficulty Badges

Visual design for the difficulty labels\. Keep it simple but clear:

- __Easy: __Green background, dark green text \(e\.g\., Tailwind: bg\-green\-100 text\-green\-800\)
- __Doable: __Yellow/amber background, dark amber text \(e\.g\., bg\-yellow\-100 text\-yellow\-800\)
- __Hard: __Red background, dark red text \(e\.g\., bg\-red\-100 text\-red\-800\)
- Use pill/badge shape with rounded corners

## 5\. Sorting and Filtering

Allow users to quickly find the best opportunities:

__Sorting \(click column headers\):__

- By volume \(high to low, low to high\)
- By difficulty \(easy first, hard first\)
- Alphabetical \(optional\)

__Filtering:__

- Filter by difficulty: Show All | Easy Only | Easy \+ Doable | Hard Only
- This could be tabs, buttons, or a dropdown
- Default to "Show All"

## 6\. Loading and Empty States

__Loading state:__

- Show spinner or skeleton while fetching
- "Finding keywords\.\.\." message
- Disable input during load

__Empty state \(before first search\):__

- "Enter a seed keyword above to discover related keywords you can rank for\."

__No results state:__

- "No keywords found\. Try a different seed keyword\."

## 7\. "Save to Map" Button

- Each keyword row has a button/icon to save it
- For this milestone, the button can be non\-functional or show an alert
- Milestone 5 will wire up the actual save functionality
- Consider: change button state if keyword is already saved \(requires passing saved keywords to component\)

## 8\. API Integration

- Call POST /api/keywords/expand on form submit
- Pass: keyword \(from input\), project\_id \(from page props\)
- Handle success: populate results table
- Handle error: show error message, allow retry

# Acceptance Criteria

The milestone is complete when:

1. User can enter a seed keyword on the project page
2. Clicking "Expand" fetches and displays related keywords
3. Each keyword shows: keyword text, search volume, difficulty badge
4. Difficulty badges are color\-coded \(green/yellow/red\)
5. User can sort results by volume or difficulty
6. User can filter to show Easy only \(or other difficulty filters\)
7. Loading state displays while fetching
8. Empty state displays before first search
9. Error state displays if API call fails
10. "Save to Map" button exists \(functionality in Milestone 5\)

# UX Notes

- __Speed matters\. __Users should see results as fast as possible\. Cached results should feel instant\.
- __Easy keywords should pop\. __The green Easy badges should visually stand out — these are the quick wins\.
- __Keep it scannable\. __Users may see 50\-100 keywords\. The table should be easy to scan quickly\.
- __Default sort by opportunity\. __Consider defaulting to sort by difficulty ascending \(Easy first\) since that's the primary value\.

# Notes

- This is the core UI of the product — invest time in making it feel good
- All sorting and filtering can be client\-side since data is already loaded
- Consider showing the raw difficulty score as a tooltip on the badge
- You may want to track which seed keywords have been searched \(history\) — defer to V2

