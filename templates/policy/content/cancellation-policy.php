<?php
/**
 * Cancellation Policy — editorially corrected copy of the published page
 * content (see Policy_Pages), with the published rules presented as a
 * comparison table. Every rule is the published one, worded as published:
 * full refund 24 hours or more before; 50% refund within 24 hours; no refund
 * for a no-show without notice; full refund or free rescheduling when the
 * clinic cancels; case-by-case review for genuine emergencies. Nothing about
 * timing, amounts or methods was changed — the reconciliation with the Terms
 * page and the booking system's configured refund window is a separate
 * proposal for owner review.
 *
 * Shown only while the stored page content still matches 'fingerprint'.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'fingerprint' => 'b3e03493728964374186b57a2f37cb3f',
	'meta'        => array(),
	'html'        => <<<'HTML'
<p>We understand that schedules may change. To help us serve all patients efficiently, please review our cancellation policy.</p>

<h2>Cancellation and refund rules</h2>
<p>The table below sets out what happens in each situation.</p>
<div class="dak-policy-table-wrap">
<table class="dak-policy-table">
<caption class="dak-visually-hidden">Cancellation situations, timing and refund outcomes</caption>
<thead>
<tr><th scope="col">Situation</th><th scope="col">Cancellation timing</th><th scope="col">Refund or rescheduling outcome</th></tr>
</thead>
<tbody>
<tr><th scope="row">Patient cancellation</th><td><strong>24 hours or more</strong> before the scheduled consultation</td><td>Eligible for a <strong>full refund</strong></td></tr>
<tr><th scope="row">Patient cancellation</th><td><strong>Within 24 hours</strong> of the scheduled consultation</td><td>Eligible for a <strong>50% refund</strong></td></tr>
<tr><th scope="row">No-show</th><td>Patient does not attend without prior notice</td><td>Not eligible for any refund</td></tr>
<tr><th scope="row">Clinic cancellation</th><td>Doctor AK Lohana Clinics needs to cancel or reschedule due to emergencies, doctor availability or unforeseen circumstances</td><td>Patient may choose either a <strong>full refund</strong> or a <strong>rescheduled appointment at no additional cost</strong></td></tr>
<tr><th scope="row">Emergency exceptions</th><td>Genuine medical or family emergencies</td><td>Management may review refund requests on a case-by-case basis</td></tr>
</tbody>
</table>
</div>

<h2>How to cancel</h2>
<p>Patients may cancel appointments by contacting us through:</p>
<ul>
<li>WhatsApp;</li>
<li>email;</li>
<li>phone (where applicable).</li>
</ul>

<h2>Contact</h2>
<div class="dak-policy-contact">
<p>For appointment cancellations:</p>
<ul class="dak-policy-contact-list">
<li><strong>Email:</strong> <a href="mailto:draklohanaclinics@gmail.com">draklohanaclinics@gmail.com</a></li>
<li><strong>WhatsApp:</strong> <a href="https://wa.me/923343646307">+92 334 3646307</a></li>
</ul>
</div>
HTML
	,
);
