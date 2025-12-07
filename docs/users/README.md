# Users Domain

## Purpose

Manages user accounts for the application.

## Backend

-   **UserController**: provides endpoint for listing all users in the application.
-   **User model**: stores user account details including name, email, password, and role ('admin' or 'guest').

## Frontend

-   **Pages**: `resources/js/pages/users/UsersIndex.vue` displays a table of all users with their name, email, role, and creation date.

## User Roles

-   **admin**: Full access to all features including user management, and administrative functions.
-   **guest**: Limited access to application features. Default role for new registrations.
