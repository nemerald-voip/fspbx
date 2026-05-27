---
target: resources/js/Pages/Dashboard.vue
total_score: 25
p0_count: 0
p1_count: 2
timestamp: 2026-05-26T23-40-48Z
slug: resources-js-pages-dashboard-vue
---
# Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Account counts and tile counts have partial skeletons, but global info can silently appear late or not at all. |
| 2 | Match System / Real World | 3 | Most labels are plain, but `DND`, `FWD no Ans`, and `FWD no Reg` require PBX shorthand knowledge. |
| 3 | User Control and Freedom | 3 | Settings and Manage exits are available, but the dashboard has no way to personalize or reorder high-frequency shortcuts. |
| 4 | Consistency and Standards | 2 | Cyan, blue, green, indigo, purple, lime, fuchsia, orange, and rose compete with the design system's restrained indigo/status model. |
| 5 | Error Prevention | 3 | Low-risk surface with few destructive actions, and the extension modal blocks until data loads. |
| 6 | Recognition Rather Than Recall | 3 | Quick Access is visible and labeled, but tiles do not explain whether counts are totals, unread, active, or action-needed. |
| 7 | Flexibility and Efficiency of Use | 2 | Shortcuts help, but there is no favorites/recent/work queue concept for power users. |
| 8 | Aesthetic and Minimalist Design | 2 | The repeated metric-card grid and right-column cards make everything feel similarly important. |
| 9 | Error Recovery | 2 | Errors go to Notification, but failed async loads leave large dashboard regions empty or stale without inline recovery. |
| 10 | Help and Documentation | 1 | No contextual support for dashboard-specific terminology or next steps. |
| **Total** | | **25/40** | **Acceptable: solid foundation, but the hierarchy and color system need work before this feels first-class.** |

# Anti-Patterns Verdict

Does this look AI-generated? Not blatantly, but it does read as a generic admin dashboard more than a calm PBX control room. The strongest tell is the identical tile grid: same shape, same icon box, same large count, repeated across many modules. That pattern is familiar, but here it flattens priority and starts to feel templated.

LLM assessment: the page has a useful operational core, but the visual language is too democratic. Account status, self-service extension status, shortcuts, and superadmin infrastructure all use similar panels and similarly loud chips. The page needs one clear job per user role: tenant user, admin, and superadmin should not all feel like they landed on the same mixed dashboard.

Deterministic scan: attempted, but unavailable. `detect.mjs --json resources/js/Pages/Dashboard.vue` failed with `Error: bundled detector not found.`

Visual overlays: skipped because browser automation is not exposed in this session, so no reliable user-visible overlay could be injected.

# Overall Impression

This is useful and serviceable. The best part is that it gives users a real self-service entry point for their own extension instead of making them hunt through Extensions. The biggest opportunity is to stop treating the dashboard as a wall of shortcuts and make it a role-aware operational summary with shortcuts as secondary support.

# What's Working

1. The `My Extension` panel is directionally right. It summarizes active call handling and gives the user a direct Manage action, which matches the prior dashboard self-service direction.
2. Loading is better than a blank page in the account summary and tiles. The skeleton treatment avoids a pure spinner-in-empty-space feel.
3. The page stays operationally restrained compared with a marketing dashboard: compact buttons, real counts, direct labels, no oversized hero.

# Priority Issues

## [P1] The visual hierarchy does not reveal the dashboard's primary job

Why it matters: `Quick Access` dominates two columns with many equal-weight tiles, while `My Extension` and account health are pushed into the right rail. For many users, the most valuable task is understanding their own extension status or seeing whether the account is healthy, not scanning every module shortcut.

Fix: Put a compact "Today / My Extension / Account Health" band above shortcuts, then make shortcuts a denser directory below. For non-superadmins, make `My Extension` a first-row panel when present. For superadmins, keep `Global Info` in a clearly separate admin/system section.

Suggested command: `impeccable layout resources/js/Pages/Dashboard.vue`

## [P1] The tile grid is an identical-card pattern with too much color noise

Why it matters: The dashboard uses many icon color families in `DashboardTile.vue`, including teal, purple, sky, fuchsia, rose, indigo, red, lime, green, cyan, blue, and orange. Users cannot tell which colors are semantic versus decorative, which weakens real status colors like suspended, offline, warning, and error.

Fix: Collapse module tiles to a restrained neutral style with one accent hover/focus color. Reserve semantic fills for actual status. If modules need recognition, use consistent monochrome icons plus grouped headings instead of per-module rainbow icon boxes.

Suggested command: `impeccable colorize resources/js/Pages/Dashboard.vue`

## [P2] Telephony shorthand leaks into first-glance status

Why it matters: `DND`, `FWD no Ans`, and `FWD no Reg` are efficient for PBX admins but brittle for less technical tenant users. This is exactly where the dashboard should reduce translation effort.

Fix: Keep compact badges, but use readable labels: `Do not disturb`, `Forward all`, `Forward if busy`, `Forward if unanswered`, `Forward if unregistered`. If space is tight, keep the short label visually and add a title/tooltip, but do not rely on abbreviations alone.

Suggested command: `impeccable clarify resources/js/Pages/Dashboard.vue`

## [P2] Async error and empty states are not local enough

Why it matters: If `counts`, `data`, or `my extension status` fail, the user gets a global notification but the affected panel may be missing, stale, or ambiguous. On a dashboard, absence often looks like "nothing is wrong" rather than "data failed."

Fix: Track loading/error state separately for counts, data, and extension status. Show inline retry affordances in the affected panel. Keep the global notification for unexpected failures, but let each dashboard region explain its own state.

Suggested command: `impeccable harden resources/js/Pages/Dashboard.vue`

## [P3] Copy and casing feel slightly uneven

Why it matters: Small inconsistencies add drag in an admin UI. `Account dashboard`, `Account name`, `My Extension`, `Quick Access`, `Global Info`, `Total extensions`, and `Horizon Status` mix title case, sentence case, and repeated account naming.

Fix: Standardize panel labels and casing. Consider `Dashboard`, company name as H1, `Account summary`, `My extension`, `Shortcuts`, and `System status` for superadmins.

Suggested command: `impeccable clarify resources/js/Pages/Dashboard.vue`

# Persona Red Flags

Alex, power user: Alex gets useful shortcuts, but cannot pin, reorder, hide, or prioritize them. The grid is one-size-fits-all, so a frequent task like Call Routing or Extensions receives the same weight as lower-frequency destinations. Alex will eventually route around the dashboard through nav memory.

Sam, keyboard and screen reader user: The full-card anchor in `DashboardTile.vue` is good for large click targets, but the pseudo-element overlay plus separate `alt_href` link creates a more complex focus/click model. Status bars for extension registration, disk, memory, and global registrations communicate proportion visually; the text helps, but the bars themselves have no accessible label.

Jordan, less technical tenant user: Jordan can understand `Manage`, `Extensions`, and `Phone Numbers`, but `DND`, `FWD no Ans`, `FWD no Reg`, `Horizon Status`, and `Global Info` are not self-explanatory. If the dashboard is their first operational landing page, it assumes more PBX literacy than it needs to.

# Cognitive Load

Failed checklist items: single focus, chunking, visual hierarchy, progressive disclosure.

Load level: high-moderate. The page is not dense in a table sense, but it asks users to scan multiple unrelated object types at once: shortcuts, account metadata, extension routing state, and superadmin system health. The cost is not raw volume; it is unclear priority.

# Minor Observations

- `Account name` repeats the H1 company name, using valuable right-rail space without adding new insight.
- `Settings` and `Manage` use the same cog icon, but one means account settings and one means extension management. The labels save it, but different icons could improve scan speed.
- The suspension banner is visually loud, which is appropriate, but the copy is long for a top banner. It could be shorter and action-oriented if there is a payment route.
- `Global Info (Superadmin only)` exposes implementation audience in the heading. `System status` would feel cleaner; access level does not need to be visible inside the card.

# Questions to Consider

1. Should this dashboard optimize for daily tenant self-service, admin navigation, or superadmin system monitoring first?
2. What belongs above the fold for a normal tenant: extension status, account status, recent activity, or shortcuts?
3. Are tile counts meant to indicate inventory totals or items needing attention? If they are totals, they should be visually quieter.
