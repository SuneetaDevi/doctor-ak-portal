# CRM

One table for everyone the clinic deals with, from first enquiry to regular patient. Each row is a **contact**: a lead who hasn't booked yet, or a patient. Staff work the table with saved views, filters, saved segments and bulk actions. A side panel shows each contact's history, notes, tasks and emails. Staff can email one person or a whole filtered group, using reusable templates.

Email is the only message channel. Phone numbers are there for staff to call.

## Where things are

| What | Where |
| --- | --- |
| Administrators and receptionists | Admin dashboard → Users → **CRM** (tabs: People, Tasks, Emails) |
| Doctors | Doctor dashboard → **CRM** (People and Tasks; their own patients only) |
| Code | `includes/class-crm.php` (contacts and rules), `includes/class-crm-email.php` (templates, group emails, unsubscribing, segments), `frontend/class-crm-handler.php` (AJAX `doctor_ak_crm`), `templates/dashboard/partials/crm.php`, `assets/js/doctor-ak-crm.js`, `assets/css/doctor-ak-crm.css` |
| Tables | `dak_crm_contacts`, `dak_crm_links`, `dak_crm_notes`, `dak_crm_tasks`, `dak_crm_activity`, `dak_crm_templates`, `dak_crm_campaigns`, `dak_crm_messages` (version option `dak_crm_db_version`) |
| Background sending | WP-Cron event `doctor_ak_crm_email_queue` |

## Who can do what

Every check runs on the server in `Crm_Handler`. Hidden buttons are a convenience only.

| Action | Admin / receptionist | Doctor |
| --- | --- | --- |
| See contacts | All | Only people who booked or visited them |
| Payment totals, unpaid and no-show flags | ✓ | – |
| Change stage, owner, tags, details | ✓ | – |
| Set the next follow-up date, add notes and tasks | ✓ | ✓ |
| Add a contact, bulk actions, merge, CSV export | ✓ | – |
| Email people, manage templates, saved segments | ✓ | – |
| See emails in a contact's history | ✓ | – |

Receptionists need `crm` in their allowed sections, and the CRM tab can be switched off per role under role permissions. A doctor's history hides other doctors' bookings and visits and shows a one-line "hidden" note in their place.

## Where contacts come from

Contacts are created and updated automatically:

- **Bookings**: created, paid, rescheduled, checked in, completed, cancelled, refund requested or processed.
- **Patient accounts**: when a patient registers or staff add one.
- **Service requests**: when a request is submitted.
- **Check-ins**: when an encounter is opened.

The first time someone opens the CRM, existing patients, appointments and service requests are imported in batches. A progress line shows while this runs, and the import resumes if the page is closed part-way.

### Matching people

- A patient account always has its own contact.
- A guest booking joins an existing contact only when the **name matches and** the phone or email matches. Family members who share one phone number therefore stay separate.
- Phone numbers are compared without the country code or leading zero (`0300…`, `+92300…` and `0092300…` are the same).
- Likely duplicates appear in the side panel under **Possible duplicates**, where staff can merge them. Two patient accounts can't be merged. A merge keeps all emails, and if either record had unsubscribed, the merged one stays unsubscribed.

## Stages

New lead → Contacted → Booked → Patient, or Lost.

- Stages move forward automatically: a booking makes a contact **Booked**, and a check-in or completed visit makes them a **Patient**. Automatic changes never move a contact backwards.
- Completing a task on a new lead marks it **Contacted**.
- Marking someone **Lost** asks for a reason. A lost contact comes back only if they make a new booking after being marked lost.

## Saved views and segments

| View | Shows |
| --- | --- |
| All | Everyone |
| New leads | Stage is New lead |
| Follow-ups due | Next follow-up date is today or earlier |
| Pending payments | An online booking that isn't paid yet (staff only) |
| Possible no-shows | A past appointment, 3+ hours after its start, with no check-in, completion or cancellation (staff only). This is a hint for a phone call, not a record of a missed visit. |
| Inactive patients | Patients with no visit in the last 6 months |

Filters: search (name, phone, email), stage, source, doctor, owner, tag, date added and **last visit date**.

**Saved segments** store a view together with its filters under a name (e.g. "Diabetic patients, no visit in 6 months"). Use **Save as segment** under the filters, and pick a saved segment from the first filter. Segments are shared by all staff, and there can be up to 50. A segment is re-applied each time it's chosen, so it always shows who matches today.

## Email

### Two kinds of email

| Kind | For | Who gets it |
| --- | --- | --- |
| **Care message** | Their own visits, bookings or follow-up care, e.g. a review-visit reminder or "your report is ready" | Everyone with an email address |
| **Offers & news** | Health camps, new services, offers, clinic news | Only people who haven't unsubscribed. Every email has an unsubscribe link. |

Choose the kind honestly: a care message goes even to people who unsubscribed from offers, so it must not be used for promotions.

### Unsubscribing

- Every offers & news email has an **Unsubscribe** link and `List-Unsubscribe` headers, so mail apps can show their own one-click unsubscribe button.
- Opening the link shows a confirmation page. A person is unsubscribed only when they press the button (or their mail app's one-click unsubscribe does it), so mail scanners that open links can't unsubscribe anyone by accident. The page shows their address masked (`s***@example.com`).
- Unsubscribing also turns off **announcement emails** on their patient account, if they have one. The CRM follows the account setting too: a patient who turns announcements off in their dashboard settings gets no offers & news. This is the same opt-out rule the site already used for its announcement emails.
- Unsubscribing applies to the **email address**. If any record with that address has unsubscribed, nobody at that address gets offers & news.
- Staff can mark someone as not wanting offers & news from the side panel (e.g. they asked at reception). Staff can allow them again only if the person asked. Staff can't change the patient's own account setting.
- Care messages still reach people who unsubscribed.

### Emailing a group

1. On **People**, choose a view, filters or saved segment, or tick people in the table.
2. Click **Email these N people** (above the table) or **Email selected** (in the bulk bar).
3. Pick the kind of email, start from a template if you like, and write the subject and message. A greeting ("Hi Sara,") is added automatically.
4. The panel shows how many people will get it and who is skipped: no email address, unsubscribed, or sharing an address with someone else on the list (each address gets one copy).
5. Use **Preview** to see it as one recipient will, and **Send a test to me** to get a copy in your own inbox. The test isn't logged, and its unsubscribe link goes nowhere.
6. **Send** asks for confirmation, then shows progress.

Emails go out in batches of 10 while the CRM is open. If the page is closed, a background job sends 25 a minute until the rest are done. Everything is recorded, one row per person, including who was skipped and why.

The **Emails** tab lists every group email with its result (sent, failed, skipped, waiting, stopped). Opening one shows the message and every recipient. From there you can:

- **Send the rest now**, if it's still sending;
- **Stop sending** (people not yet emailed won't get it; sent emails can't be called back);
- **Try the failed ones again**.

A group email can go to at most 5,000 people at once.

### Emailing one person

In the side panel, **Email → Write an email** sends one email straight away. It appears in their history with the full message. You can't send offers & news to someone who unsubscribed.

### Templates

**Emails → Templates** holds reusable wording. Three care templates are added the first time the tab is opened: review visit reminder, missed appointment and unpaid booking. They match the saved views and can be edited or archived like any other template. Templates are archived, never deleted, so past emails still make sense.

Merge fields work in the subject, heading and message:

| Field | Becomes |
| --- | --- |
| `{first_name}` | First name ("Mr." / "Dr." etc. removed) |
| `{name}` | Full name |
| `{last_visit}` | e.g. 23 September 2026 |
| `{next_appointment}` | e.g. Thursday 30 October, 10:30 am |
| `{clinic_name}` | The clinic name from the footer settings |

An unknown field (e.g. a typo like `{first_nam}`) is refused when saving or sending. An empty field (e.g. no next appointment) is left out.

### History

A contact's history shows:

- every email sent from the CRM: the kind, who sent it, the group email it was part of, and for one-off emails the full message;
- the other emails the site sends them, such as booking confirmations, reminders and receipts. These are matched by email address and logged by **subject only**.

Doctors don't see emails.

### Sending reliability

- Each email is claimed before it's sent, so the open page and the background job can never send the same email twice.
- If a send is interrupted (for example the server times out), that email is marked **failed** after 15 minutes, with a note that it may or may not have arrived. It's never resent on its own; staff decide whether to try again.
- An email the mail server refuses is marked **failed** and can be retried from the group email's page.

Deliverability depends on the site's mail setup (Settings → Email Notifications sets the From name and address). Sending to large groups through a shared host's mail function can be throttled or land in spam, so a proper SMTP or transactional mail service is recommended before using group emails at scale.

## Notes on the numbers

- **Visits** counts check-ins and completed appointments.
- **Total paid** is paid appointments minus processed refunds. Bills raised inside an encounter for walk-ins aren't included yet.
- CSV export escapes cells that start with `=`, `+`, `-` or `@` so spreadsheets don't run them as formulas.

## Not built yet

- WhatsApp or SMS.
- Feedback surveys after visits, and the reports dashboard (Phase 3).
- Filtering by city or age (contacts don't store these yet).
