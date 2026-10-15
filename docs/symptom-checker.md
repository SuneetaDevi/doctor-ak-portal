# Symptom Checker

The Symptom Checker lets a visitor describe or pick symptoms and answer follow-up questions. It then shows next steps, possible explanations, relevant specialties and nearby doctors, and hands over to the normal booking flow.

**The software is complete. The clinical content is not.** The only ruleset shipped with the plugin (`includes/data/symptom-checker-demo-rules.json`) was written by the software team to exercise the code. It is marked `"is_demo": true`, has not been clinically reviewed, and **can never be approved or shown to patients** — the software refuses. A qualified clinician must write or review real rules before the checker is switched on.

## Where things are

| What | Where |
| --- | --- |
| Public page | Any page containing `[symptom_checker]` (a "Symptom Checker" page is created automatically). |
| Entry points | A "Check Your Symptoms" card on the home page, and a "Not sure who to see? Check your symptoms" link in the header's Doctors menu. Both appear **only** while the checker is available to patients. |
| Configuration | Admin dashboard → Content → **Symptom Checker** (administrators only; receptionists never see it). |
| Clinic coordinates | Admin dashboard → Clinic → edit a clinic → **Map coordinates (optional)**. |

## Turning it on — step by step

1. **Add map coordinates to clinics** (Clinic → edit → "Map coordinates"). In Google Maps, right-click the clinic and click the numbers (e.g. `24.8138, 67.0299`) to copy them. Clinics without coordinates are still listed, but without a distance.
2. **Choose clinical reviewers** (Symptom Checker → Clinical approval → Clinical reviewers). Only administrator accounts can be chosen, so a clinician needs an administrator account to review.
3. **Write the rules** in the **Draft rules** form — no JSON needed. Each part opens as its own section:
   - **Basics:** the rule set's name, the emergency message, the demonstration tick-box and the "when nothing matches" specialist.
   - **Symptoms, follow-up questions, warning phrases, warning signs, next steps, possible explanations and answer checks:** each item is a card with ordinary fields.
   - **Rules:** written with a **"Show when…"** builder, e.g. *Patient has… Chest pain* AND *Answer… "How long" is at least "1–4 weeks"*. Use **Combine** to choose "all of these" or "any one of these", and **Reverse** for "when this is NOT the case".
   - **Deleting** a symptom, question or answer also removes it from the rules that use it. Re-check those rules afterwards — a warning sign left with no conditions is reported as a problem.

   Use **Check for problems** and **Save draft**. Problems are listed by name, e.g. *Warning sign "Vomiting blood": add at least one condition*. Saving never changes what patients see. Technical staff can still open **Advanced: edit as JSON** (format below).
4. **Preview** with "Preview draft". Administrators see the draft on the public page, under a "Staff preview — not visible to patients" banner. The demonstration rules can be previewed this way for testing.
5. **Approve.** A designated reviewer who did *not* make the latest draft edit ticks the confirmation, adds a review note and clicks **Approve and publish**. This creates version 1, 2, … with the editor, approver, date and note recorded under Version history.
6. **Switch on** "Show the Symptom Checker to patients". This needs an approved, non-demonstration version. Turning it off hides the page content and both entry points immediately.

Every later change follows the same draft → review → approve path. Patients keep seeing the last approved version until a new one is approved.

### Approval is refused when…

- the approver isn't a designated reviewer, or isn't an administrator;
- the approver made the latest draft edit (a second person must check it);
- the draft has validation problems;
- the draft is marked `"is_demo": true`;
- there is no review note, or the confirmation box isn't ticked.

## Privacy

Everything the visitor types or answers, and their location, is processed **in their browser** by `assets/js/doctor-ak-symptom-engine.js`:

- nothing is sent to the site (no AJAX), written to the URL, stored in cookies, local or session storage, or logged;
- leaving the page or pressing "Start again" discards it;
- the page only *receives* the approved rules and the same public doctor/clinic information the Find a Doctor page already shows;
- the location is used only to calculate approximate straight-line distances. It is never stored, never shown as coordinates, and IP-based location is not used;
- booking links carry only the doctor, the visit type and the clinic — never symptoms or answers.

## Location behaviour

- Pressing **Start** asks for the location (the page says "We use your location to find relevant doctors near you"). If the visitor already allowed it, there is no new prompt. If they blocked it, the site never asks again.
- Questions and the emergency message never wait for the location.
- Results show **"Near [area]"** with **Change**. The area is the nearest clinic area within 10 km, else the nearest major city within 60 km, else "your current location".
- If the location is denied, unavailable or times out, the visitor can try again (blocked browsers need the setting changed), choose a city, or see all doctors without distances.

## Matching and ranking

**Free text** — `matchText()`:

- The text is split into clauses at punctuation and at "and / but / also / however…".
- In each clause, symptom labels and synonyms are matched longest phrase first, so "blood in stool" wins over "stool".
- Typos are tolerated on words of 5 or more letters: one edit, or two for 8 or more letters, same first letter. Everyday words such as "never" are never fuzzy-matched.
- Negation applies within its own clause ("no fever", "I don't have diarrhoea", "nahi"). Negated symptoms are shown back to the visitor ("You said you don't have…"), with an option to add them anyway.
- Uncertainty words ("maybe", "I think", "not sure") mark a symptom as uncertain.
- Clauses that match nothing are listed back to the visitor, never silently dropped.
- Warning phrases in `text_alerts` queue their screening question, even when negated.
- A duration such as "for 3 days" pre-selects the matching duration answer, which the visitor must confirm.

**Questions:**

- A question is asked when it `applies_to` a selected symptom (or `"*"`) and its `show_if` condition holds. Questions are ordered by `priority`, then file order.
- Changing an earlier answer or a symptom clears answers that no longer apply, repeating until stable, and tells the visitor.
- "Not sure" is stored as `unsure`. It never satisfies a score comparison, and results note that answers were uncertain.
- Warning signs (`red_flags`) are checked after every answer. Any match shows the emergency message immediately.

**Results:**

- `next_steps` whose condition matches are shown, most urgent first. When none match, the step marked `"fallback": true` is shown.
- `explanations` whose condition matches are shown as possibilities, never as a diagnosis or a likelihood.
- `conflicts` are shown on the "Check your answers" step.
- Specialties come from the matched explanations first, then from the selected symptoms. `fallback_specialty` is used only when nothing else applies.

**Doctors** — `rankDoctors()`:

- Only doctors with one of the suggested specialties are shown. Unrelated specialties are never used to fill the list.
- Each doctor appears once. Their nearest clinic with coordinates is the primary one, and their other clinics are under "N other locations".
- Doctors are ordered by:
  1. specialty order;
  2. distance;
  3. earliest open slot;
  4. name.
- With a location:
  - **Nearest to you:** clinics within 25 km;
  - **Search a wider area:** extends to 100 km, then to any distance;
  - **Other clinics (distance not available):** clinics with no coordinates.
- With a chosen city, only clinics in that city are shown. With no location, everyone is shown without distances.
- **Online video consultations** lists matching doctors who offer video visits.
- With no match at all, the page says so and links to the full directory.
- Fees and availability are the real ones, worked out the same way as on Find a Doctor: per clinic, from the doctor's services, sessions and bookings. When a value is unknown it is left out — never invented.

## Ruleset format (technical — the form writes this for you)

```jsonc
{
  "meta": { "name": "…", "is_demo": false },
  "emergency": { "title": "…", "message": "…", "contact_text": "" },
  "fallback_specialty": "general-physician",
  "symptoms": [ { "id": "abdominal_pain", "label": "…", "synonyms": [ "…" ], "specialties": [ "gastroenterologist" ] } ],
  "text_alerts": [ { "phrases": [ "…" ], "question": "screen_now" } ],
  "questions": [ {
    "id": "duration", "priority": 10, "applies_to": "*",
    "type": "single | multi | duration | severity | text",
    "text": "…", "help": "…", "allow_unsure": true, "required": true,
    "show_if": { /* condition */ },
    "options": [ { "value": "lt1d", "label": "…", "score": 1, "max_days": 1, "exclusive": false } ]
  } ],
  "red_flags":    [ { "id": "…", "title": "…", "when": { } } ],
  "next_steps":   [ { "id": "…", "urgency": "urgent | soon | routine | self_care", "title": "…", "text": "…", "when": { }, "fallback": false } ],
  "explanations": [ { "id": "…", "title": "…", "summary": "…", "when": { }, "specialties": [ "…" ] } ],
  "conflicts":    [ { "id": "…", "message": "…", "when": { } } ]
}
```

Format rules:

- **Ids and option values** are lowercase letters, numbers and `_`. `unsure` is reserved.
- **Specialties** must be slugs of this site's specializations, e.g. `general-physician`, `gastroenterologist`, `ent-specialist`.
- **`contact_text`** stays empty unless a verified emergency number is supplied by the clinical owner. The software never shows a number on its own.
- **Markup** is stripped from every text field.

### Conditions

| Condition | True when |
| --- | --- |
| `{ "all": [ … ] }` / `{ "any": [ … ] }` / `{ "not": c }` | all / any / not (`all: []` is true, `any: []` is false) |
| `{ "symptom": "id" }` | the symptom is selected |
| `{ "answer": "q", "equals": "v" }` | the answer is `v` (for multi: includes `v`) |
| `{ "answer": "q", "in": [ … ] }` | the answer (or any multi answer) is in the list |
| `{ "answer": "q", "includes": "v" }` | a multi answer includes `v` |
| `{ "answer": "q", "gte": n }` / `"lte": n` | the chosen option's `score` is ≥ / ≤ n |
| `{ "answered": "q" }` | the question has any answer |

The validator checks structure, duplicate ids, that every symptom, question, option value and specialty referenced exists, and that a fallback next step is present.

## Clinical content that needs professional review

Everything in a ruleset is clinical content. In the shipped demonstration file, all of the following is **unreviewed**:

- **Symptoms and synonyms**: all 19 symptoms, including the Urdu/Roman-Urdu synonyms (e.g. "pet dard", "bukhar", "ulti", "qabz", "peeliya").
- **Symptom → specialty mappings**: e.g. difficulty swallowing → gastroenterologist and ENT; headache → neurologist and general physician.
- **Screening question** `screen_now`: which warning signs are asked about and how they're worded.
- **Text alert phrases** that trigger the screening question.
- **Follow-up questions**: wording, answer options, the duration bands and their scores, the severity scale, the age groups, and branching (e.g. `abdo_with_food` only for upper abdominal pain).
- **All 7 warning signs (`red_flags`)**: e.g. chest pain with spreading pain, sweating or breathlessness; vomiting blood; black stools; a large amount of blood; jaundice with drowsiness; fever or headache with a stiff neck, a non-fading rash or a sudden severe headache. Also review **what is missing** from this list.
- **Emergency message wording**, and whether a verified local emergency number should be shown.
- **All 9 next steps**: urgency levels and wording, e.g. "Get checked in person today" when unsure about warning signs; dehydration advice; timeframes such as "within a couple of weeks".
- **All 7 possible explanations**: whether each should be shown, its trigger conditions and its wording (gallbladder, reflux, gastroenteritis, IBS, liver/bile duct, piles, oesophagus).
- **The conflict check** (weight loss with symptoms under a day).
- **Fallback specialty** (general physician) and the safety-net wording in the page itself ("If your symptoms get worse…", "This tool can't detect every emergency…").
- **The 25 km / 100 km search radii**: a service decision rather than clinical, but worth confirming.

Until a clinician has reviewed and approved a ruleset through the dashboard, the Symptom Checker must not be represented as validated or ready for patient use.
