---
name: FS PBX
description: Dense, calm operational UI for modern PBX administration.
colors:
  accent-indigo: "#4f46e5"
  accent-indigo-soft: "#6366f1"
  accent-indigo-deep: "#3730a3"
  action-blue: "#2563eb"
  surface-white: "#ffffff"
  surface-muted: "#f9fafb"
  surface-raised: "#f3f4f6"
  border-muted: "#d1d5db"
  border-soft: "#e5e7eb"
  text-strong: "#111827"
  text-default: "#374151"
  text-muted: "#6b7280"
  text-subtle: "#9ca3af"
  success-fill: "#dcfce7"
  success-text: "#15803d"
  warning-fill: "#fef3c7"
  warning-text: "#b45309"
  danger-fill: "#ffe4e6"
  danger-text: "#be123c"
typography:
  headline:
    fontFamily: "ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.33
    letterSpacing: "normal"
  title:
    fontFamily: "ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.5
    letterSpacing: "normal"
  body:
    fontFamily: "ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
  label:
    fontFamily: "ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.33
    letterSpacing: "0.05em"
rounded:
  sm: "2px"
  md: "6px"
  lg: "8px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  page: "12px"
components:
  button-primary:
    backgroundColor: "{colors.accent-indigo}"
    textColor: "{colors.surface-white}"
    rounded: "{rounded.md}"
    padding: "6px 10px"
  button-secondary:
    backgroundColor: "{colors.surface-white}"
    textColor: "{colors.text-strong}"
    rounded: "{rounded.md}"
    padding: "6px 10px"
  input-search:
    backgroundColor: "{colors.surface-white}"
    textColor: "{colors.text-strong}"
    rounded: "{rounded.md}"
    padding: "6px 12px 6px 40px"
  badge-info:
    backgroundColor: "#dbeafe"
    textColor: "#1e40af"
    rounded: "{rounded.md}"
    padding: "4px 8px"
---

# Design System: FS PBX

## 1. Overview

**Creative North Star: "The Calm Control Room"**

FS PBX is an operational control room for complex phone-system work. The interface should feel modern, calm, and elegant while staying practical enough for repeated admin tasks, troubleshooting, and tenant operations. It is dense by design, but density must be structured, readable, and predictable.

The system follows familiar Laravel, Vue, Inertia, Tailwind, Headless UI, and VueForm conventions. New screens should start from proven app patterns such as Devices, Ring Groups, Extensions, Default Settings, and Conference-style pages before inventing a new layout. The design serves the task: tables, filters, modals, drawers, status badges, and action buttons should help mixed-skill users understand what is happening and what they can safely do next.

This visual system explicitly rejects legacy FusionPBX clutter, dense visual noise, scattered actions, ambiguous form layouts, and pages that require tribal knowledge. It also rejects marketing-style admin UI, decorative excess, oversized hero sections, and generic SaaS gloss that slows down operational work.

**Key Characteristics:**
- Restrained, light operational surfaces with indigo reserved for primary actions and active states.
- Dense tables and forms with compact spacing, clear headers, and predictable action placement.
- Calm semantic status colors that pair color with labels, counts, or icons.
- Familiar controls that prioritize confidence over novelty.
- VueForm and Headless UI patterns used consistently across create, update, confirmation, and selection flows.

## 2. Colors

The palette is a restrained product system: white and gray surfaces carry most of the screen, with indigo as the product accent and small semantic colors for status.

### Primary
- **Control Indigo**: The main action and selection color. Use it for primary buttons, checked inputs, toggles, active filters, and focus rings. Its scarcity gives it authority.
- **Soft Control Indigo**: The hover and VueForm primary tone. Use it for lighter active affordances and hover states that need a softer transition.
- **Deep Control Indigo**: The darker VueForm primary state. Use it sparingly for pressed states or high-contrast active surfaces.

### Secondary
- **Action Blue**: A legacy-adjacent action blue appears in some filters, pagination, and secondary action states. Use it only when matching an existing page pattern. Prefer Control Indigo for new primary actions.

### Tertiary
- **Semantic Green**: Success and enabled states. Pair it with text such as Enabled, Available, Provisioned, or a count.
- **Semantic Amber**: Warning, pending, or override states. Use it for waiting, caution, or configuration drift.
- **Semantic Rose**: Destructive, disabled, suspended, or error states. Use it plainly and never as decoration.

### Neutral
- **Operational White**: Primary content surface for nav, tables, modals, cards, and form controls.
- **Soft Workbench**: Quiet page and table-header background. Use it to separate groups without adding ornament.
- **Raised Gray**: Hover and secondary surface feedback.
- **Muted Border**: Default input and card border.
- **Soft Divider**: Table row, panel, and list separation.
- **Strong Ink**: Headings and important row values.
- **Default Ink**: Navigation, secondary buttons, and normal readable UI text.
- **Muted Ink**: Table body text, descriptions, metadata, and supporting copy.
- **Subtle Ink**: Icons, placeholders, disabled hints, and low-priority affordances.

### Named Rules

**The Accent Budget Rule.** Indigo is for action, selection, focus, and current state. Do not use it as page decoration.

**The Status Pairing Rule.** Status color must be paired with a label, number, icon, or tooltip. Never rely on color alone for telephony state.

## 3. Typography

**Display Font:** System sans stack.
**Body Font:** System sans stack.
**Label/Mono Font:** System sans for labels; monospace only for values, identifiers, technical keys, and copied settings.

**Character:** The type system is quiet, compact, and native-feeling. It should read like a serious admin tool: clear enough for newer users, dense enough for experienced operators.

### Hierarchy
- **Display** (600, 1.5rem, 1.33): Rare. Use for true page-level headings in newer operational pages such as settings surfaces.
- **Headline** (600, 1.25rem to 1.5rem, 1.33): Page titles and major modal contexts.
- **Title** (600, 1rem, 1.5): Panel titles, modal titles, table section headers, and grouped form headings.
- **Body** (400, 0.875rem, 1.5): Table cells, descriptions, form text, filter labels, and operational copy.
- **Label** (500 to 600, 0.75rem, normal to 0.05em): Small labels, category headers, badges, and compact metadata. Uppercase labels are allowed only for short scannable categories.

### Named Rules

**The Native Tool Rule.** Use one sans-serif family for almost everything. Do not introduce display fonts into labels, buttons, tables, or forms.

**The Plain Label Rule.** Labels should describe the actual system object or action. Avoid clever wording around telephony, permissions, and destructive actions.

## 4. Elevation

FS PBX uses a hybrid of thin borders, light rings, and restrained shadows. Most surfaces sit flat at rest; elevation appears for tables, cards, dropdowns, modals, and menus where layering affects usability. Depth should clarify interaction priority, not decorate the screen.

### Shadow Vocabulary
- **Table Shell** (`box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05)` plus a subtle ring): Used around dense data tables and compact panels.
- **Raised Control** (`box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05)`): Used on secondary buttons and compact action controls.
- **Dropdown Lift** (`box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)`): Used for top nav menus and floating lists.
- **Modal Lift** (`box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)`): Used for dialogs and drawers over a gray overlay.
- **Multiselect Lift** (`box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25)`): Existing multiselect dropdown treatment. Use only where that component requires it.

### Named Rules

**The Flat-Until-Useful Rule.** A surface starts flat. Add shadow only when it floats, contains a table, opens over content, or must be visually separated from a busy operational area.

## 5. Components

### Buttons

Buttons are compact, familiar, and direct. They should look like tools, not promotional calls to action.

- **Shape:** Gently curved rectangles (6px radius).
- **Primary:** Control Indigo background, white text, compact padding (usually 6px vertical and 10px to 12px horizontal), semibold 0.875rem text.
- **Hover / Focus:** Hover moves to Soft Control Indigo. Focus uses a 2px indigo ring or visible outline. Disabled states lower opacity and use a not-allowed cursor.
- **Secondary / Ghost / Tertiary:** White background, Strong Ink text, gray ring, subtle shadow, and Soft Workbench hover. Use these for navigation, import/export, reload, reset, and non-destructive secondary actions.
- **Icon Buttons:** Round or rounded-full 36px action targets with gray icons, Soft Workbench hover, and clear tooltip text.

### Chips

Chips are compact operational labels, not decoration.

- **Style:** 6px radius, 0.75rem text, medium or semibold weight, soft fill, matching text, and a faint inset ring when needed.
- **State:** Blue chips can mark forwarding or informational state, green chips can mark enabled or available state, amber chips can mark pending or override state, and rose chips can mark suspended, disabled, destructive, or error state.
- **Behavior:** Clickable chips must look actionable through hover state or tooltip. Passive chips should stay visually quieter.

### Cards / Containers

Containers frame repeated operational content without making every section feel like a card.

- **Corner Style:** 8px radius for settings panels and modal shells; 6px radius for table shells and compact controls.
- **Background:** Operational White for primary surfaces, Soft Workbench for headers, hover rows, and nested details.
- **Shadow Strategy:** Use restrained shadow plus gray ring only when the surface is a table shell, panel, dropdown, or modal.
- **Border:** Prefer `ring-1 ring-gray-200` or `border-gray-200` for structure. Do not use decorative side stripes.
- **Internal Padding:** Compact panels use 12px to 16px. Larger modals use 16px mobile and 24px desktop.

### Inputs / Fields

Inputs should feel native and predictable.

- **Style:** White background, 6px radius, gray ring, 0.875rem text, and compact vertical padding. Search fields often reserve 40px left padding for an icon.
- **Focus:** 2px indigo or blue focus ring, with no layout shift.
- **Error / Disabled:** Error text uses rose/red, disabled controls reduce opacity and keep layout stable.
- **VueForm:** Use VueForm for create/update modals and keep labels non-floating on settings-style forms.

### Navigation

Navigation is a clean top operational bar with dropdown menus and a domain selector.

- **Style:** White surface, subtle shadow, compact 64px height, gray menu text, and small chevrons.
- **Default / Hover:** Default nav text is muted gray; hover darkens to Default Ink and may use Soft Workbench in dropdown menus.
- **Dropdowns:** White floating panels with a subtle ring and lifted shadow. Items use compact 8px vertical padding and single-line labels.
- **Mobile:** Use the established Headless UI disclosure pattern with nested menu disclosure rows.

### Data Tables

Tables are the main working surface.

- **Structure:** DataTable shell with title, filters, actions, paginator, table header, body, empty state, loading state, and optional footer paginator.
- **Header:** Soft Workbench background, semibold Strong Ink, compact 14px vertical rhythm, sortable headers with chevrons.
- **Rows:** White background, thin dividers, 0.875rem Muted Ink body text, hover states only when the row or cell is interactive.
- **Actions:** Row actions live at the right edge. Use icon buttons with tooltips for edit, delete, restart, copy, download, and advanced actions.

### Modals

Modals use Headless UI transitions and should stay focused on the task.

- **Shell:** Operational White, 8px radius, Modal Lift, 16px to 24px padding, and width matched to form complexity.
- **Overlay:** Gray overlay at 75 percent opacity.
- **Motion:** 200ms to 300ms ease-out opacity and slight translate/scale transitions.
- **Close:** Top-right icon button with visible focus ring and screen-reader label.

### Toggles

Toggles are state controls, not decorative switches.

- **On:** Control Indigo track, white knob, label and optional description to the left when present.
- **Off:** Soft gray track, white knob.
- **Focus:** 2px indigo ring with offset.
- **Disabled:** Reduced opacity and not-allowed cursor.

## 6. Do's and Don'ts

### Do:
- **Do** follow Devices, Ring Groups, Extensions, Default Settings, Conference-style pages, VueForm conventions, and existing Laravel/Inertia data-route structure before inventing new patterns.
- **Do** use compact spacing, predictable headers, and right-aligned row actions for dense admin screens.
- **Do** reserve Control Indigo for primary actions, active selections, checked controls, and focus states.
- **Do** pair every important status color with readable text, an icon, a count, or a tooltip.
- **Do** keep create and update workflows in VueForm modals when that is the local page pattern.
- **Do** keep user-facing copy short, literal, and operational.
- **Do** preserve keyboard focus states and readable contrast for mixed-skill users.

### Don't:
- **Don't** recreate legacy FusionPBX clutter.
- **Don't** create dense visual noise, scattered actions, ambiguous form layouts, or pages that require prior tribal knowledge to operate.
- **Don't** use marketing-style admin UI, decorative excess, oversized hero sections, or generic SaaS gloss.
- **Don't** use colored side-stripe borders as accents on cards, rows, callouts, or alerts.
- **Don't** use gradient text, glassmorphism, decorative motion, or page-load choreography.
- **Don't** rely on color alone for enabled, disabled, warning, error, registration, provisioning, or queue state.
- **Don't** introduce new broad visual systems when an existing page pattern already solves the workflow.
