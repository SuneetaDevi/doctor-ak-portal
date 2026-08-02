<?php
/**
 * Template: Doctor Onboarding Agreement & Code of Conduct — shown inside the
 * registration form's Terms & Conditions modal when "I'm a Doctor" is
 * selected, and required to be accepted before an account can be created.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="dak-terms-italic"><?php esc_html_e( '(To be accepted digitally during registration)', 'doctor-ak-portal' ); ?></p>

<h3><?php esc_html_e( '1. Scope of Engagement & Purpose', 'doctor-ak-portal' ); ?></h3>
<p><?php esc_html_e( 'This platform provides a digital service enabling licensed medical practitioners ("Consultants") to offer teleconsultations, clinic appointment bookings, and health communication services to registered patients.', 'doctor-ak-portal' ); ?></p>

<h3><?php esc_html_e( '2. Obligations & Professional Qualifications of the Doctor', 'doctor-ak-portal' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Licensure & Credentials:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'The Consultant confirms they hold a valid, active license from the relevant national medical council and possess no pending legal or disciplinary suspensions.', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Standard of Care:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'Teleconsultations must be conducted adhering to standard clinical guidelines. The Consultant retains full clinical autonomy and professional responsibility for diagnosis, advice, and treatment plans provided.', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Limitations of Telemedicine:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'The Consultant agrees to advise patients to seek immediate, physical emergency room care if their condition is assessed as acute, critical, or unsuitable for virtual evaluation.', 'doctor-ak-portal' ); ?></li>
</ul>

<h3><?php esc_html_e( '3. Patient Data & Medical Records', 'doctor-ak-portal' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Confidentiality:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'The Consultant agrees to keep all Patient Identifiable Information (PII) strictly confidential.', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Record Keeping:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'The Consultant is responsible for accurately updating medical notes, electronic prescriptions, and diagnostic requests on the platform interface following each consultation.', 'doctor-ak-portal' ); ?></li>
</ul>

<h3><?php esc_html_e( '4. Financial Terms & Platform Charges', 'doctor-ak-portal' ); ?></h3>
<ul>
	<li><strong><?php esc_html_e( 'Consultation Fees:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'Fees for virtual or clinic visits are set in accordance with the agreed schedule between the platform and the Consultant.', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Payout Terms:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'Platform service fees, transaction costs, or commission deductions (if applicable) will be processed on a regular cycle communicated to the Consultant.', 'doctor-ak-portal' ); ?></li>
</ul>

<h3><?php esc_html_e( '5. Platform Usage & Conduct', 'doctor-ak-portal' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'The Consultant shall not share platform credentials with unverified third parties.', 'doctor-ak-portal' ); ?></li>
	<li><?php esc_html_e( 'Direct off-platform payment requests to platform-sourced patients are strictly prohibited without authorization.', 'doctor-ak-portal' ); ?></li>
</ul>
