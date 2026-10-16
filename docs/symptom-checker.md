# Find a doctor by symptoms

Patients type their symptoms, confirm anything unclear, and see doctors in the specialties those symptoms are linked to, nearest first. From there they use the normal profile and booking pages.

The page is **doctor discovery only**. It gives no diagnosis, possible conditions, treatment advice or urgency assessment, and it does not detect emergencies. A general emergency notice, approved by a clinical reviewer, is shown at the top whatever the patient enters.

**The software is complete. The clinical content is not.** The example mappings shipped with the plugin (`includes/data/symptom-mapping-examples.json`) were written by the software team. They are not clinically reviewed, always load as **Draft**, and are labelled "Example — not reviewed". Nothing reaches patients until a clinical reviewer has reviewed and activated it.

## Where things are

| What | Where |
| --- | --- |
| Public page | Any page containing `[symptom_checker]`. One is created automatically. |
| Entry points | A "Not sure which doctor you need?" card on the home page, a "Find by symptoms" header link, and a link in the header's Doctors menu. They appear **only** while the page is available to patients. Administrators and reviewers also see the header link earlier, for their preview. |
| Administrators | Admin dashboard → Content → **Symptom mappings**. Receptionists never see it. |
| Clinical reviewers | Doctor dashboard → **Symptom mappings**. The tab appears only for doctors an administrator has made reviewers. |
| Clinic coordinates | Admin dashboard → Clinic → edit a clinic → **Map coordinates (optional)**. |

## Roles and permissions

All of these are checked on the server, not just hidden in the screen.

| Action | Administrator | Clinical reviewer | Others |
| --- | --- | --- | --- |
| Add / edit / import mappings, edit notice text | ✓ | ✓ | – |
| Mark reviewed, activate / deactivate, approve the notice | Only if also a reviewer | ✓ | – |
| Delete mappings, switch the page on/off, choose reviewers | ✓ | – | – |

- Reviewers hold the `doctor_ak_review_symptom_mappings` capability. Grant or remove it under **Clinical reviewers** on the admin screen. Only doctors and administrators can be reviewers, and they don't need administrator access.
- **Nobody can review their own latest edit.** A mapping or notice edited by a reviewer must be reviewed by someone else, so plan on two reviewers, or an administrator who edits plus a reviewer who reviews.

## Turning it on — step by step

1. **Add map coordinates to clinics.** In Google Maps, right-click the clinic and click the numbers to copy them. Clinics without coordinates are still listed, with "Distance not available".
2. **Choose clinical reviewers** (admin screen → Clinical reviewers).
3. **Add mappings.** Use any mix of:
   - **+ Add symptom.** Enter a name, the alternative phrases patients use (one per line; include misspellings and Urdu / Roman Urdu), the related specialties from the site's list, and optional internal notes.
   - **Import list.** Paste one mapping per line, `Symptom | phrase, phrase | Specialty, Specialty`, or a JSON list. A preview shows which rows are ready, duplicates, or have unknown specialties. Only ready rows are imported, as drafts.
   - **Load example drafts.** Adds the bundled examples as drafts. Anything already present is skipped.
   - **Copy those symptoms in as drafts.** Appears when the previous Symptom Checker's ruleset exists.
4. **Review.** A reviewer checks each draft and clicks **Mark reviewed**, then **Activate**. Only **Active** mappings are used on the page. Bulk selection is available.
5. **Approve the emergency notice.** Save the wording (the suggested text is a starting point), then a reviewer who didn't make the last edit clicks **Approve notice**.
6. **Preview** with "Preview page" (`?sc_preview=1`). Staff see every mapping, including drafts, under a "Staff preview" banner.
7. **Switch on** "Show 'Find a doctor by symptoms' to patients". This is refused until at least one mapping is active and the notice is approved.

### Safeguards

- **Any change to a mapping's name, phrases or specialties returns it to Draft**, which takes it off the page until it is reviewed again. A notes-only change keeps the review.
- Changing the notice text returns it to draft, and the page stops being public until it's re-approved.
- If the active mappings or the approved notice disappear, the page stops being public even while switched on.
- A name or phrase can belong to only one mapping. Duplicates are refused when saving and skipped on import.
- Specialties must exist on the site. A mapping whose specialty is later removed is flagged "Unknown specialty", and can't be reviewed until it's fixed.

## How matching works (`assets/js/doctor-ak-symptom-match.js`)

- **Recognised:** text is split on commas, semicolons, full stops, new lines and "and" / "&" / "aur". A part is recognised only when, after ignoring filler words, it **exactly** matches a mapping's name or phrase. Filler words cover "I have", "severe", "since 2 days", "3 din se", "ho raha hai", and similar. Case and simple plurals are ignored.
- **"Did you mean":** partial matches and close typos are never silently accepted. Examples: "stomach", "stomache pain", "pain", or "chest pain when climbing stairs". The patient chooses, or picks "None of these".
- **No match:** anything unmatched is shown back with a message. If nothing matched, the results suggest browsing general physicians or searching all doctors.
- Patients can remove any symptom chip.
- The **Try matching** box on the admin screen uses the same code. It can include or exclude non-active mappings.

## Results and location

- **When location is asked for:** only after "Find doctors", and only when there are specialties to show. The page explains why before asking. If it's granted, there is no extra step.
- **Sorting:** doctors are matched by specialty, then sorted by straight-line distance to their nearest matching clinic. Each doctor gets one card, at that clinic, with "Also at N other clinics". Distances are labelled "straight-line".
- **When location isn't available:** if it's denied, already blocked, timed out or unsupported, the page offers an optional city / area choice, or browsing without location. It never asks again after a refusal. Doctors are never called "nearby" without a real location.
- **Cards:** photo, name, matched specialty, clinic and area, distance (if known), fee and next availability (if known), **View profile** and **Book appointment**. Video consultations are listed separately.

## Privacy

- Everything the patient types, and their location, stays in the browser.
- None of it goes into the URL, cookies, local/session storage or the console, and nothing is sent to the site or to analytics.
- Booking links carry only the doctor and clinic.
- Symptoms are never attached to appointments.
- The page receives only active mappings and the same public doctor and clinic details as the Find a Doctor page.

## Storage

| Option | Contents |
| --- | --- |
| `doctor_ak_symptom_mappings` | Mappings, each with: name, phrases, specialties, status, source, notes, and updated / reviewed / activated (who and when). |
| `doctor_ak_symptom_mappings_next_id` | Next mapping ID. |
| `doctor_ak_symptom_finder_notice` | Notice text, status, edited / approved (who and when). |
| `doctor_ak_symptom_finder_enabled` | `yes` / `no`. |

The previous Symptom Checker's options (`doctor_ak_symptom_rules_*`, `doctor_ak_symptom_checker_enabled`) are left untouched and are no longer used. `includes/class-symptom-rules.php` remains only so its symptoms can be copied in as drafts.
