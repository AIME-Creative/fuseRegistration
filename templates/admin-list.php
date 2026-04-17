<?php
/**
 * Admin Registration List Template
 * Shows paginated list of all registrations with search, filters, and inline edit
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Fuse 2026 Registrations</h1>
    <a href="?page=fuse-reg-add" class="page-title-action">Add New Registration</a>
    <hr class="wp-header-end">

    <!-- Dashboard Stats -->
    <div id="fuse-dashboard-content">
        <div class="fuse-stats-grid">

            <!-- Row 1: Primary ticket counts -->
            <div class="fuse-stat-card fuse-stat-card--primary">
                <div class="stat-value" id="stat-total-attendees">-</div>
                <div class="stat-label">Total Attendees</div>
            </div>
            <div class="fuse-stat-card fuse-stat-card--member">
                <div class="stat-value" id="stat-premium-elite">-</div>
                <div class="stat-label">Premium &amp; Elite</div>
            </div>
            <div class="fuse-stat-card fuse-stat-card--vip">
                <div class="stat-value" id="stat-vip-claimed">-</div>
                <div class="stat-label">VIP</div>
            </div>
            <div class="fuse-stat-card fuse-stat-card--purchased">
                <div class="stat-value" id="stat-purchased">-</div>
                <div class="stat-label">Purchased Tickets</div>
            </div>

            <!-- Row 2: Guest counts -->
            <div class="fuse-stat-card fuse-stat-card--guest">
                <div class="stat-value" id="stat-ga-guests">-</div>
                <div class="stat-label">General Admission Guests</div>
            </div>
            <div class="fuse-stat-card fuse-stat-card--vip-guest">
                <div class="stat-value" id="stat-vip-guests">-</div>
                <div class="stat-label">VIP Guests</div>
            </div>

            <!-- Row 3: Add-ons -->
            <div class="fuse-stat-card fuse-stat-card--hoa">
                <div class="stat-value" id="stat-hall-of-aime">-</div>
                <div class="stat-label">Hall of AIME</div>
            </div>
            <div class="fuse-stat-card fuse-stat-card--wmn">
                <div class="stat-value" id="stat-wmn-at-fuse">-</div>
                <div class="stat-label">WMN at Fuse</div>
            </div>
            <div class="fuse-stat-card fuse-stat-card--lunch">
                <div class="stat-value" id="stat-vip-luncheon">-</div>
                <div class="stat-label">VIP Luncheon</div>
            </div>
            <div class="fuse-stat-card fuse-stat-card--va">
                <div class="stat-value" id="stat-vetted-va">-</div>
                <div class="stat-label">Vetted VA</div>
            </div>

        </div>
    </div>

    <div id="fuse-registrations-content">
        <!-- Search and Filters -->
        <div class="fuse-controls">
            <div class="fuse-search">
                <input type="text" id="fuse-search-box" placeholder="Search by name, email, or company..." value="">
                <span class="spinner" id="fuse-search-spinner"></span>
            </div>

            <div class="fuse-filters">
                <select id="fuse-filter-ticket-type">
                    <option value="">All Ticket Types</option>
                    <option value="general_admission">General Admission</option>
                    <option value="vip">VIP</option>
                </select>

                <select id="fuse-filter-tier">
                    <option value="">All Tiers</option>
                    <option value="Premium">Premium</option>
                    <option value="Elite">Elite</option>
                    <option value="VIP">VIP</option>
                    <option value="">Non-Member</option>
                </select>

                <select id="fuse-filter-purchase-type">
                    <option value="">All Purchase Types</option>
                    <option value="pending">Pending</option>
                    <option value="purchased">Purchased</option>
                    <option value="claimed">Claimed</option>
                </select>
            </div>

            <div class="fuse-export-buttons">
                <button class="button button-secondary" id="fuse-export-csv">Export CSV</button>
                <button class="button button-secondary" id="fuse-export-json">Export JSON</button>
            </div>
        </div>

        <!-- Registrations Table -->
        <div class="fuse-table-container">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Ticket Type</th>
                        <th>Tier</th>
                        <th>Purchase Type</th>
                        <th>Hall of AIME</th>
                        <th>WMN</th>
                        <th>VIP Luncheon</th>
                        <th>Vetted VA</th>
                        <th>Guests</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="fuse-registrations-tbody">
                    <tr><td colspan="13" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="fuse-pagination">
            <button class="button" id="fuse-pagination-prev">Previous</button>
            <span id="fuse-pagination-info">Page 1</span>
            <button class="button" id="fuse-pagination-next">Next</button>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="fuse-edit-modal" class="fuse-modal" style="display: none;">
        <div class="fuse-modal-content">
            <div class="fuse-modal-header">
                <h2>Edit Registration</h2>
                <button type="button" class="fuse-modal-close">&times;</button>
            </div>
            <div class="fuse-modal-body">
                <form id="fuse-edit-form">
                    <input type="hidden" id="edit-registration-id" name="registration_id" value="">

                    <div class="fuse-form-row">
                        <div class="fuse-form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" id="edit-first_name" required>
                        </div>
                        <div class="fuse-form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" id="edit-last_name" required>
                        </div>
                    </div>

                    <div class="fuse-form-group">
                        <label>Preferred Name</label>
                        <input type="text" name="preferred_name" id="edit-preferred_name">
                    </div>

                    <div class="fuse-form-row">
                        <div class="fuse-form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="edit-email" required>
                        </div>
                        <div class="fuse-form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" id="edit-phone">
                        </div>
                    </div>

                    <div class="fuse-form-group">
                        <label>Company</label>
                        <input type="text" name="company" id="edit-company">
                    </div>

                    <div class="fuse-form-row">
                        <div class="fuse-form-group">
                            <label>Ticket Type</label>
                            <select name="ticket_type" id="edit-ticket_type" required>
                                <option value="">Select...</option>
                                <option value="general_admission">General Admission</option>
                                <option value="vip">VIP</option>
                            </select>
                        </div>
                        <div class="fuse-form-group">
                            <label>Tier</label>
                            <select name="tier" id="edit-tier">
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
                            <select name="purchase_type" id="edit-purchase_type">
                                <option value="">Select...</option>
                                <option value="pending">Pending (Invoice Sent)</option>
                                <option value="purchased">Purchased</option>
                                <option value="claimed">Claimed</option>
                            </select>
                        </div>
                        <div class="fuse-form-group">
                            <label>Gender</label>
                            <select name="gender" id="edit-gender">
                                <option value="">Select an option</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Non-binary">Non-binary</option>
                                <option value="Prefer not to say">Prefer not to say</option>
                            </select>
                        </div>
                    </div>

                    <div class="fuse-form-row">
                        <div class="fuse-form-group">
                            <label>
                                <input type="checkbox" name="hall_of_aime" id="edit-hall_of_aime" value="1">
                                Hall of AIME
                            </label>
                        </div>
                        <div class="fuse-form-group">
                            <label>
                                <input type="checkbox" name="wmn_at_fuse" id="edit-wmn_at_fuse" value="1">
                                WMN at Fuse
                            </label>
                        </div>
                        <div class="fuse-form-group">
                            <label>
                                <input type="checkbox" name="vip_luncheon" id="edit-vip_luncheon" value="1">
                                VIP Luncheon
                            </label>
                        </div>
                        <div class="fuse-form-group">
                            <label>
                                <input type="checkbox" name="vetted_va" id="edit-vetted_va" value="1">
                                Vetted VA
                            </label>
                        </div>
                    </div>

                    <div class="fuse-form-group">
                        <label>Fuse Attendance</label>
                        <input type="text" name="fuse_attendance" id="edit-fuse_attendance">
                    </div>

                    <div class="fuse-form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="edit-notes" rows="4"></textarea>
                    </div>

                    <div id="edit-guests-section">
                        <h3>Guests</h3>
                        <div id="edit-guests-list"></div>
                        <button type="button" class="button button-secondary" id="edit-add-guest">Add Guest</button>
                    </div>
                </form>
            </div>
            <div class="fuse-modal-footer">
                <button type="button" class="button button-secondary fuse-modal-close">Cancel</button>
                <button type="button" class="button button-primary" id="fuse-save-registration">Save Changes</button>
                <button type="button" class="button button-secondary" id="fuse-send-invoice-from-edit">Send Invoice</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="fuse-delete-modal" class="fuse-modal" style="display: none;">
        <div class="fuse-modal-content" style="max-width:420px;">
            <div class="fuse-modal-header">
                <h2>Delete Registration</h2>
                <button type="button" class="fuse-modal-close">&times;</button>
            </div>
            <div class="fuse-modal-body">
                <p>Are you sure you want to permanently delete the registration for <strong id="fuse-delete-name"></strong>? This cannot be undone.</p>
            </div>
            <div class="fuse-modal-footer">
                <button type="button" class="button button-secondary fuse-modal-close">Cancel</button>
                <button type="button" class="button" id="fuse-delete-confirm-btn" style="background:#d32f2f;color:#fff;border-color:#b71c1c;">Delete Permanently</button>
            </div>
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
    .fuse-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin: 16px 0 20px;
    }

    @media (max-width: 1200px) {
        .fuse-stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 600px) {
        .fuse-stats-grid { grid-template-columns: 1fr; }
    }

    .fuse-stat-card {
        background: #fff;
        border: 1px solid #ddd;
        border-top: 4px solid #0073aa;
        border-radius: 4px;
        padding: 16px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.07);
    }

    .fuse-stat-card--primary   { border-top-color: #0073aa; }
    .fuse-stat-card--member    { border-top-color: #2e7d32; }
    .fuse-stat-card--vip       { border-top-color: #6a1b9a; }
    .fuse-stat-card--purchased { border-top-color: #e65100; }
    .fuse-stat-card--guest     { border-top-color: #00838f; }
    .fuse-stat-card--vip-guest { border-top-color: #8e24aa; }
    .fuse-stat-card--hoa       { border-top-color: #c62828; }
    .fuse-stat-card--wmn       { border-top-color: #ad1457; }
    .fuse-stat-card--lunch     { border-top-color: #e65100; }
    .fuse-stat-card--va        { border-top-color: #1565c0; }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #23282d;
        margin-bottom: 6px;
        line-height: 1;
    }

    .stat-label {
        font-size: 12px;
        color: #666;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    /* ── Guest sub-rows ── */
    .fuse-guest-row td {
        background: #f9f9f9;
        border-top: none !important;
        font-size: 13px;
        color: #555;
    }

    .fuse-guest-indicator {
        color: #aaa;
        font-size: 14px;
    }

    .fuse-guest-label {
        display: inline-block;
        font-size: 11px;
        color: #888;
        background: #efefef;
        border-radius: 10px;
        padding: 1px 7px;
        margin-left: 6px;
    }

    /* ── Badges ── */
    .fuse-badge {
        display: inline-block;
        padding: 3px 9px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.02em;
        white-space: nowrap;
        line-height: 1.5;
    }

    /* Ticket type */
    .fuse-badge--ticket-general_admission       { background: #e8f4fd; color: #1565c0; }
    .fuse-badge--ticket-vip                     { background: #f3e5f5; color: #6a1b9a; }
    .fuse-badge--ticket-unknown                 { background: #f5f5f5; color: #757575; }

    /* Tier */
    .fuse-badge--tier-none      { background: #fafafa; color: #9e9e9e; border: 1px solid #e0e0e0; }
    .fuse-badge--tier-premium   { background: #fff8e1; color: #e65100; }
    .fuse-badge--tier-elite     { background: #fce4ec; color: #880e4f; }
    .fuse-badge--tier-vip       { background: #ede7f6; color: #4527a0; }

    /* Purchase type */
    .fuse-badge--purchase-claimed    { background: #e0f2f1; color: #00695c; }
    .fuse-badge--purchase-purchased  { background: #e3f2fd; color: #0277bd; }

    .fuse-controls {
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .fuse-search {
        flex: 1;
        min-width: 300px;
        position: relative;
    }

    .fuse-search input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .fuse-search .spinner {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        visibility: hidden;
    }

    .fuse-search .spinner.active {
        visibility: visible;
    }

    .fuse-filters {
        display: flex;
        gap: 10px;
    }

    .fuse-filters select {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    .fuse-export-buttons {
        display: flex;
        gap: 10px;
    }

    .fuse-table-container {
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .wp-list-table {
        margin: 0;
    }

    .wp-list-table tbody tr {
        cursor: pointer;
    }

    .wp-list-table tbody tr:hover {
        background-color: #f5f5f5;
    }

    .text-center {
        text-align: center;
    }

    .fuse-pagination {
        display: flex;
        gap: 10px;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 4px;
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

    /* Edit modal — centered with dark overlay, wider than default */
    #fuse-edit-modal .fuse-modal-content {
        max-width: 860px;
        width: 92%;
        max-height: 88vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        box-shadow: 0 8px 32px rgba(0,0,0,0.28);
        border-radius: 6px;
    }
    #fuse-edit-modal .fuse-modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 28px 30px;
    }

    /* Invoice modal sits above everything including the edit modal */
    #fuse-invoice-modal {
        z-index: 100000;
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

    .fuse-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .fuse-form-group {
        margin-bottom: 15px;
    }

    .fuse-form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }

    .fuse-form-group input,
    .fuse-form-group select,
    .fuse-form-group textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: inherit;
    }

    .fuse-form-group input[type="checkbox"] {
        width: auto;
        margin-right: 5px;
    }

    #edit-guests-section {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    #edit-guests-list {
        margin-bottom: 15px;
    }

    .guest-item {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .guest-item-remove {
        float: right;
        cursor: pointer;
        color: #d32f2f;
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
