<?php
/**
 * Admin Add Registration Template
 * Manual registration form with member lookup and invoice sending
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Add Manual Registration</h1>

    <div id="fuse-add-registration-content">
        <div class="fuse-form-container">
            <form id="fuse-add-registration-form">
                <!-- Email and Member Lookup -->
                <div class="fuse-form-group">
                    <label>Email Address</label>
                    <div class="fuse-email-group">
                        <input type="email" name="email" id="add-email" required placeholder="Enter email address">
                        <button type="button" class="button button-secondary" id="add-lookup-member">Lookup Member</button>
                    </div>
                    <div id="add-member-lookup-result"></div>
                </div>

                <!-- Personal Information -->
                <div class="fuse-form-row">
                    <div class="fuse-form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" id="add-first_name" required>
                    </div>
                    <div class="fuse-form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" id="add-last_name" required>
                    </div>
                </div>

                <div class="fuse-form-group">
                    <label>Preferred Name</label>
                    <input type="text" name="preferred_name" id="add-preferred_name">
                </div>

                <div class="fuse-form-row">
                    <div class="fuse-form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" id="add-phone">
                    </div>
                    <div class="fuse-form-group">
                        <label>Gender</label>
                        <select name="gender" id="add-gender">
                            <option value="">Select an option</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Non-binary">Non-binary</option>
                            <option value="Prefer not to say">Prefer not to say</option>
                        </select>
                    </div>
                </div>

                <div class="fuse-form-group">
                    <label>Company</label>
                    <input type="text" name="company" id="add-company">
                </div>

                <!-- Registration Details -->
                <div class="fuse-form-row">
                    <div class="fuse-form-group">
                        <label>Ticket Type *</label>
                        <select name="ticket_type" id="add-ticket_type" required>
                            <option value="">Select ticket type...</option>
                            <option value="general_admission">General Admission</option>
                            <option value="vip">VIP</option>
                        </select>
                    </div>
                    <div class="fuse-form-group">
                        <label>Tier</label>
                        <select name="tier" id="add-tier">
                            <option value="">Non-Member</option>
                            <option value="Premium">Premium</option>
                            <option value="Elite">Elite</option>
                            <option value="VIP">VIP</option>
                        </select>
                    </div>
                </div>

                <div class="fuse-form-row">
                    <div class="fuse-form-group">
                        <label>Purchase Type</label>
                        <select name="purchase_type" id="add-purchase_type">
                            <option value="">Select...</option>
                            <option value="pending">Pending (Invoice Sent)</option>
                            <option value="purchased">Purchased</option>
                            <option value="claimed">Claimed</option>
                        </select>
                    </div>
                    <div class="fuse-form-group">
                        <label>Fuse Attendance</label>
                        <input type="text" name="fuse_attendance" id="add-fuse_attendance">
                    </div>
                </div>

                <div class="fuse-form-row">
                    <div class="fuse-form-group">
                        <label>
                            <input type="checkbox" name="hall_of_aime" id="add-hall_of_aime" value="1">
                            Hall of AIME
                        </label>
                    </div>
                    <div class="fuse-form-group">
                        <label>
                            <input type="checkbox" name="wmn_at_fuse" id="add-wmn_at_fuse" value="1">
                            WMN at Fuse
                        </label>
                    </div>
                    <div class="fuse-form-group">
                        <label>
                            <input type="checkbox" name="vip_luncheon" id="add-vip_luncheon" value="1">
                            VIP Luncheon
                        </label>
                    </div>
                    <div class="fuse-form-group">
                        <label>
                            <input type="checkbox" name="vetted_va" id="add-vetted_va" value="1">
                            Vetted VA
                        </label>
                    </div>
                </div>

                <div class="fuse-form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="add-notes" rows="4" placeholder="Add any additional notes..."></textarea>
                </div>

                <!-- Guests Section -->
                <div id="add-guests-section">
                    <h3>Guests</h3>
                    <div id="add-guests-list"></div>
                    <button type="button" class="button button-secondary" id="add-add-guest">Add Guest</button>
                </div>

                <!-- Form Actions -->
                <div class="fuse-form-actions">
                    <button type="submit" class="button button-primary" id="add-save-registration">Save Registration</button>
                    <button type="button" class="button button-primary" id="add-save-and-invoice" style="background-color: #2271b1;">Save & Send Invoice</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Invoice Modal -->
    <div id="fuse-invoice-modal" class="fuse-modal" style="display: none;">
        <div class="fuse-modal-content">
            <div class="fuse-modal-header">
                <h2>Send Invoice</h2>
                <button type="button" class="fuse-modal-close">&times;</button>
            </div>
            <div class="fuse-modal-body">
                <p>Review invoice for <strong id="invoice-recipient-name"></strong>:</p>
                <div id="invoice-line-items">
                    <!-- Populated dynamically -->
                </div>
                <!-- Add Line Item hidden — invoice items are read-only, generated from registration data -->
                <!-- <button type="button" class="button button-secondary" id="invoice-add-line-item">Add Line Item</button> -->
            </div>
            <div class="fuse-modal-footer">
                <button type="button" class="button button-secondary fuse-modal-close">Cancel</button>
                <button type="button" class="button button-primary" id="fuse-send-invoice-confirm">Send Invoice</button>
            </div>
        </div>
    </div>
</div>

<style>
    .fuse-form-container {
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 30px;
        max-width: 800px;
    }

    .fuse-form-group {
        margin-bottom: 20px;
    }

    .fuse-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
    }

    .fuse-form-group input,
    .fuse-form-group select,
    .fuse-form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: inherit;
        font-size: 14px;
    }

    .fuse-form-group input[type="checkbox"] {
        width: auto;
        margin-right: 8px;
    }

    .fuse-email-group {
        display: flex;
        gap: 10px;
    }

    .fuse-email-group input {
        flex: 1;
    }

    .fuse-email-group button {
        flex-shrink: 0;
    }

    #add-member-lookup-result {
        margin-top: 10px;
        font-size: 14px;
    }

    #add-member-lookup-result.success {
        color: #207a00;
        background: #f0f8f0;
        padding: 10px;
        border-radius: 4px;
    }

    #add-member-lookup-result.error {
        color: #d32f2f;
        background: #fef5f5;
        padding: 10px;
        border-radius: 4px;
    }

    .fuse-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    #add-guests-section {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    #add-guests-list {
        margin-bottom: 15px;
    }

    .guest-item {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .guest-item-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .guest-item-remove {
        cursor: pointer;
        color: #d32f2f;
        font-weight: 500;
    }

    .guest-item-fields {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .guest-item-fields input {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .fuse-form-actions {
        display: flex;
        gap: 10px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    .fuse-form-actions button {
        padding: 10px 20px;
    }

    .fuse-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .fuse-modal-content {
        background: #fff;
        border-radius: 4px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .fuse-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #ccc;
    }

    .fuse-modal-header h2 {
        margin: 0;
    }

    .fuse-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
    }

    .fuse-modal-body {
        padding: 20px;
    }

    .fuse-modal-footer {
        padding: 20px;
        border-top: 1px solid #ccc;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    #invoice-line-items {
        margin-bottom: 20px;
    }

    .invoice-line-item {
        display: grid;
        grid-template-columns: 2fr 1fr 40px;
        gap: 10px;
        margin-bottom: 10px;
        align-items: flex-end;
    }

    .invoice-line-item input {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .invoice-line-item-remove {
        background: #d32f2f;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        padding: 6px;
    }
</style>
