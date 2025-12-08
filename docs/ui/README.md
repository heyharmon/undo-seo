# UI Design Guidelines

This app follows a clean, modern aesthetic inspired by OpenAI and Stripe—compact yet open, with intentional use of negative space.

## Core Principles

1. **Compact, not cramped** — Minimize vertical space while maintaining breathing room
2. **Restrained styling** — Subtle borders, soft shadows, muted colors
3. **Functional elegance** — Every element earns its place

## Layout

- Max content width: `max-w-[1563px]` for main container
- Page padding: `py-8` vertical, `px-4` horizontal
- Form containers: `max-w-lg mx-auto` for centered forms
- Grid layouts: `gap-3` between cards, `sm:grid-cols-2 lg:grid-cols-3`

## Typography

- Page titles: `text-xl font-semibold text-neutral-900`
- Subtitles: `text-sm text-neutral-500` with `mt-0.5`
- Body text: `text-sm text-neutral-600`
- Labels: `text-sm font-medium text-neutral-700`

## Colors

Use the neutral palette exclusively:
- Text: `neutral-900` (primary), `neutral-600` (secondary), `neutral-500` (muted)
- Backgrounds: `white`, `neutral-50/50` (subtle), `neutral-100` (hover)
- Borders: `neutral-200` (default), `neutral-300` (hover)
- Accents: `neutral-900` for primary buttons and active states

## Components

### Cards
```
rounded-lg border border-neutral-200 bg-white p-4
hover:border-neutral-300 hover:shadow-sm transition
```

### Buttons
- Primary: `bg-black text-white hover:bg-black/80`
- Secondary: `bg-neutral-100 hover:bg-neutral-200`
- Ghost: `hover:bg-neutral-100`
- Destructive: Use sparingly, left-aligned in forms

### Empty States
```
rounded-xl border border-dashed border-neutral-300 bg-neutral-50/50
py-16 text-center
```
Include: icon (`h-10 w-10 text-neutral-400`), heading, description, optional CTA

### Forms
- Input spacing: `mt-1.5` after labels
- Error text: `mt-1.5 text-sm text-red-600`
- Button row: `mt-5 flex items-center justify-end gap-2`
- Destructive actions: left side, primary actions: right side

## Spacing Patterns

| Context | Spacing |
|---------|---------|
| Between page header and content | `mb-6` |
| Between form fields | Stack naturally, no extra margin |
| Button groups | `gap-2` |
| Card content | `p-4` or `p-5` |

## Badges & Pills

```
inline-flex items-center rounded-full px-2 py-0.5
text-xs font-medium bg-neutral-100 text-neutral-600
```

## Navigation

- Active state: `bg-neutral-900 text-white`
- Hover state: `hover:bg-neutral-100`
- Use `rounded-full` for nav pills

## Icons

- Standard size: `h-4 w-4`
- Large (empty states): `h-10 w-10`
- Color: `text-neutral-400` or `text-neutral-500`
- Stroke width: `1.5` or `2`

## Do's and Don'ts

**Do:**
- Use existing components from `components/ui/`
- Keep forms narrow and centered
- Use subtle hover transitions
- Prefer grid layouts for lists of items

**Don't:**
- Add decorative elements without purpose
- Use bright colors or heavy shadows
- Create tall, scrolling forms
- Add excessive padding or margins
