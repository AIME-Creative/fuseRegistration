<?php
/**
 * Fuse 2026 Non-Member Registration Form Template
 *
 * Skips the email/membership verification step entirely.
 * Visitors land directly on ticket selection and pay the standard non-member rate.
 * Use the [fuse_registration_nonmember] shortcode to embed this form.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="fuse-reg-wrapper fuse-reg-wrapper--nonmember">
    <!-- Progress Bar (3 steps, displayed as 1-2-3, internally driven by data-step 2-3-4) -->
    <div class="fuse-progress-bar">
        <div class="fuse-progress-step fuse-progress-step--active" data-step="2">
            <span class="fuse-progress-number">1</span>
            <span class="fuse-progress-label">Select Ticket</span>
        </div>
        <div class="fuse-progress-step" data-step="3">
            <span class="fuse-progress-number">2</span>
            <span class="fuse-progress-label">Your Details</span>
        </div>
        <div class="fuse-progress-step" data-step="4">
            <span class="fuse-progress-number">3</span>
            <span class="fuse-progress-label">Confirm & Pay</span>
        </div>
    </div>

    <form id="fuse-registration-form" class="fuse-reg-form" novalidate>

        <!-- STEP 1 (internal step 2): Ticket Selection -->
        <div class="fuse-reg-step fuse-reg-step--active" data-step="2">
            <div class="fuse-reg-step-content">
                <h2 class="fuse-reg-step-title">Select Your Ticket</h2>
                <p class="fuse-reg-step-description">Choose your ticket type and add-ons.</p>

                <div class="fuse-ticket-section">
                    <h3 class="fuse-ticket-section-title">Main Ticket</h3>
                    <div class="fuse-ticket-grid" id="ticket-options">
                        <!-- Populated by JavaScript -->
                    </div>
                    <div class="fuse-membership-nudge">
                        <div class="fuse-membership-nudge__eyebrow">💰 STOP — There's a better deal</div>
                        <p class="fuse-membership-nudge__headline">
                            This $<?php echo $nm_ticket_price; ?> ticket is included <em>free</em> with an AIME membership — which starts at just <strong>$<?php echo $nm_membership_low; ?>/year</strong>.
                        </p>
                        <p class="fuse-membership-nudge__body">
                            That's a saving of <strong>$<?php echo $nm_savings; ?></strong> — and you'd still have a full year of membership benefits after Fuse. Most members recoup the cost in their first deal.
                        </p>
                        <a href="https://aimegroup.com/broker-membership/" target="_blank" rel="noopener" class="fuse-membership-nudge__cta">
                            Get a membership &amp; save $<?php echo $nm_savings; ?> &rarr;
                        </a>
                    </div>
                </div>

                <div class="fuse-addons-section">
                    <h3 class="fuse-addons-section-title">Add-Ons</h3>
                    <div class="fuse-addons-list" id="addon-options">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>

                <div class="fuse-guest-section" id="guest-section">
                    <h3 class="fuse-guest-section-title">Guests</h3>
                    <p class="fuse-guest-section-note">Add guests to your registration. Each guest ticket will be added to your total.</p>
                    <div id="guest-list">
                        <!-- Guest rows added dynamically -->
                    </div>
                    <button type="button" class="fuse-btn fuse-btn--secondary fuse-btn--small" id="btn-add-guest">+ Add Guest</button>
                </div>

                <div class="fuse-reg-step-actions">
                    <span></span><!-- spacer so Next aligns right -->
                    <button type="button" class="fuse-btn fuse-btn--primary" id="btn-step-next-2">Next: Your Details</button>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="fuse-order-summary">
                <h3 class="fuse-summary-title">Order Summary</h3>
                <div class="fuse-summary-items" id="summary-items">
                    <div class="fuse-summary-item fuse-summary-item--empty">
                        <span>Select a ticket to continue</span>
                    </div>
                </div>
                <div class="fuse-summary-total" id="summary-total">
                    <div class="fuse-summary-amount">Total: <span class="fuse-amount">$0.00</span></div>
                </div>
            </div>
        </div>

        <!-- STEP 2 (internal step 3): Attendee Details -->
        <div class="fuse-reg-step" data-step="3">
            <div class="fuse-reg-step-content">
                <h2 class="fuse-reg-step-title">Your Information</h2>
                <p class="fuse-reg-step-description">Please provide your details for this registration.</p>

                <div class="fuse-form-row">
                    <div class="fuse-field-group">
                        <label for="fuse-first-name" class="fuse-field-label">First Name <span class="fuse-required">*</span></label>
                        <input
                            type="text"
                            id="fuse-first-name"
                            name="first_name"
                            class="fuse-field-input"
                            placeholder="First name"
                            required
                        >
                        <div class="fuse-field-error" id="first-name-error"></div>
                    </div>

                    <div class="fuse-field-group">
                        <label for="fuse-last-name" class="fuse-field-label">Last Name <span class="fuse-required">*</span></label>
                        <input
                            type="text"
                            id="fuse-last-name"
                            name="last_name"
                            class="fuse-field-input"
                            placeholder="Last name"
                            required
                        >
                        <div class="fuse-field-error" id="last-name-error"></div>
                    </div>
                </div>

                <div class="fuse-field-group">
                    <label for="fuse-preferred-name" class="fuse-field-label">Preferred Name</label>
                    <input
                        type="text"
                        id="fuse-preferred-name"
                        name="preferred_name"
                        class="fuse-field-input"
                        placeholder="How should we address you?"
                    >
                </div>

                <div class="fuse-form-row">
                    <!-- Email is EDITABLE in non-member mode (not verified in step 1) -->
                    <div class="fuse-field-group">
                        <label for="fuse-email-nonmember" class="fuse-field-label">Email <span class="fuse-required">*</span></label>
                        <input
                            type="email"
                            id="fuse-email-nonmember"
                            name="email"
                            class="fuse-field-input"
                            placeholder="your@email.com"
                            required
                        >
                        <div class="fuse-field-error" id="email-nonmember-error"></div>
                    </div>

                    <div class="fuse-field-group">
                        <label for="fuse-phone" class="fuse-field-label">Phone <span class="fuse-required">*</span></label>
                        <input
                            type="tel"
                            id="fuse-phone"
                            name="phone"
                            class="fuse-field-input"
                            placeholder="(555) 000-0000"
                            required
                        >
                        <div class="fuse-field-error" id="phone-error"></div>
                    </div>
                </div>

                <div class="fuse-field-group">
                    <label for="fuse-company" class="fuse-field-label">Company <span class="fuse-required">*</span></label>
                    <input
                        type="text"
                        id="fuse-company"
                        name="company"
                        class="fuse-field-input"
                        placeholder="Your company"
                        required
                    >
                </div>

                <div class="fuse-form-row">
                    <div class="fuse-field-group">
                        <label for="fuse-gender" class="fuse-field-label">Gender</label>
                        <select id="fuse-gender" name="gender" class="fuse-field-select">
                            <option value="">Select an option</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Non-binary">Non-binary</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                </div>

                <div class="fuse-field-group fuse-field-group--checkbox">
                    <input
                        type="checkbox"
                        id="fuse-marketing-consent"
                        name="marketing_consent"
                        class="fuse-field-checkbox"
                    >
                    <label for="fuse-marketing-consent" class="fuse-field-label fuse-field-label--inline">
                        I agree to receive marketing communications from Fuse
                    </label>
                </div>

                <div class="fuse-reg-step-actions">
                    <button type="button" class="fuse-btn fuse-btn--secondary" id="btn-step-back-3">Back</button>
                    <button type="button" class="fuse-btn fuse-btn--primary" id="btn-step-next-3">Next: Confirm & Pay</button>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="fuse-order-summary">
                <h3 class="fuse-summary-title">Order Summary</h3>
                <div class="fuse-summary-items" id="summary-items-3">
                    <!-- Populated by JavaScript -->
                </div>
                <div class="fuse-summary-total" id="summary-total-3">
                    <div class="fuse-summary-amount">Total: <span class="fuse-amount">$0.00</span></div>
                </div>
            </div>
        </div>

        <!-- STEP 3 (internal step 4): Confirmation / Payment -->
        <div class="fuse-reg-step" data-step="4">
            <div class="fuse-reg-step-content">
                <h2 class="fuse-reg-step-title">Confirm & Complete Registration</h2>
                <p class="fuse-reg-step-description">Review your information and complete your registration.</p>

                <div class="fuse-confirmation-section">
                    <h3 class="fuse-confirmation-title">Attendee Information</h3>
                    <div class="fuse-confirmation-grid">
                        <div class="fuse-confirmation-item">
                            <span class="fuse-confirmation-label">Name:</span>
                            <span class="fuse-confirmation-value" id="confirm-name"></span>
                        </div>
                        <div class="fuse-confirmation-item">
                            <span class="fuse-confirmation-label">Email:</span>
                            <span class="fuse-confirmation-value" id="confirm-email"></span>
                        </div>
                        <div class="fuse-confirmation-item">
                            <span class="fuse-confirmation-label">Company:</span>
                            <span class="fuse-confirmation-value" id="confirm-company">-</span>
                        </div>
                        <div class="fuse-confirmation-item">
                            <span class="fuse-confirmation-label">Phone:</span>
                            <span class="fuse-confirmation-value" id="confirm-phone">-</span>
                        </div>
                    </div>
                </div>

                <div class="fuse-confirmation-section">
                    <h3 class="fuse-confirmation-title">Registration Details</h3>
                    <div class="fuse-confirmation-grid">
                        <div class="fuse-confirmation-item">
                            <span class="fuse-confirmation-label">Ticket Type:</span>
                            <span class="fuse-confirmation-value" id="confirm-ticket"></span>
                        </div>
                        <div class="fuse-confirmation-item">
                            <span class="fuse-confirmation-label">Add-ons:</span>
                            <span class="fuse-confirmation-value" id="confirm-addons">None</span>
                        </div>
                    </div>
                </div>

                <div class="fuse-reg-step-actions">
                    <button type="button" class="fuse-btn fuse-btn--secondary" id="btn-step-back-4">Back</button>
                    <button type="button" class="fuse-btn fuse-btn--primary" id="btn-submit-registration">
                        <span class="fuse-btn-text" id="submit-btn-text">Proceed to Payment</span>
                        <span class="fuse-btn-spinner" style="display: none;">
                            <i class="fuse-spinner"></i>
                        </span>
                    </button>
                </div>

                <div class="fuse-form-notice" id="form-notice" style="display: none;"></div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="fuse-order-summary">
                <h3 class="fuse-summary-title">Order Summary</h3>
                <div class="fuse-summary-items" id="summary-items-4">
                    <!-- Populated by JavaScript -->
                </div>
                <div class="fuse-summary-total" id="summary-total-4">
                    <div class="fuse-summary-amount">Total: <span class="fuse-amount">$0.00</span></div>
                </div>
                <p class="fuse-payment-note" id="payment-note" style="display: none;"></p>
                <div class="fuse-membership-nudge fuse-membership-nudge--summary">
                    <div class="fuse-membership-nudge__eyebrow">⚡ Before you pay</div>
                    <p class="fuse-membership-nudge__headline">
                        A one-year AIME membership is <strong>$<?php echo $nm_membership_low; ?></strong> — and includes this ticket.
                    </p>
                    <p class="fuse-membership-nudge__body">
                        You're about to spend $<?php echo $nm_ticket_price; ?> on just the ticket. For $<?php echo $nm_savings; ?> less you get the ticket <em>plus</em> 12 months of membership.
                    </p>
                    <a href="https://aimegroup.com/broker-membership/" target="_blank" rel="noopener" class="fuse-membership-nudge__cta">
                        Join as a member instead &rarr;
                    </a>
                </div>
            </div>
        </div>
    </form>

    <!-- Success Message (shown after free registration — unlikely for non-members but kept for edge cases) -->
    <div class="fuse-reg-success" id="success-message" style="display: none;">
        <div class="fuse-success-content">
            <h2 class="fuse-success-title">Registration Complete!</h2>
            <p class="fuse-success-message">
                Your registration has been confirmed. You will receive a confirmation email shortly.
            </p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fuse-btn fuse-btn--primary">
                Return to Home
            </a>
        </div>
    </div>
</div>
