# Claude Code prompt: Dr. AK Lohana medical dashboards

Copy this entire document into Claude Code while the existing website project is open.

---

You are a senior product designer and frontend engineer working on the existing medical website https://doctoraklohana.com/.

Implement a polished, professional redesign of the **patient, doctor, admin, and receptionist dashboards**, including their existing related lists, forms, modals, details, and empty states. The desired feel is calm, trustworthy, clean, and appropriate for a working medical practice. Deliver working changes in the existing project, not just a mockup or a design proposal.

## 1. Start with the real implementation

Inspect the repository before editing. Read its project instructions. Identify the dashboard templates, shared styles, JavaScript, routing, permissions, form handlers, and dependencies. The live site shows WordPress integration and custom `dak-` dashboard elements, but the exact repository structure and asset ownership have not been inspected. Verify them; do not assume a framework or invent filenames.

Produce a brief map of the files and components involved, then implement the redesign. Reuse the existing stack and libraries. Do not introduce React, a new CSS framework, or a replacement backend simply to restyle these pages. If code is managed through a custom plugin, theme, shortcodes, or snippets, identify the actual source of truth first.

Preserve existing URLs and query parameters, permissions, clinic scoping, data, field names, IDs used by scripts, server endpoints, nonces, validation, payment calculations, notifications, and scheduling rules. If markup changes require JavaScript selector updates, update both together. Keep public marketing-page styling isolated from dashboard changes. Do not deploy or change production data as part of this task.

Keep me informed with short progress updates explaining what you found, what you are changing, and what you verified. Make ordinary design decisions yourself and continue working until the implementation is complete. Ask only when a missing dependency or consequential product decision genuinely blocks the work.

## 2. Review evidence and limits

The following observations come from a live desktop browser review on October 2, 2026. The site displayed October 3 in its own timezone during the review; this is not by itself a date bug.

| Experience | Live route and coverage | Important limits |
| --- | --- | --- |
| Admin | `/admin-dashboard/`; overview, appointments and Add Appointment modal, billing, encounters list, All Users, receptionist directory, clinics, services, video pricing, doctor sessions; settings section headings inspected | Not every admin detail page, form, or action was exercised. Other sidebar sections were identified for follow-up in code. |
| Doctor | `/doctor-dashboard/`; overview, appointments, clinics, services, video consultation pricing, patients, earnings, encounters, notifications; settings organization inspected | This account had no appointments, patients, clinics, services, or encounters. Populated doctor states remain to be verified using local/staging fixtures. |
| Patient | `/patient-dashboard/`; overview, appointments, medical history, payments, notifications, profile field organization, settings; booking entry at `/book-appointment/` | This account had no bookings, payments, or completed visits. The booking entry screen was viewed; no booking was submitted. |
| Receptionist account supplied for review | A restricted `/admin-dashboard/` with fewer navigation items; overview, appointments, patients, doctors, encounters, service requests, doctor sessions, notifications, settings | The shell still says “Admin portal.” Verify the actual role/capability mapping in code before applying role labels or layouts. No permission changes were tested. |

Admin navigation also exposes Doctor Requests, Blogs, Roles & Permissions, and Locations. Inspect their actual source and UI before extending the shared components to them. Do not claim these were individually audited in the live review.

No live records were created, edited, paid, deleted, or sent reminders during the review. Mobile layouts, keyboard operation, dark mode, and populated doctor/patient details still need implementation-time verification. Treat the observations below as design findings, not a completed functional or security audit. Use synthetic data in local/staging tests; do not copy real patient information into fixtures, documentation, screenshots for sharing, or source code.

## 3. Problems observed on the current site

1. **The portals do not feel like one product.** Admin has a black active navigation item and a deep-green KPI card; doctor and patient use pale-lime navigation, large bright-green welcome banners, different sidebar structures, and different spacing.
2. **The patient greeting dominates the page.** Its oversized heading, waving emoji, membership-date card, and profile-completion panel compete with appointment tasks. A missing photo is presented as profile incompleteness even though its importance to care is unclear.
3. **The doctor profile block uses too much sidebar space.** The Clinics empty state has no clear visible page title. Several empty pages offer minimal guidance.
4. **Admin/receptionist appointment and encounter rows are very tall.** Repeated clinic names and full addresses wrap across many lines. Only a small number of records fit on screen.
5. **Action groups are difficult to scan.** Many small icon-only controls appear together. Edit, payment, viewing, printing, and deletion compete in the same row; payment collection and manually recording payment need clearer distinctions.
6. **Filters have inconsistent sizes and alignment.** They wrap unevenly, use differing input treatments, and sometimes sit inside multiple nested cards. Searches and filter application patterns vary between modules.
7. **The Add Appointment modal is taller than the viewport.** The save action is below the visible screen. Related fields need clearer grouping and a contained scrolling area.
8. **Overview charts consume too much working space.** The admin zero-revenue chart remains large with awkward repeated tick labels. The restricted receptionist overview gives a large chart priority over today's working list.
9. **Pricing presentation needs clarity.** Some service rows show “Free” alongside nonzero clinic-specific prices. The doctor pricing preview displays “0% off.” Verify the pricing data model and improve labels without changing calculations.
10. **Application chrome is distracting.** Floating WhatsApp and chatbot widgets sit near sidebar and row actions. The WordPress toolbar affects the admin layout. The account/header area is not consistently clear.
11. **The receptionist shell is misleadingly labeled.** It still says “Admin portal,” and its overview resembles an administrator report. A “Review requests” shortcut remains visible even though Doctor Requests is absent from this account's sidebar; verify the capability condition rather than assuming an access bug.
12. **Notifications can become visually overwhelming.** The reviewed receptionist account had hundreds of unread items; broad tinted rows and counters dominate. Existing unread behavior and mark-as-read rules must be preserved.

## 4. Visual direction and shared tokens

Create a restrained medical application, retaining the recognizable logo and green brand connection. Use white surfaces, a soft cool-neutral canvas, dark slate text, and deep green for primary actions. Keep lime as a small supporting brand accent, not a large background or the default text-button combination.

Use this starting palette, adjusted only when necessary for verified contrast:

| Token | Suggested value | Use |
| --- | --- | --- |
| Canvas | `#F6F8FA` | Main application background |
| Surface | `#FFFFFF` | Cards, tables, forms |
| Primary | `#16634B` | Primary buttons and selected controls |
| Primary hover | `#104D3A` | Hover/pressed primary actions |
| Primary subtle | `#EAF4EF` | Active navigation and light emphasis |
| Main text | `#182B3A` | Headings and primary content |
| Secondary text | `#526273` | Supporting readable text |
| Border | `#DFE5EB` | Dividers and control boundaries |
| Information | `#245FA8` | Informational states |
| Warning | `#8A4B08` on `#FFF4D8` | Pending/action-needed states |
| Danger | `#B42318` on `#FEECEB` | Errors and destructive actions |

Check actual text/background combinations. Do not rely on color alone to indicate state.

- Use one existing readable sans-serif font family; a system stack is acceptable. Avoid adding an external font dependency unnecessarily.
- Body text 14–16px; patient-facing essential instructions 16px; secondary labels normally at least 12–13px. Page headings 24–28px, section headings 17–20px, KPI values about 26–30px.
- Use a 4/8px spacing scale: 8, 12, 16, 24, 32. Page padding 24–32px desktop, 16px mobile.
- Cards: 10–12px radius, subtle 1px borders, very light shadows only where useful. Avoid nested cards with repeated shadows.
- Inputs and regular buttons: consistent 40–44px height; mobile primary touch controls at least 44px. Keep visible labels above form fields.
- One coherent icon set already supported by the project. Small consistent icons; no decorative emoji greetings, oversized icons, excessive pill shapes, gradients, or hover lift on every card.
- Centralize CSS variables and shared component rules. Scope them to the dashboard root to prevent conflicts with the public theme, Elementor, WordPress admin, Select2, or other existing widgets.
- Preserve the existing dark-mode capability and preference behavior. Adapt all shared components and semantic colors; do not leave light-only fields or unreadable charts.

## 5. Shared application shell

Build one consistent shell with role-aware navigation and content:

- Desktop sidebar around 232–248px; collapsed mode around 72px if the existing collapse behavior is retained.
- Small logo and accurate portal name at the top. Compact account identity instead of a large profile card. Keep sign-out reachable without widget overlap.
- Group navigation by purpose, with a consistent active background and icon treatment. Preserve every authorized route. Do not expose restricted routes because another role has them.
- Topbar around 64px with appropriate search, notifications, theme control, and a compact account menu where supported.
- Search must describe and search its actual scope. Do not label a patient search “Search everything.” Do not add a search field that has no working behavior.
- Page header: title, short useful description, and one clear primary action. No oversized hero banner inside a working dashboard.
- Align content, card edges, headings, and toolbar baselines across pages.
- Account for the WordPress toolbar when present using supported layout offsets. Do not remove administrative access merely to conceal the toolbar.
- Replace competing floating dashboard overlays with one unobtrusive Help entry if integration permits. Preserve support access and keep marketing chat widgets unchanged on public pages. Support controls must never cover navigation, save buttons, table actions, or mobile bottom controls.

## 6. Patient dashboard: clear next steps

Make the homepage understandable at a glance:

1. Compact greeting and one “Book appointment” primary action.
2. A prominent **Next appointment** card, when present: doctor, specialty, date, time with the applicable timezone, clinic or video visit, relevant status, and the existing allowed next action.
3. Upcoming appointments with a small “View all” link; simple recent-record/activity section where supported.
4. Payment due information only when relevant. Keep a zero balance reassuring and compact.
5. Move membership date and nonessential profile-completion information to Profile. Keep required missing information visible without treating an optional photo like an urgent care requirement. Verify current requirements rather than removing validation.

Patient appointments:

- Use approachable Upcoming/Past views if they map to existing filters. Preserve date and status filtering.
- Use readable appointment cards on small screens, with clinic/video distinction and explicit action labels.
- Show rescheduling, cancellation, joining a video visit, payment, and prescription/receipt actions only when supported and allowed by the current state.
- Surface existing deadlines and cancellation/payment rules near the relevant action. Do not invent join windows, refund terms, or appointment states.
- No-data state: “No appointments yet” plus “Book an appointment.” No-results state: “No appointments match these filters” plus “Clear filters.” Distinguish both from loading and failed requests.

Medical history and payments:

- Present existing visit records in chronological order with doctor/date and clearly named existing documents or detail actions. Expand supporting detail on demand.
- Use clear “Amount paid,” “Amount due,” payment status, and date labels based on real data. Format all currency consistently, for example `PKR 2,500`.
- Preserve existing receipt and prescription permissions and download handlers. Do not invent lab-report features that do not exist.
- Group profile details logically; separate password/security settings visually from basic profile editing while preserving the existing authentication flow.

Booking entry:

- Preserve `/book-appointment/` and its current selection/details/date-time-payment/confirmation flow.
- Improve the dashboard-to-booking transition with a clear return path and consistent controls.
- Reduce excessive header space, keep the step indicator compact, and make long doctor names readable without relying solely on clipped text.
- Preserve doctor/service/clinic dependencies, availability, selected values, validation, and payment behavior. Do not make unrelated public-site changes.

## 7. Doctor dashboard: today's clinical work

- Replace the large welcome banner with a concise heading and the current day/context.
- Put today's schedule or upcoming appointments first, using existing available data. Place recent patients and relevant pending work next.
- Keep a compact metric row for supported useful counts. Do not manufacture clinical metrics or treat unavailable data as zero.
- Reduce the sidebar profile block to a small identity area. Use the same navigation styling as other roles.
- Give every page, including Clinics, a consistent title, description, and primary action where one exists.
- For a newly configured doctor account, provide a small setup checklist based on actual missing prerequisites, such as clinic availability and services. Each item must lead to an existing authorized screen.
- In Clinics/sessions, clearly distinguish location, visit type, day, hours, and slot duration. Use an orderly weekly-hours layout inside existing setup forms without changing scheduling logic.
- In Services, distinguish base/default prices from clinic-specific prices. If the backend represents an unset base price as zero, do not imply that every clinic visit is free; label the actual pricing scope correctly.
- In Video Consultation, retain the useful form/preview arrangement but make it compact. Group base price, discount/end date, instant-booking surcharge/window, and refund window. Hide promotional discount badges when the actual discount is zero. Reflect the current effective price and rules accurately.
- In Earnings, retain the exact doctor/clinic/video separation and revenue-share calculations. Make “Owed to you,” “You owe,” and “Settled” explicit; do not merge distinct clinic ledgers or change settlement logic.
- Preserve all existing encounter, prescription, and billing forms. In populated states, use a compact identity summary, clear field sections, and visible save/cancel actions. Inspect their actual implementation before restyling them.

## 8. Admin dashboard: operational overview

- Keep useful KPI cards compact and consistently styled. Show the reporting period and metric meaning where needed.
- Place actionable information close to the top: today's appointments, outstanding items, pending approvals when authorized, and relevant activity.
- Charts should support decisions. For no revenue in the selected period, show a small explanatory zero state rather than a large empty chart with meaningless ticks. Preserve legitimate zero values; distinguish them from loading or failure.
- Improve chart label density, responsive sizing, and legends. Keep colors consistent across corresponding statuses. Use existing chart libraries.
- Rename “Recent activities” to a more specific label if it only lists appointments, or preserve the name if the source really is an activity feed. Confirm in code.
- Apply the same table, filter, form, and dialog components to appointments, encounters, directories, requests, clinics, services, sessions, and the remaining admin screens after inspecting them.
- Keep Billing's separate doctor/clinic/video breakdown. Use a balanced summary grid and compact ledger with clear payer/payee direction. Preserve all amounts, formulas, fees, settlement boundaries, and exports.
- Permission-dependent shortcuts, metrics, approvals, and actions must use the same effective capability checks as their destination pages.

## 9. Receptionist dashboard: front-desk workflow

The account used in the review opened the restricted admin route. Verify role handling in code, then render an accurate “Receptionist portal” label and role-appropriate overview within the existing routing structure. Do not add a new URL merely for styling.

- Prioritize today's appointment list over a large historical chart.
- Put date, authorized clinic context, doctor filter, patient lookup, and “Add appointment” close to the list.
- Use existing appointment status data to offer useful views such as scheduled/checked-in/completed where those states and filters are already supported.
- Show a check-in action only if an existing authorized workflow supports it. Do not invent queue positions, waiting times, triage categories, or new patient-status transitions.
- Keep appointment status and payment status visually separate.
- Make the distinction between initiating a payment and recording an already-collected payment explicit. Connect clearer labels to the existing handlers; do not convert one operation into the other.
- Show the most frequent authorized action visibly; place secondary options in a labeled “More” menu. Keep existing edit, view, print, and reminder capabilities discoverable.
- Keep patient registration/lookup concise and make the selected patient unmistakable in booking forms.
- In Service Requests, use concise status labels, readable contact/request information, and the existing follow-up controls. Do not send messages or automatically change request statuses during testing.
- Keep clinic and doctor scope enforced on the server. Changing navigation visibility must not expand access or change authorization policy.

## 10. Reusable lists, filters, forms, and states

### Lists and tables

- Use structured desktop tables or aligned compact rows for dense staff workflows. Aim for roughly 64–80px ordinary rows when content allows; never clip essential clinical information to force a fixed height.
- Suggested appointment columns: patient, doctor, date/time, clinic or visit type, appointment status, payment status, amount, actions. Adapt to the actual available data and viewport.
- Show a short clinic name in the main row. Put the full address in an accessible detail view or expandable section. Avoid displaying the same clinic name twice.
- Align currency values, use tabular numerals, and standardize dates. Preserve the application's configured timezone; prefer a readable unambiguous display such as `03 Oct 2026, 5:40 PM` without changing stored values or server parsing.
- Use one visible primary row action, a secondary view/edit action when useful, and a menu for the rest. Icon-only controls need accessible names and tooltips; high-impact actions should have understandable text.
- Keep deletion and other consequential actions visually separated and preserve confirmations. Do not make them the strongest visual action.
- Display bulk actions only when items are selected, or keep them clearly disabled with an understandable zero-selection state. Preserve selection across only the scope supported by the application; do not silently select hidden pages.
- Keep pagination, result totals, and sort/filter state clear. If absent, inspect the data layer before adding pagination; do not sort or search just the visible page while implying the entire dataset was searched.

### Filters

- One consistent toolbar/grid, aligned field labels and controls, responsive wrapping at intentional breakpoints.
- Search, frequent filters, Apply, and Clear/Reset in a predictable order.
- Preserve existing filter behavior or explicitly update the complete interaction consistently. Do not accidentally combine automatic requests with an Apply button that submits the same query twice.
- Keep long clinic/doctor names usable in Select2 or the existing select component. Ensure dropdowns remain above dialogs and within the viewport.

### Forms and dialogs

- Group related fields under short headings. For Add Appointment, use patient details, doctor/service/visit type, date/time, and status/payment/notes sections that respect actual dependencies.
- Desktop modal width approximately 720–880px when appropriate, with `max-height` tied to the viewport, a scrolling body, and a visible footer containing Cancel and Save.
- On phones use a responsive full-screen or nearly full-screen form with reachable controls. Do not require horizontal scrolling to save.
- Keep labels, required indicators, optional labels, validation, helper text, and disabled states consistent.
- Preserve field values on validation errors. Show loading, saving, success, and failure states. Prevent duplicate submissions without leaving the form permanently disabled after errors.
- Trap keyboard focus in dialogs, support Escape where appropriate, and restore focus to the opening control. Ensure unsaved edits are handled consistently with existing behavior.

### Notifications and feedback

- Compact date-grouped notification rows with a restrained unread indicator, concise event text, and a clear destination.
- Use a consistent badge-capping convention and accessible full unread count. Preserve existing read/unread rules and mark-as-read actions.
- All async panels need separate loading, empty, filtered-empty, and error states. Do not display “No records” while a request is still loading.
- Use restrained confirmations/toasts and inline errors. No fake success messages.

## 11. Responsive and accessibility requirements

Verify at approximately 390px, 768px, 1366px, and 1440px widths, including short laptop heights.

- Mobile sidebar becomes an accessible drawer; active page, account, help, and sign-out remain discoverable.
- Metrics stack sensibly; filters collapse or wrap without making core actions hard to reach.
- Patient pages use readable cards. Dense staff tables may have a contained horizontal scroll region when needed, but the whole page must not overflow sideways.
- Labels, focus rings, keyboard access, semantic tables, and modal focus behavior work throughout.
- Meet WCAG 2.2 AA as a verification target for the changed UI, including contrast and keyboard behavior. Do not claim compliance without testing the applicable requirements.
- Long names, multiple clinics, large PKR amounts, special characters, missing avatars, zero records, and unavailable values must not break layouts.
- Respect reduced-motion preferences. Essential information must not depend on animation, hover, or color alone.

## 12. Implementation and verification sequence

1. Inspect and map the current source, role/capability checks, shared elements, and route coverage. Capture baseline screenshots locally using synthetic or appropriate test data.
2. Define shared tokens and the shell, then reusable headers, buttons, inputs, badges, filter bars, lists, empty states, and modal structure.
3. Apply to patient and doctor experiences, keeping their primary tasks clear.
4. Apply to admin and receptionist experiences, preserving capability-dependent content and clinic scoping.
5. Inspect remaining existing subpages and detail forms before adapting them. Do not stop after four home screens.
6. Verify representative existing workflows in a local/staging environment: filtering, pagination where present, booking/editing, appointment details, rescheduling rules, encounter forms, existing document downloads, and pricing/earnings display. Use safe test fixtures for mutations and a payment sandbox for payment-related checks.
7. Verify empty and populated states for every role, including role-denied routes and absent permissions. Use long text and special characters in fixtures.
8. Check console errors, missing assets, duplicate event handlers, focus/keyboard behavior, desktop/mobile overflow, and dark-mode contrast. Test the admin toolbar both present and absent.
9. Compare before/after screenshots at the target sizes. Fix visible inconsistencies before finishing.

Do not rewrite business logic to make the interface easier to demonstrate. Do not add fictitious appointments, charts, balances, doctor availability, or clinical facts to production. Treat new backend features as separate proposals if the current application does not support them.

## 13. What to deliver

- The implemented changes in the existing codebase, with a concise file/change summary.
- A brief explanation of the shared design system and each role's main workflow improvements.
- Before/after screenshots or local previews for all four roles at desktop and mobile sizes, using non-sensitive test data.
- The routes and meaningful checks actually verified, plus any remaining blocked or untested states.
- Explicit confirmation that the existing permission model, routing, payment calculations, and clinical/scheduling rules were preserved, supported by the checks performed; disclose any exception rather than assuming it.

The result should look like one carefully designed medical platform: clear patient next steps, efficient doctor workflows, an organized admin workspace, and a fast receptionist workspace. Start by inspecting the repository, then implement the redesign end to end.
