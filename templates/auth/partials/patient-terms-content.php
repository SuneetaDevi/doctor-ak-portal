<?php
/**
 * Template: Patient Terms & Conditions & Informed Consent — shown inside the
 * registration form's Terms & Conditions modal when "I'm a Patient" is
 * selected, and required to be accepted before an account can be created.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h3><?php esc_html_e( '1. Platform Purpose & Emergency Services Disclaimer', 'doctor-ak-portal' ); ?></h3>
<p><strong><?php esc_html_e( 'CRITICAL MEDICAL NOTICE:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'This platform is NOT an emergency response service. If you are experiencing a life-threatening medical emergency, chest pain, severe shortness of breath, acute bleeding, or severe trauma, do not rely on this online platform. Please visit the nearest hospital emergency room immediately.', 'doctor-ak-portal' ); ?></p>

<h3><?php esc_html_e( '2. Nature of Telemedicine & Informed Consent', 'doctor-ak-portal' ); ?></h3>
<p><?php esc_html_e( 'By registering and scheduling a consultation on this platform, you acknowledge and agree that:', 'doctor-ak-portal' ); ?></p>
<ul>
	<li><strong><?php esc_html_e( 'Virtual Limitations:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'Video or online consultations rely on the information and medical history you provide. They cannot fully substitute for a comprehensive physical, hands-on examination when clinically required.', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Referrals:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'The consulting doctor may determine that your condition requires a physical, in-person clinical visit or additional laboratory tests before a diagnosis can be rendered.', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Technical Factors:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'Video/audio quality depends on internet connection stability. The platform is not liable for disruptions caused by technical failures outside its control.', 'doctor-ak-portal' ); ?></li>
</ul>

<h3><?php esc_html_e( '3. Prescriptions & Medication Guidelines', 'doctor-ak-portal' ); ?></h3>
<ul>
	<li><?php esc_html_e( "Prescriptions generated via the platform are based on the doctor's professional judgment following your consultation.", 'doctor-ak-portal' ); ?></li>
	<li><?php esc_html_e( 'Doctors on this platform will not prescribe controlled substances, narcotics, psychotropic drugs, or high-risk emergency medications via telemedicine.', 'doctor-ak-portal' ); ?></li>
</ul>

<h3><?php esc_html_e( '4. User Accounts, Payments & Cancellations', 'doctor-ak-portal' ); ?></h3>
<p><strong><?php esc_html_e( '4.1 Accuracy of Information:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'You agree to provide correct personal, contact, and medical history details. Providing misleading information may impair the clinical accuracy of your care.', 'doctor-ak-portal' ); ?></p>
<p><strong><?php esc_html_e( '4.2 Payment Gateways, Digital Wallet Safety & Non-Liability for Scams/Theft:', 'doctor-ak-portal' ); ?></strong></p>
<ul>
	<li><strong><?php esc_html_e( 'Third-Party Processing:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'All online payments — including credit/debit cards, bank transfers, and mobile wallet transactions (e.g., EasyPaisa, JazzCash, Raast, or internet banking) — are processed directly through secure, licensed third-party payment service providers (PSPs).', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Non-Liability for Financial Loss/Scams:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'This platform and its associated medical staff shall not be held liable or financially responsible for unauthorized transactions, fraud, or identity theft on your personal account or device; phishing attempts, fraudulent OTP sharing, or social engineering scams; or banking errors, double-deductions, or payment delays caused by third-party payment gateways.', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'User Safeguards:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'You are strictly responsible for maintaining the confidentiality of your PINs, passwords, card numbers, and OTPs. This platform\'s staff will never contact you asking for your banking passwords, card CVVs, or digital wallet OTPs.', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Dispute Resolution:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'In the event of a fraudulent transaction or payment discrepancy, you must report the issue directly to your issuing bank or mobile wallet provider.', 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Payment Terms:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( "Consultation fees must be settled prior to confirmation of the appointment as per the platform's payment gateway options.", 'doctor-ak-portal' ); ?></li>
	<li><strong><?php esc_html_e( 'Cancellation/Refunds:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( "Appointments cancelled with sufficient notice prior to the scheduled slot qualify for rescheduling or refund per the platform's refund policy.", 'doctor-ak-portal' ); ?></li>
</ul>

<h3><?php esc_html_e( '5. Privacy & Medical Confidentiality', 'doctor-ak-portal' ); ?></h3>
<ul>
	<li><?php esc_html_e( 'Your health records, diagnostic uploads, and consultation details are protected under standard medical privacy guidelines and encrypted platform security protocols.', 'doctor-ak-portal' ); ?></li>
	<li><?php esc_html_e( 'Medical information will only be accessed by your consulting healthcare provider and authorized platform support personnel necessary for rendering services.', 'doctor-ak-portal' ); ?></li>
</ul>
