---
target: resources/js/Pages/Extensions.vue
total_score: 22
p0_count: 0
p1_count: 3
timestamp: 2026-05-27T06-10-54Z
slug: resources-js-pages-extensions-vue
---
# Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | The page has loading feedback, but registration status can display as unregistered while registrations are still loading. |
| 2 | Match System / Real World | 2 | Labels like `DND`, `FWD no Ans`, `FWD no Reg`, `Make Agent`, and `Make Admin` assume domain context. |
| 3 | User Control and Freedom | 2 | Bulk selection exists, but several row actions are immediate or hidden behind unclear controls. |
| 4 | Consistency and Standards | 2 | Table actions mix buttons, clickable SVGs, clickable spans, clickable divs, tooltips, and row-name editing. |
| 5 | Error Prevention | 2 | Destructive delete is confirmed, but advanced create/duplicate actions fire immediately from the menu. |
| 6 | Recognition Rather Than Recall | 2 | Services, registration dots, and forwarding badges require tooltip reading or PBX shorthand knowledge. |
| 7 | Flexibility and Efficiency of Use | 3 | Search, sort, pagination, import/export, bulk edit/delete, and advanced row actions are efficient for admins. |
| 8 | Aesthetic and Minimalist Design | 2 | The first column carries too many meanings at once: selection, registration, identity, mobile email, and routing states. |
| 9 | Error Recovery | 2 | Errors surface globally, but failed CSV/template/download/registration paths do not give strong local recovery. |
| 10 | Help and Documentation | 1 | No contextual help for service icons, advanced actions, registration expansion, or voicemail retention. |
| **Total** | | **22/40** | **Acceptable but fragile: operationally powerful, visually and interaction-wise too uneven for a core admin table.** |

# Anti-Patterns Verdict

Does this look AI-generated? No. It looks like an organically evolved admin table. The problem is not generic AI gloss; the problem is legacy-density and inconsistent interaction vocabulary.

LLM assessment: the page is useful, dense, and feature-complete, but it asks the user to infer too much from small icons, colors, shorthand badges, and hover-only tooltips. This is a high-value PBX admin table, so density is allowed. The current density is not structured enough: the extension identity column is carrying selection, registration, edit affordance, mobile email fallback, suspended state, DND, forwarding, and call sequence all in one cluster.

Deterministic scan: attempted, but unavailable. `detect.mjs --json resources/js/Pages/Extensions.vue` failed with `Error: bundled detector not found.`

Visual overlays: skipped because browser automation is not exposed in this session, so no reliable user-visible overlay could be injected.

# Overall Impression

This page has the right capabilities for a serious Extensions surface: search, sorting, pagination, import/export, bulk operations, inline statuses, expanded registrations, and modals for create/update. It needs a design hardening pass more than a visual redesign. The highest-risk issues are misleading registration loading and non-standard row actions.

# What's Working

1. The page respects the app's operational pattern: DataTable shell, compact actions, paginated API data, VueForm modals, import/export, and bulk actions.
2. The extension row tries to surface important routing state without forcing users into edit mode. Suspended, DND, forwarding, and sequence badges are directionally right.
3. The expanded registration row is valuable. Showing device, remote IP, transport, and expiration directly under the extension is exactly the kind of admin affordance this page should have.

# Priority Issues

## [P1] Registration loading can falsely imply extensions are offline

Why it matters: While `isRegsLoading` is true, the registration indicator falls into the gray `Not registered` branch. That means a user can briefly see every extension as unregistered during load, which is a dangerous false signal on a phone-system admin page.

Fix: Add a third state for registration loading. Use a neutral skeleton/dot with `Loading registrations` text or title, and disable expansion until the data is known. Only show `Not registered` after registrations have loaded.

Suggested command: `impeccable harden resources/js/Pages/Extensions.vue`

## [P1] Row actions are not standard accessible controls

Why it matters: Edit and delete are clickable SVGs, registration expand is a clickable span, and the extension name is a clickable div. These are hard for keyboard and screen-reader users, have weaker focus semantics, and diverge from the app's icon-button expectation.

Fix: Convert row actions to real `<button type="button">` controls with visible focus rings, `aria-label`s, disabled/loading states where appropriate, and tooltips attached to stable per-row targets or button refs. Make the registration expander a button with `aria-expanded`.

Suggested command: `impeccable audit resources/js/Pages/Extensions.vue`

## [P1] The first column is doing too much

Why it matters: The extension cell combines checkbox, registration count, edit affordance, name, mobile-only email, suspended state, DND, forwarding badges, and sequence. Users have to parse a cluster instead of scanning a row.

Fix: Split the row into clearer subzones. Keep checkbox and identity together, put registration as a labeled `Registration` status column or a clearly separated status chip, and group call-handling badges under a `Routing` or `Call handling` column. On mobile, stack identity first, then compact status groups below.

Suggested command: `impeccable layout resources/js/Pages/Extensions.vue`

## [P2] Sorting and column structure are visually and semantically brittle

Why it matters: Sortable headers are clickable divs, not buttons, and they lack `aria-sort`. There are also typo-level class issues like `py-3.5...text-left` and `spx-2` that can break spacing and polish.

Fix: Make sortable headers proper buttons inside column headers, expose active sort state, and clean up class typos. Prefer one reusable sortable header pattern instead of hand-wiring each column.

Suggested command: `impeccable polish resources/js/Pages/Extensions.vue`

## [P2] Advanced actions need clearer risk framing

Why it matters: `Duplicate`, `Make User`, `Make Admin`, `Make Agent`, and `Make Admin` under Contact Center are powerful actions. Some fire immediate POST requests from an ellipsis menu with no preview, confirmation, or local pending state.

Fix: Rename role actions to include the target system, such as `Create portal user`, `Create portal admin`, `Create contact center agent`, `Create contact center admin`. For actions that create records or permissions, use confirmation or at least a pending state and success message that names the created object.

Suggested command: `impeccable clarify resources/js/Pages/Extensions.vue`

# Persona Red Flags

Alex, power user: Alex will appreciate bulk actions, import/export, and inline status. The pain is trust: a transient false unregistered state and inconsistent row controls make the table feel unreliable when moving quickly.

Sam, keyboard and screen-reader user: Sam cannot operate several important controls cleanly because edit/delete are SVG click targets, registration expansion is a span, and sortable headers are clickable divs. Tooltips help sighted mouse users but do not create a robust accessibility path.

Riley, stress tester: Riley will notice that registration loading, registration empty state, failed registration fetch, and truly unregistered can collapse into similar visual output. Riley will also hit duplicate IDs like `destination_tooltip_target` and `delete_tooltip_target` repeated for every row.

Project-specific PBX admin: The admin needs to scan whether extensions are reachable and safely make high-impact changes. The page exposes the right data, but status and action confidence are not yet strong enough for live-system operations.

# Cognitive Load

Failed checklist items: chunking, visual hierarchy, minimal choices, progressive disclosure.

Load level: high. The page has many legitimate capabilities, but they are concentrated into the row rather than grouped into predictable zones. The first decision point in each row has more than four simultaneous signals: checkbox, registration dot/count, name/edit affordance, email, suspended, DND, multiple forwarding states, sequence, services, edit, delete, and advanced menu.

# Minor Observations

- `MainLayout` is self-closing while content sits beside it, unlike pages where layout wraps the page. This may be a local pattern, but visually it is worth verifying in the running app.
- The `Services` column uses icon-only indicators. Tooltips are helpful, but a compact text fallback or accessible label strategy would make the state more durable.
- The delete confirmation includes `Retain voicemail (convert to team inbox)`, which is good operationally, but this option is high-stakes enough to deserve one line explaining who will see that inbox.
- The empty state only says `Adjust your search and try again.` That works for filtered empty results, but not for a domain with no extensions yet.
- Import failure clears selected items, which may surprise users if selection was unrelated to the CSV upload.

# Questions to Consider

1. Should registration status be a primary column instead of a dot inside the extension identity cell?
2. Are forwarding badges mostly for expert scanning, or do tenant admins need plain-language call-handling labels here too?
3. Which advanced actions should be instant, and which should require confirmation because they create users, admins, or contact-center roles?
