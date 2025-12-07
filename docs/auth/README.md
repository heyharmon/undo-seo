# Auth Domain

## Purpose

Manages user authentication including standard registration, login, logout and fetching the current user.

## Backend

-   **AuthController**: provides endpoints for registering, logging in, logging out and retrieving the current user. New users are assigned the 'guest' role by default.
-   **User model**: stores account details including a role field ('admin' or 'guest').

## Frontend

-   **Pages**: `resources/js/pages/auth/Login.vue` and `resources/js/pages/auth/Register.vue` implement the login and registration forms.
-   **Service**: `resources/js/services/auth.js` wraps authentication API calls and persists tokens in `localStorage`.
