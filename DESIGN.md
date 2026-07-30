---
name: FS PBX
description: A calm, compact operations console for administering business communications.
colors:
  signal-indigo: "#4F46E5"
  signal-indigo-hover: "#6366F1"
  signal-indigo-deep: "#3730A3"
  working-blue: "#2563EB"
  paper-surface: "#FFFFFF"
  quiet-surface: "#F9FAFB"
  console-canvas: "#F3F4F6"
  divider: "#E5E7EB"
  control-border: "#D1D5DB"
  operator-ink: "#111827"
  secondary-text: "#6B7280"
  muted-text: "#9CA3AF"
  success: "#15803D"
  success-surface: "#F0FDF4"
  danger: "#B91C1C"
  danger-surface: "#FEF2F2"
  warning: "#A16207"
  warning-surface: "#FEFCE8"
typography:
  headline:
    fontFamily: "Nunito, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.5
    letterSpacing: "normal"
  title:
    fontFamily: "Nunito, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.5
    letterSpacing: "normal"
  body:
    fontFamily: "Nunito, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  label:
    fontFamily: "Nunito, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "normal"
rounded:
  sm: "0.25rem"
  md: "0.375rem"
  lg: "0.5rem"
  full: "9999px"
spacing:
  xs: "0.25rem"
  sm: "0.5rem"
  md: "1rem"
  lg: "1.5rem"
  xl: "2rem"
components:
  button-primary:
    backgroundColor: "{colors.signal-indigo}"
    textColor: "{colors.paper-surface}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "0.375rem 0.625rem"
  button-primary-hover:
    backgroundColor: "{colors.signal-indigo-hover}"
    textColor: "{colors.paper-surface}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "0.375rem 0.625rem"
  button-secondary:
    backgroundColor: "{colors.paper-surface}"
    textColor: "{colors.operator-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "0.375rem 0.625rem"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.paper-surface}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "0.5rem 0.75rem"
  input:
    backgroundColor: "{colors.paper-surface}"
    textColor: "{colors.operator-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "0.375rem 0.75rem"
  badge-success:
    backgroundColor: "{colors.success-surface}"
    textColor: "{colors.success}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "0.25rem 0.5rem"
  badge-danger:
    backgroundColor: "{colors.danger-surface}"
    textColor: "{colors.danger}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "0.25rem 0.5rem"
  panel:
    backgroundColor: "{colors.quiet-surface}"
    textColor: "{colors.secondary-text}"
    rounded: "{rounded.md}"
    padding: "1.5rem"
---

# Design System: FS PBX

## Overview

**Creative North Star: "The Operations Console"**

FS PBX is a calm, compact working surface for PBX administrators and communications operators. Picture an administrator scanning routes, registrations, messages, and logs on a desktop monitor in a bright office or equipment room: the light theme keeps dense operational data legible, while restrained Signal Indigo marks the few controls and states that require attention.

The interface is practical, dependable, and direct. Familiar tables, filters, side navigation, forms, and dialogs should disappear into the task. FS PBX explicitly rejects marketing-style admin UI, decorative dashboards, novelty interactions, inconsistent component patterns, and visual treatments that compete with operational data.

**Key Characteristics:**

- Compact, scannable information density.
- Quiet gray surfaces with Signal Indigo used sparingly.
- Familiar administrative controls with explicit states and permissions.
- Technical detail preserved through truncation, expansion, tooltips, and progressive disclosure.
- Responsive structure based on breakpoints, not fluid type or decorative rearrangement.

## Colors

The palette pairs one focused indigo signal with a cool operational gray scale and restrained semantic colors.

### Primary

- **Signal Indigo** (`#4F46E5`): Primary actions, current navigation icons, selected controls, and important focus states.
- **Signal Indigo Lift** (`#6366F1`): Primary-button hover and VueForm's canonical primary token.
- **Signal Indigo Depth** (`#3730A3`): Pressed states and VueForm's darker primary token.

### Secondary

- **Working Blue** (`#2563EB`): Search-field focus rings, links, and interactive text where blue already communicates navigation rather than primary action.

### Neutral

- **Paper Surface** (`#FFFFFF`): Tables, controls, navigation, menus, and dialogs.
- **Quiet Surface** (`#F9FAFB`): Table headers, expanded rows, notifications, and subtle grouped content.
- **Console Canvas** (`#F3F4F6`): Page background and inactive navigation surfaces.
- **Divider** (`#E5E7EB`): Row separators and low-emphasis boundaries.
- **Control Border** (`#D1D5DB`): Inputs, secondary buttons, pagination, and explicit control edges.
- **Operator Ink** (`#111827`): Headings, control labels, and values requiring strongest contrast.
- **Secondary Text** (`#6B7280`): Table values, descriptions, breadcrumbs, and supporting copy.
- **Muted Text** (`#9CA3AF`): Icons, placeholders, inactive state, and tertiary metadata.

### Semantic

- **Success Green** (`#15803D`) on **Success Surface** (`#F0FDF4`): Enabled, healthy, complete, and received states.
- **Danger Red** (`#B91C1C`) on **Danger Surface** (`#FEF2F2`): Failed, disabled, destructive, and invalid states.
- **Warning Amber** (`#A16207`) on **Warning Surface** (`#FEFCE8`): Pending, degraded, and attention-required states.

**The Signal, Not Decoration Rule.** Signal Indigo must remain under roughly 10% of a normal screen. Use it for primary action, selection, and focus, never to decorate inactive surfaces.

**The Semantic Pair Rule.** Status must combine color with a text label, icon, or both. Color alone is never sufficient.

## Typography

**Display Font:** Nunito (with `sans-serif` fallback)  
**Body Font:** Nunito (with `sans-serif` fallback)  
**Label/Mono Font:** Nunito for labels; the platform monospace stack is reserved for identifiers, code, payloads, and technical output.

**Character:** Nunito keeps the console approachable without becoming informal. A compact fixed scale and moderate weight contrast support dense operational reading.

### Hierarchy

- **Headline** (600, `1.125rem`, 1.5): Page and section headings.
- **Title** (600, `1rem`, 1.5): Dialog titles, panel titles, and emphasized row groups.
- **Body** (400, `0.875rem`, 1.5): Tables, forms, descriptions, buttons, and navigation.
- **Label** (600, `0.75rem`, 1.25): Badges, metadata labels, compact state, and small utility controls.

**The Fixed Scale Rule.** Product typography uses fixed rem sizes. Never introduce fluid display type, oversized marketing headings, or a second decorative font.

**The Technical Detail Rule.** Long prose should stay within 65–75 characters per line. Tables may remain dense, but identifiers and URLs must truncate in-row and remain fully available in an expanded view or tooltip.

## Elevation

FS PBX uses a hybrid of tonal layering, borders, and restrained shadows. Surfaces are flat by default; shadows identify floating menus, dialogs, notifications, or a contained table, not ordinary content groups.

### Shadow Vocabulary

- **Control Lift** (`0 1px 2px 0 rgb(0 0 0 / 0.05)`): Primary and secondary buttons.
- **Contained Surface** (`0 1px 3px 0 rgb(0 0 0 / 0.10), 0 1px 2px -1px rgb(0 0 0 / 0.10)`): Tables and page-level working panels.
- **Floating Menu** (`0 20px 25px -5px rgb(0 0 0 / 0.10), 0 8px 10px -6px rgb(0 0 0 / 0.10)`): Menus, dialogs, side panels, and transient overlays.

**The Structural Elevation Rule.** Use borders and tonal changes for persistent structure. Reserve strong shadows for elements that physically float above the working surface.

**The Single Container Rule.** One page-level working surface is sufficient. Nested cards and repeated shadow boxes are prohibited.

## Components

### Buttons

- **Shape:** Gently curved rectangle (`0.375rem`).
- **Primary:** Signal Indigo background, Paper Surface text, semibold body type, and compact padding (`0.375rem 0.625rem`).
- **Hover / Focus:** Lift to `#6366F1`; show a visible two-pixel indigo focus outline or ring with offset. State transitions should complete in 100–150ms.
- **Secondary:** Paper Surface with Operator Ink, a one-pixel Control Border inset ring, and the same dimensions as primary.
- **Destructive:** Danger Red with explicit destructive copy. Never use icon or color alone to imply deletion.

### Chips

- **Style:** Pale semantic surface, dark semantic text, one-pixel inset ring when additional separation is needed, medium corners (`0.375rem`).
- **State:** Always carry a readable state label such as Enabled, Failed, Pending, or Received.

### Cards / Containers

- **Corner Style:** Medium to gently rounded (`0.375rem–0.5rem`).
- **Background:** Paper Surface for floating content; Quiet Surface for nested tonal sections.
- **Shadow Strategy:** Flat by default, with Contained Surface elevation only at page-level boundaries.
- **Border:** Divider for persistent grouping; Control Border for interactive boundaries.
- **Internal Padding:** `1rem` for compact content and `1.5rem` for page-level working panels.

### Inputs / Fields

- **Style:** Paper Surface, Operator Ink, medium corners (`0.375rem`), and a one-pixel Control Border inset ring.
- **Focus:** Two-pixel Working Blue or Signal Indigo inset ring. Placeholder text uses Muted Text.
- **Error / Disabled:** Errors pair Danger Red with readable validation copy. Disabled controls reduce contrast but remain legible and preserve their label.

### Tables

- **Structure:** Quiet Surface header, Paper Surface body, Divider row separators, compact `0.5rem` vertical cell padding, and horizontal overflow on narrow screens.
- **Data density:** Keep dates, states, and actions on one line. Truncate unbounded values such as URLs and descriptions, then reveal the full value through title text, tooltips, or expandable details.
- **Interaction:** Hover uses Quiet Surface. Sort controls, selection checkboxes, row expansion, pagination, and loading states must retain visible focus and explicit labels.

### Navigation

- **Style:** Paper Surface top navigation and side navigation, Nunito body type, gray inactive labels, and Signal Indigo active icons.
- **Active / Hover:** Active rows use Console Canvas or Quiet Surface plus Signal Indigo iconography. Hover changes the tonal surface without moving layout.
- **Responsive:** Collapse the side navigation below the medium breakpoint (`768px`) and preserve icon tooltips or accessible labels.

### Dialogs and Notifications

- **Dialogs:** Paper Surface, gently rounded corners (`0.5rem`), Floating Menu elevation, restrained gray overlay, visible close control, and explicit title.
- **Notifications:** Compact, top-right on larger screens and full-width on small screens. Success and error pair icons, headings, and message text.
- **Motion:** Use 100–300ms state transitions. Motion must explain opening, closing, loading, or selection; decorative choreography is forbidden.

## Do's and Don'ts

### Do:

- **Do** use Signal Indigo (`#4F46E5`) for the primary action, active state, and focus indication.
- **Do** use Paper Surface, Quiet Surface, Console Canvas, Divider, and Control Border to establish hierarchy before adding shadows.
- **Do** keep tables compact and scannable, with responsive horizontal overflow and bounded columns.
- **Do** preserve technical detail through expandable rows, tooltips, copy controls, and monospace output.
- **Do** pass page-specific permissions into the page and hide or disable unavailable actions explicitly.
- **Do** provide default, hover, focus, active, disabled, loading, error, and empty states for interactive components.
- **Do** use text labels and icons alongside semantic color.
- **Do** reuse VueForm, Headless UI, Heroicons, DataTable, Paginator, Badge, Notification, and established FS PBX page patterns.

### Don't:

- **Don't** create marketing-style admin UI, decorative dashboards, or oversized hero metrics.
- **Don't** add novelty interactions, gratuitous animation, or motion unrelated to state.
- **Don't** introduce inconsistent component patterns; the same action must look and behave the same across pages.
- **Don't** use visual treatments that compete with operational data.
- **Don't** disguise technical state or destructive actions.
- **Don't** use gradient text, decorative glassmorphism, colored side-stripe borders, or repeated identical card grids.
- **Don't** wrap every content group in a card or nest cards inside cards.
- **Don't** let unbounded URLs, payloads, identifiers, or descriptions expand a table beyond the viewport.
- **Don't** rely on color alone for status, error, warning, selection, or health.
- **Don't** introduce a second display font, fluid product typography, or decorative uppercase tracking in ordinary controls.
