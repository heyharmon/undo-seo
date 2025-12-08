__MILESTONE 1 OF 4__

Project Management

*Estimated effort: 1 day*

# Overview

Implement CRUD operations for projects\. Users can create, view, edit, and delete projects\. Each project will eventually contain a topical map of keywords\. By the end of this milestone, users have a functional project management interface\.

# Prerequisites

- Auth working \(user registration and login\)
- Database schema in place

# Deliverables

1. ProjectController with CRUD actions
2. Vue components for project list, create, edit
3. Project detail page \(placeholder for keyword research\)
4. Form validation and error handling

# Technical Requirements

## 1\. Routes

__Method__

__URI__

__Action__

__Name__

GET

/projects

List user's projects

projects\.index

GET

/projects/create

Show create form

projects\.create

POST

/projects

Store new project

projects\.store

GET

/projects/\{id\}

Show project detail

projects\.show

GET

/projects/\{id\}/edit

Show edit form

projects\.edit

PUT

/projects/\{id\}

Update project

projects\.update

DELETE

/projects/\{id\}

Delete project

projects\.destroy

## 2\. Controller Logic

ProjectController should:

- Scope all queries to the authenticated user \(users can only see/edit their own projects\)
- Use Form Requests for validation \(or inline validation\)
- Return appropriate Inertia responses
- Use route model binding where appropriate

## 3\. Validation Rules

Project creation/update:

- __name: __required, string, max 255 characters
- That's it for MVP — keep it simple

## 4\. Vue Components

__Projects/Index\.vue__

- Display list of user's projects as cards or rows
- Each project shows: name, created date, keyword count \(0 for now\)
- "Create New Project" button
- Empty state when no projects exist
- Click project to go to detail page

__Projects/Create\.vue__

- Simple form with name input
- Submit and Cancel buttons
- Display validation errors inline

__Projects/Edit\.vue__

- Same as Create but pre\-populated
- Add Delete button with confirmation

__Projects/Show\.vue__

- Project name as header
- Edit button
- Placeholder section: "Your topical map will appear here" \(Milestone 3 adds the full UI\)

## 5\. Navigation Updates

- Change dashboard redirect to /projects
- Add "Projects" link to main navigation
- Breadcrumbs are optional for MVP

# Acceptance Criteria

The milestone is complete when:

1. User can create a new project with a name
2. User sees a list of their projects on /projects
3. User can click a project to view its detail page
4. User can edit a project's name
5. User can delete a project \(with confirmation\)
6. User cannot see or access another user's projects
7. Validation errors display when submitting empty name
8. Empty state shows when user has no projects

# Notes

- Consider using a Policy class for authorization, but inline checks are fine for MVP
- The project detail page will grow significantly in later milestones — keep layout flexible
- Delete confirmation can be a simple browser confirm\(\) or a modal — your choice
- Flash messages for success/error states are nice to have but not required

