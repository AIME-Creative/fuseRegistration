<?php
/**
 * Admin Dashboard Template
 * Shows overview statistics and quick navigation
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Fuse 2026 Registration Dashboard</h1>

    <div id="fuse-dashboard-content">
        <!-- Stats Grid -->
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

        </div>

        <!-- Quick Links -->
        <div class="fuse-section">
            <h2>Quick Links</h2>
            <div class="fuse-quick-links">
                <a href="?page=fuse-reg-list" class="button button-primary">View All Registrations</a>
                <a href="?page=fuse-reg-add" class="button button-secondary">Add Manual Registration</a>
                <a href="?page=fuse-reg-export" class="button button-secondary">Export Data</a>
                <a href="?page=fuse-reg-settings" class="button button-secondary">Settings</a>
            </div>
        </div>
    </div>
</div>

<style>
    .fuse-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin: 20px 0;
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
        padding: 20px 16px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.07);
    }

    /* Accent colours by card type */
    .fuse-stat-card--primary   { border-top-color: #0073aa; }
    .fuse-stat-card--member    { border-top-color: #2e7d32; }
    .fuse-stat-card--vip       { border-top-color: #6a1b9a; }
    .fuse-stat-card--purchased { border-top-color: #e65100; }
    .fuse-stat-card--guest     { border-top-color: #00838f; }
    .fuse-stat-card--vip-guest { border-top-color: #8e24aa; }
    .fuse-stat-card--hoa       { border-top-color: #c62828; }
    .fuse-stat-card--wmn       { border-top-color: #ad1457; }

    .stat-value {
        font-size: 36px;
        font-weight: 700;
        color: #23282d;
        margin-bottom: 8px;
        line-height: 1;
    }

    .stat-label {
        font-size: 13px;
        color: #666;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .fuse-section {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
    }

    .fuse-section h2 {
        margin-top: 0;
    }

    .fuse-quick-links {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 15px;
    }

    .fuse-quick-links .button {
        text-decoration: none;
    }
</style>
