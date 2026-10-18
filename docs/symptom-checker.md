# Find a doctor by symptoms

Patients enter their symptoms, answer any follow-up questions a clinician has approved, and see doctors in the related specialties, nearest first. From there they use the normal profile and booking pages.

The page is **for navigation only**. It gives no diagnosis, possible conditions, risk score or treatment advice. It never says that anything has been ruled out. Two kinds of safety messaging appear:

- A **general emergency notice**, approved by a clinical reviewer, shown at the top of the page whatever the patient enters.
- **Safety rules**: clinician-written instructions that appear immediately when a patient picks a particular symptom or gives a particular answer. A rule shows only when a reviewer has approved and activated it. With no live rules, the page reacts to no answer and claims no assessment.

**The software is complete. The clinical content is not.** Everything shipped with the plugin is an illustrative draft written by the software team: the example symptoms (`includes/data/symptom-mapping-examples.json`) and the suggested notice wording. None of it is clinically reviewed. Nothing reaches patients until a clinical reviewer has approved and activated it.

## Where things are

| What | Where |
| --- | --- |
| Public page | Any page containing `[symptom_checker]`. One is created automatically. |
| Entry points | A "Not sure which doctor you need?" card on the home page, a "Find by symptoms" header link, and a link in the header's Doctors menu. They appear **only** while the page is available to patients. Administrators and reviewers also see the header link earlier, for their preview. |
| Administrators | Admin dashboard → Content → **Symptom mappings**. Receptionists never see it. |
| Clinical reviewers | Doctor dashboard → **Symptom mappings**. The tab appears only for doctors an administrator has made reviewers. |
| Clinic coordinates | Admin dashboard → Clinic → edit a clinic → **Map coordinates (optional)**. |

## Roles and permissions

Every action is checked on the server, in `Symptom_Mappings_Handler` and `Clinical_Workflow`. Hidden buttons are a convenience only.

| Action | Administrator | Clinical reviewer | Others |
| --- | --- | --- | --- |
| Add or edit symptoms, questions, safety rules and notice text; import; export; submit for review; archive; restore; view history | ✓ | ✓ | – |
| Approve, return for revision, activate or deactivate; approve the notice | Only if also a reviewer | ✓ | – |
| Delete (never while live); switch the page on or off; choose reviewers | ✓ | – | – |

- Reviewers hold the `doctor_ak_review_symptom_mappings` capability. Grant or remove it under **Clinical reviewers**. Only doctors and administrators can be reviewers, and they don't need administrator access.
- A reviewer may approve and activate content they edited themselves. The history records who edited and who approved each version.

## Review workflow

Symptoms (including their questions) and safety rules follow the same lifecycle:

```
Draft ──submit──▶ Pending review ──approve──▶ Reviewed ──activate──▶ Active (live)
  ▲                    │                         │                       │
  └──── return for revision (reason required) ───┘                  deactivate
                                                                         ▼
Archive (any non-live item) ──────────────────────────────────────▶ Inactive ──restore──▶ Draft
                                                                         └──reactivate (unchanged approved version)──▶ Active
```

- **Live items keep their approved version while they're being edited.** Editing an active symptom or rule creates a revision in Draft. Patients keep seeing the last approved version until the revision is submitted, approved and activated. So correcting a safety message never takes it off the page.
- **Deactivate** takes an item off the page immediately. Reviewers only.
- Changing only **internal notes** keeps the current review.
- Items with validation problems can't be submitted, approved or activated. Problems are shown on the list.
- Every change is written to the **audit log**: who did it, when, and the return reason. Activations and deletions also store the exact content at that moment. Use the **History** button on any symptom, rule, the notice, or the reviewers section.

### Safeguards between symptoms and rules

- A safety rule can be **activated only when every symptom it uses is live, with that question and those answers in its live version**. A rule that looks live can therefore always show.
- A symptom revision that removes a question or answer a live rule depends on **cannot be activated** until the rule is updated.
- A symptom that a live rule depends on **cannot be deactivated**, so the rule's instructions aren't silently lost. A symptom that any rule refers to cannot be deleted.
- A name, identifier or phrase belongs to one symptom only. This covers both live and working copies.
- Specialties must exist on the site. A symptom whose specialty is later removed is flagged until it's fixed.

## Turning it on: step by step

1. **Add map coordinates to clinics.** In Google Maps, right-click the clinic and click the numbers to copy them. Clinics without coordinates are still listed, with "Distance not available".
2. **Choose clinical reviewers** (Clinical reviewers section).
3. **Add symptoms.** Use any mix of:
   - **+ Add symptom.** See *Symptom fields* below.
   - **Import CSV.** See *CSV format*. Rows arrive as Drafts.
   - **Load example drafts.** These are illustrative and not reviewed.
   - **Copy those symptoms in as drafts.** This appears when the previous Symptom Checker's ruleset exists.
4. **Add follow-up questions and safety rules** with a clinician, if wanted. Both are optional.
5. **Review.** The author clicks **Submit for review**. Another reviewer clicks **Approve**, or **Return for revision** with a reason, and then **Activate**. Activate symptoms before the rules that use them. Bulk actions are available for symptoms.
6. **Approve the emergency notice.** Save the wording, then a clinical reviewer clicks **Approve notice**.
7. **Preview** with **Preview page** (`?sc_preview=1`), or the **Preview** button on a symptom, which opens the page with that symptom already chosen. Staff see working copies of everything, including drafts, under a "Staff preview" banner.
8. **Switch on** "Show 'Find a doctor by symptoms' to patients". This is refused until at least one symptom is live and the notice has an approved version.

## Symptom fields

| Field | Notes |
| --- | --- |
| Symptom name | Shown to patients. Max 80 characters. |
| Identifier | Unique slug (letters, numbers, hyphens). Made from the name if left blank. |
| Patient guidance | Optional. One clinician-approved line shown when the symptom is chosen. Max 500 characters. |
| English alternative phrases | One per line, max 40, including common misspellings. |
| Roman Urdu phrases | One per line, max 40. Other languages can be added with the `doctor_ak_symptom_languages` filter (`code => label`). Each gets its own field and CSV column. |
| Related specialties | At least one, from the site's list. |
| Follow-up questions | Optional. Up to 10. See below. |
| Internal notes | Reviewers only. Never sent to the patient page. |

### Follow-up questions

Questions are asked one at a time, in list order, after the patient picks the symptom. The patient sees a progress line ("Question 2 of 3") and can go back. Before seeing doctors they can review and change every answer.

| Setting | Notes |
| --- | --- |
| Question | Max 200 characters. |
| Answer type | **One answer**, **Several answers**, **Yes / No** (always exactly Yes and No), or **Number** (optional smallest and largest allowed answer, plus a unit such as "days"). Choice types take 2 to 8 answers. |
| Patients must answer | Required questions can't be skipped. |
| When to ask | Always, or only when an **earlier** choice or yes/no question has one of the chosen answers. |
| This question affects | **Navigation**, **Safety messages** and/or **Specialty matching**, recorded explicitly for reviewers. With "Specialty matching" ticked, each answer can add specialties to the results ("linked to your answer …"). Number questions can't change matching. |
| Clinician-reviewed explanation | Optional. Neutral help text under the question. |

There is **no scoring**. An answer can do only three things: show or hide a later question, add specialties a clinician chose, or show a safety rule a clinician wrote. The number range only limits what patients can type. Thresholds that should show a message belong in a reviewed safety rule.

Example (illustrative only, **not** a validated protocol): *Headache* → "How long have you had the headache?" (one answer: Started today / 1–3 days / More than 3 days) → "Did the headache begin suddenly and feel extremely severe?" (Yes / No) → a safety rule shown when that answer is "Yes". Every question, answer and rule must be reviewed by an appropriately qualified clinician before activation.

## Safety rules

| Field | Notes |
| --- | --- |
| Heading | e.g. "Get emergency help now". Max 120 characters. |
| How it's shown | **Emergency**: red, and always stops the remaining questions. **Urgent**: amber, and stops them only when "Stop the remaining questions" is ticked. |
| Instructions for patients | What to do now. One instruction per line. Max 1,000 characters. Wording that reassures patients or says something is ruled out is refused, e.g. "you are safe", "no need to see a doctor", "nothing serious", "ruled out". |
| Show this when | One or more conditions; any one is enough. A condition is a symptom (shown as soon as it's chosen), or a symptom plus one of its questions and the answers that show the rule. For a number question, the condition is a range the clinician enters. |
| Internal notes | Reviewers only, e.g. the clinical source. |

On the patient page:

- A triggered rule appears **at once**, above the questions and the doctor results. It is announced to screen readers and, when it stops the questions, takes focus.
- It stays on screen while it applies and can't be dismissed. Changing the answer that caused it removes it.
- While it applies, a fixed bar at the bottom of the screen ("Emergency: … · See instructions") keeps it one tap away on phones.
- When the questions stop, the patient sees "We've stopped the questions", with **Change my answers** and **Show doctors anyway**. Results still show, with the rule pinned above them.
- Answers to questions that are no longer being asked (because an earlier answer changed) never trigger rules or add specialties.

## CSV format

**Import CSV** accepts a file (max 1 MB) or pasted text. **Export CSV** downloads every symptom in the same format. **Download a template** gives a starter file.

```
name,slug,description,english_phrases,ur_roman_phrases,specialties,notes,status
Back pain,back-pain,"Pain anywhere in the back",backache | sore back,kamar dard,Orthopedic Surgeon | General Physician,,
```

- The first row must contain the column names. Only `name` and `specialties` are required. Unknown columns are ignored.
- Lists inside one cell are separated by `|`. Specialties can be site names ("General Physician") or identifiers (`general-physician`).
- One column per extra language: `<code>_phrases`, with hyphens written as underscores (`ur-roman` → `ur_roman_phrases`).
- `status` appears in exports for information and is **ignored on import**. Every imported row is a **Draft**.
- **Check import** previews each row as Ready, Duplicate (repeats an existing symptom's name, identifier or phrase, or an earlier row) or Needs fixing (e.g. an unknown specialty). Only Ready rows are imported. **Existing symptoms are never changed.** Re-importing an export skips everything already there.
- Follow-up questions aren't in the CSV. Add them on screen after import.
- Exported cells that start like a spreadsheet formula (`=`, `+`, `-`, `@`) are prefixed with `'`. Import removes the prefix again.
- The older formats still work: a JSON list of `{ name, synonyms, specialties }`, or one `Symptom | phrase, phrase | Specialty, Specialty` per line.

## How matching works (`assets/js/doctor-ak-symptom-match.js`)

- **Recognised:** text is split on commas, semicolons, full stops, new lines and "and" / "&" / "aur". A part is recognised only when, after ignoring filler words, it **exactly** matches a symptom's name or one of its English or Roman Urdu phrases. Filler words cover "I have", "severe", "since 2 days", "3 din se", "ho raha hai" and similar. Case, punctuation and simple plurals are ignored.
- **"Did you mean":** partial matches and close typos are never silently accepted, e.g. "stomach", "stomache pain", "pain". The patient chooses, or picks "None of these".
- **No match:** unmatched text is shown back with a message. If nothing matched, results suggest browsing general physicians or searching all doctors.
- Text-match closeness is used only for suggestion order. It never decides a specialty or a safety message.
- The **Try matching** box on the admin screen uses the same code, against either working copies or only what patients see now.

## Results and location

- Location is requested only on the results step, and only when there are specialties to show. The page explains why first. If it's granted, there is no extra step.
- Doctors are matched by specialty, then sorted by straight-line distance to their nearest matching clinic. Each doctor gets one card, at that clinic, with "Also at N other clinics". Distances are labelled "straight-line".
- If location is denied, blocked, timed out or unsupported, results still show. The patient can pick a city or area, or browse without location. The page never asks again after a refusal and never calls a doctor "nearby" without a real location.
- Cards reuse the Find a Doctor data: photo, name, matched specialty, clinic and area, distance (if known), fee and next availability (if known), **View profile** and **Book appointment**. Video consultations are listed separately. Nothing is invented: missing fees, availability or coordinates are left out.

## Privacy

- Everything the patient types or answers, and their location, stays in the browser. None of it goes into the URL, cookies, local or session storage, or the console, and nothing is sent to the site or to analytics.
- Symptoms and answers are never stored, logged or attached to appointments. Booking links carry only the doctor and clinic.
- The page receives only the **published copies** of live symptoms (name, identifier, guidance, phrases, specialties, questions) and live safety rules (heading, level, instructions, conditions), the approved notice, and the same public doctor and clinic details as the Find a Doctor page. Drafts, internal notes and reviewer details are never sent to patients. Approved rule conditions are public by design, because the page evaluates them in the browser.
- The audit log records staff actions only.

## Storage and migration

| Where | Contents |
| --- | --- |
| Option `doctor_ak_symptom_mappings` | Symptoms. Each has its working content, the `published` copy, `is_live`, status, source, notes, and created / updated / submitted / reviewed / activated (who and when), plus any return reason. |
| Option `doctor_ak_symptom_safety_rules` | Safety rules, same shape. |
| Options `…_next_id` | Next IDs. |
| Option `doctor_ak_symptom_finder_notice` | Notice working text, `approved_text`, and edited / approved (who and when). |
| Option `doctor_ak_symptom_finder_enabled` | `yes` / `no`. |
| Option `doctor_ak_symptom_mappings_schema` | Row format version (2). |
| Table `{prefix}dak_symptom_audit` | Audit log: object type and ID, action, user, summary, snapshot (activations and deletions), time. |

**Upgrade from the previous version** runs once, automatically, on the first page load after updating (`DB_Installer::maybe_upgrade()` → `Symptom_Mappings::migrate()`):

- Every existing symptom is kept with its name, phrases, specialties, notes, source and status.
- **Active** symptoms get their current content as their published version, so patients see exactly what they saw before. **Reviewed** and **Draft** symptoms stay as they were.
- An approved notice keeps its wording as the approved text.
- The audit table is created. History before the upgrade isn't available.

The previous Symptom Checker's options (`doctor_ak_symptom_rules_*`, `doctor_ak_symptom_checker_enabled`) are left untouched. `includes/class-symptom-rules.php` remains only so its symptoms can be copied in as drafts. Uninstalling the plugin leaves all of this data in place, like the rest of the plugin's data.

## Code map

| File | Role |
| --- | --- |
| `includes/class-clinical-workflow.php` | Shared lifecycle and permission rules (published copies, reviewer-only approval, return reasons). |
| `includes/class-symptom-mappings.php` | Symptoms, questions, validation, CSV, notice, publication switch, migration. |
| `includes/class-symptom-safety-rules.php` | Safety rules and their integrity checks against symptoms. |
| `includes/class-symptom-audit.php` | Audit log. |
| `frontend/class-symptom-mappings-handler.php` | The single AJAX endpoint (`doctor_ak_symptom_mappings`, by `op`). |
| `frontend/class-symptom-checker.php`, `templates/directory/symptom-checker.php`, `assets/js/doctor-ak-symptom-finder.js` | Patient page. |
| `assets/js/doctor-ak-symptom-match.js` | Pure matching, question-flow and rule logic, shared by the patient page, the admin "Try matching" box and the tests. |
| `templates/dashboard/partials/symptom-mappings.php`, `assets/js/doctor-ak-symptom-mappings-admin.js` | Admin and reviewer screen. |

## Tests

- **Automated:** `node --test "tests/js/*.test.js"` (no dependencies). It covers matching (Roman Urdu, filler words, no silent fuzzy matches, unmatched text), question order and conditional display, answer validation, rule triggering (choice, multi, number range, symptom-only, hidden answers ignored, emergency first, nothing without rules), answer-linked specialties, and ranking with and without location.
- **Verified during development** with WordPress stubs and synthetic data (not shipped):
  - migration of existing records;
  - every server-side permission (patients, receptionists, non-reviewer doctors, admins without the reviewer capability, bad nonce);
  - the full lifecycle including revisions of live items;
  - validation, safety-rule integrity, CSV import, export and round trip;
  - drafts never reaching the public payload;
  - a browser walk-through of the patient page at desktop and phone widths, and of the admin screen.

### Needs clinical review, not software tests

Software can't verify these. A qualified clinician must sign them off before activation:

- Each symptom's name, phrases (English and Roman Urdu) and related specialties.
- Each follow-up question: whether to ask it at all, its wording, answer options, required flag, conditions, explanation, and any answer-linked specialties.
- Each safety rule: which answers trigger it, its level, whether it stops the questions, and the instruction wording, including local emergency numbers and services.
- The general emergency notice.
- That the combination of questions and rules is **not** presented, or relied on, as a complete triage. The page's limitations text says so; reviewers should confirm it matches how the clinic intends the tool to be used.

## Deployment

1. Back up the database (the `wp_options` rows above at least).
2. Deploy the plugin files as usual. On the first page load, the audit table is created and the stored symptoms are upgraded. Nothing needs to be run by hand.
3. Open **Symptom mappings** and check that live symptoms still show "Live v1" and the notice shows "Approved".
4. Open **Preview page** and walk through a symptom with questions, and one with a rule, before adding new clinical content.
5. If the page was switched on before the update, it stays on. New questions and rules appear only after they're approved and activated.
