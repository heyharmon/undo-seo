# Application Architecture

## Overview

This is a Laravel 12 API application with a Vue.js frontend. The application follows conventional Laravel patterns on the backend and uses Vue Router for client-side routing on the frontend.

## Backend Architecture

### Framework & Structure
- **Laravel 12** API-only application
- Follows standard Laravel conventions and directory structure
- RESTful API endpoints defined in `routes/api.php`
- JSON responses for all API endpoints

### Current Implementation
- **Models**: `User` model with role-based access (admin/guest)
- **Controllers**: 
  - `AuthController` - handles registration, login, logout, and current user retrieval
  - `UserController` - provides user listing and detail endpoints
- **Middleware**: `EnsureUserIsAdmin` for admin-only route protection
- **Authentication**: Laravel Sanctum for API token authentication

### Conventions
- Resourceful controller methods only: `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`
- Implicit route model binding where possible

## Frontend Architecture

### Framework & Tools
- **Vue 3** with Composition API (script setup style)
- **Vue Router** for client-side routing
- **Pinia** for state management (when needed)
- **Tailwind CSS** for styling

### Directory Structure
```
resources/js/
├── components/
│   ├── globals/        # Global components (AdminNav)
│   └── ui/             # Reusable UI components (Button, Input, Pagination, etc.)
├── composables/        # Shared functionality via composables
├── layouts/            # Page layouts (DefaultLayout, TwoColumnLayout)
├── pages/              # Route-based page components
│   ├── auth/           # Login, Register
│   ├── dashboard/      # Dashboard (admin entry point)
│   └── users/          # UsersIndex, UserShow
├── router/             # Vue Router configuration
├── services/           # API interaction layer
│   ├── api.js          # Base API service
│   └── auth.js         # Authentication service
├── stores/             # Pinia stores
└── utils/              # Utility functions
```

### Key Patterns

#### Services Layer
All backend API interactions go through service modules:
- **api.js**: Base API service with common HTTP methods
- **auth.js**: Authentication-specific methods, token management, and user state

#### Authentication & Authorization
- Token-based authentication using localStorage
- Authorization checks in Vue Router navigation guards
- Admin-restricted routes protected at the router level
- User role and authentication state managed by auth service

#### State Management
- **Pinia** stores for application-wide state (when needed)
- **Composables** for shared reactive functionality
- Local component state for component-specific data

#### Component Architecture
- Composition API with `<script setup>` syntax
- Import order: dependencies → components → layouts
- Use `@` alias for imports
- Prefer existing components from `components/ui/` before creating new ones
- Radix Vue components as foundation for new UI elements

#### Styling
- Tailwind CSS utility classes
- Neutral color palette for backgrounds (`bg-neutral-*`)
- No custom CSS unless absolutely necessary

## Current Features

### Authentication
- User registration (default role: guest)
- User login/logout
- Token-based session management
- Current user retrieval

### User Management
- List all users (admin only)
- View user details
- Role-based access control (admin/guest)

### Dashboard
- Blank dashboard as admin entry point
- Protected by admin authorization

## Development Guidelines

### Backend
- Always use API routes in `routes/api.php`
- Return JSON responses from all controllers
- Use implicit route model binding
- Reference adjacent controllers for patterns
- No requests, resources, or tests unless explicitly requested

### Frontend
- No TypeScript (leave existing TS alone if present)
- Use script setup style in Vue components
- Check `resources/js/components` before creating new components
- Use Radix Vue components when available
- Import components after all other imports, layouts after components
- Use `@` for all imports

### General
- Challenge patterns that don't align with Laravel/Vue conventions
- Keep code clean, readable, and following established patterns
- Ask clarifying questions when needed
