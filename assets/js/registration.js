/**
 * Fuse 2026 Registration Form JavaScript
 *
 * Handles multi-step registration flow:
 * - Member verification
 * - Ticket selection
 * - Attendee details
 * - Payment processing
 */

(function($) {
    'use strict';

    // State management
    var state = {
        step: 1,
        email: '',
        isMember: false,
        memberData: null, // {tier, member_type, first_name, last_name, ...}
        selectedTicket: null, // {type, label, priceCents, purchaseType}
        addons: { hallOfAime: false, wmn: false, vipLuncheon: false, vettedVa: false },
        addonPrices: { hallOfAime: 0, vipLuncheon: 0, vettedVa: 0 },
        guests: [],
        guestPrice: 0,
        totalCents: 0
    };

    // DOM elements cache
    var $form = null;
    var $emailInput = null;
    var $checkMemberBtn = null;
    var $memberCheckResult = null;

    /**
     * Initialize the form when document is ready
     */
    function initForm() {
        $form = $('#fuse-registration-form');
        $emailInput = $('#fuse-email');
        $checkMemberBtn = $('#btn-check-member');
        $memberCheckResult = $('#member-check-result');

        if (!$form.length) {
            return;
        }

        // Bind event handlers
        bindEventHandlers();

        // Non-member mode: skip step 1 entirely — go straight to ticket selection
        if (fuseReg.nonMemberMode) {
            state.isMember   = false;
            state.memberData = null;
            renderTicketOptions();  // renders non-member ticket cards
            goToStep(2);            // step 2 is "Select Ticket" in the non-member template
        }
    }

    /**
     * Bind all event handlers for the form
     */
    function bindEventHandlers() {
        // Step 1: Email verification
        $checkMemberBtn.on('click', handleCheckMember);
        $(document).on('click', '.member-check-proceed-btn', handleCheckMemberProceed);
        $(document).on('click', '.fuse-check-another-link', handleCheckAnotherEmail);

        // Progress bar: click completed steps to go back
        $(document).on('click', '.fuse-progress-step--clickable', handleProgressStepClick);

        // Step navigation
        $(document).on('click', '#btn-step-next-2', function() {
            if (validateStep2()) {
                goToStep(3);
            }
        });
        $(document).on('click', '#btn-step-back-2', function() {
            goToStep(1);
        });

        $(document).on('click', '#btn-step-next-3', function() {
            if (validateStep3()) {
                goToStep(4);
            }
        });
        $(document).on('click', '#btn-step-back-3', function() {
            goToStep(2);
        });

        $(document).on('click', '#btn-step-back-4', function() {
            goToStep(3);
        });

        // Step 4: Submit
        $(document).on('click', '#btn-submit-registration', handleSubmitRegistration);

        // Ticket selection
        $(document).on('change', 'input[name="ticket_selection"]', handleTicketSelection);

        // Add-on toggles
        $(document).on('change', 'input[name="addon_hall_of_aime"]', handleAddonChange);
        $(document).on('change', 'input[name="addon_wmn"]', handleAddonChange);
        $(document).on('change', 'input[name="addon_vip_luncheon"]', handleAddonChange);
        $(document).on('change', 'input[name="addon_vetted_va"]', handleAddonChange);

        // Guest button
        $(document).on('click', '#btn-add-guest', addGuestField);

        // Guest field changes — use both 'input' and 'change' so state stays live
        $(document).on('input change', '#guest-list .guest-first-name, #guest-list .guest-last-name, #guest-list .guest-email, #guest-list .guest-phone', updateGuestState);

        // Guest addon checkboxes
        $(document).on('change', '#guest-list .guest-hoa, #guest-list .guest-wmn, #guest-list .guest-vip-luncheon, #guest-list .guest-vetted-va', updateGuestState);

        // Guest remove button
        $(document).on('click', '#guest-list .guest-remove-btn', function(e) {
            e.preventDefault();
            $(this).closest('.fuse-guest-item').remove();
            refreshGuestCardHeaders();
            updateGuestState();
        });
    }

    /**
     * Check if email is a member
     */
    function handleCheckMember() {
        var email = $.trim($emailInput.val());

        // Clear previous error
        $('#email-error').empty();
        $memberCheckResult.empty();

        // Validate email
        if (!email || !isValidEmail(email)) {
            $('#email-error').text('Please enter a valid email address.');
            return;
        }

        // Show loading state
        showCheckMemberLoading(true);

        // AJAX call to check membership
        $.ajax({
            url: fuseReg.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'fuse_check_member',
                email: email,
                nonce: fuseReg.nonce
            },
            success: function(response) {
                handleCheckMemberResponse(response, email);
            },
            error: function() {
                showCheckMemberLoading(false);
                showCheckMemberError('An error occurred. Please try again.');
            }
        });
    }

    /**
     * Handle the response from member check AJAX call
     */
    function handleCheckMemberResponse(response, email) {
        showCheckMemberLoading(false);

        // wp_send_json_error() path — covers non-members, already registered, API errors
        if (!response.success) {
            var errData = response.data || {};

            // Already registered / already claimed — stop here, no proceed button
            if (errData.already_registered) {
                showCheckMemberAlreadyRegistered(errData.message);
                return;
            }

            // Not a member (or inactive) — show message but let them continue as non-member
            showCheckMemberNotMember(errData.message);
            state.isMember = false;
            state.email = email;
            state.memberData = null;
            renderTicketOptions();
            return;
        }

        var data = response.data;

        // Safety net: if somehow is_member is false in a success response, treat as non-member
        if (!data || !data.is_member) {
            showCheckMemberNotMember(data && data.message ? data.message : null);
            state.isMember = false;
            state.email = email;
            state.memberData = null;
            renderTicketOptions();
            return;
        }

        // Active member found
        state.isMember = true;
        state.email = email;
        state.memberData = data;

        showCheckMemberSuccess(data);
        renderTicketOptions();
    }

    /**
     * Show member found success message
     */
    function showCheckMemberSuccess(memberData) {
        var html = '<div class="fuse-member-check-success">';
        html += '<p class="fuse-member-check-welcome"><span class="fuse-verified-icon">✓</span> Welcome back, ' + escapeHtml(memberData.first_name || 'Member') + '!</p>';
        html += '<p class="fuse-member-check-tier">Member Tier: <strong>' + escapeHtml(memberData.tier || 'Member') + '</strong></p>';
        html += '<div class="fuse-member-check-actions">';
        html += '<button type="button" class="fuse-btn fuse-btn--primary member-check-proceed-btn">Continue to Tickets</button>';
        html += '<a href="#" class="fuse-check-another-link">Use a different email address</a>';
        html += '</div>';
        html += '</div>';

        $memberCheckResult.html(html);

        // Hide the "Check Membership" button — same as non-member path
        $checkMemberBtn.hide();

        // Pre-fill attendee details form fields
        if (memberData.first_name) $('#fuse-first-name').val(memberData.first_name);
        if (memberData.last_name)  $('#fuse-last-name').val(memberData.last_name);
        if (memberData.company)    $('#fuse-company').val(memberData.company);
        if (memberData.phone)      $('#fuse-phone').val(memberData.phone);
        if (memberData.gender)     $('#fuse-gender').val(memberData.gender);

        // Pre-fill and mark the verified email field
        prefillEmailField(memberData.email || state.email);
    }

    /**
     * Pre-fill the locked email field on step 3 with the verified address
     */
    function prefillEmailField(email) {
        var $emailField = $('#fuse-email-readonly');
        $emailField.val(email);
        // Swap placeholder text for the verified badge
        $emailField.closest('.fuse-field-group')
            .find('.fuse-field-helper')
            .addClass('fuse-email-verified')
            .html('<span class="fuse-verified-icon">✓</span> Verified email address');
    }

    /**
     * Show not a member message (with optional custom message from Supabase)
     */
    function showCheckMemberNotMember(customMessage) {
        var msg = customMessage || 'No active membership found for this email.';
        var html = '<div class="fuse-member-check-not-member">';
        html += '<p class="fuse-member-check-welcome"><span class="fuse-warning-icon">!</span> ' + escapeHtml(msg) + '</p>';
        html += '<p class="fuse-member-check-subtext">You can still register as a non-member at the standard rate.</p>';
        html += '<p class="fuse-member-check-subtext">If you need any help, please contact <a href="mailto:brokermembership@aimegroup.com">brokermembership@aimegroup.com</a>.</p>';
        html += '<div class="fuse-member-check-actions">';
        html += '<button type="button" class="fuse-btn fuse-btn--primary member-check-proceed-btn">Continue as Non-Member</button>';
        html += '<a href="#" class="fuse-check-another-link">Check another email address</a>';
        html += '</div>';
        html += '</div>';

        $memberCheckResult.html(html);
        $checkMemberBtn.hide();
        prefillEmailField(state.email);
    }

    /**
     * Show already registered / already claimed message
     */
    function showCheckMemberAlreadyRegistered(customMessage) {
        var msg = customMessage || 'This email is already registered for Fuse 2026.';
        var html = '<div class="fuse-member-check-error">';
        html += '<p class="fuse-member-check-welcome"><span class="fuse-verified-icon">✓</span> ' + escapeHtml(msg) + '</p>';
        html += '<p class="fuse-member-check-subtext">If you need to make changes, please contact <a href="mailto:brokermembership@aimegroup.com">brokermembership@aimegroup.com</a>.</p>';
        html += '<div class="fuse-member-check-actions">';
        html += '<a href="#" class="fuse-check-another-link">Check another email address</a>';
        html += '</div>';
        html += '</div>';

        $memberCheckResult.html(html);
        $checkMemberBtn.hide();
    }

    /**
     * Show member check error
     */
    function showCheckMemberError(message) {
        $('#email-error').text(message);
    }

    /**
     * Show/hide loading spinner for member check
     */
    function showCheckMemberLoading(isLoading) {
        var $spinner = $checkMemberBtn.find('.fuse-btn-spinner');
        var $text = $checkMemberBtn.find('.fuse-btn-text');

        if (isLoading) {
            $spinner.show();
            $text.hide();
            $checkMemberBtn.prop('disabled', true);
        } else {
            $spinner.hide();
            $text.show();
            $checkMemberBtn.prop('disabled', false);
        }
    }

    /**
     * Handle "Proceed" button from member check result
     */
    function handleCheckMemberProceed() {
        goToStep(2);
    }

    /**
     * Handle "Check another email address" link — resets step 1 fully
     */
    function handleCheckAnotherEmail(e) {
        e.preventDefault();

        // Clear state
        state.email = '';
        state.isMember = false;
        state.memberData = null;

        // Clear email input and result area
        $emailInput.val('').focus();
        $memberCheckResult.empty();
        $('#email-error').empty();

        // Show the Check Membership button again
        $checkMemberBtn.show();
    }

    /**
     * Render ticket options based on membership status
     */
    function renderTicketOptions() {
        var $ticketContainer = $('#ticket-options');
        var html = '';

        if (state.isMember && state.memberData) {
            var tier = state.memberData.tier;

            if (tier === 'VIP') {
                // VIP: $0 VIP ticket (still goes through Stripe as a $0 invoice)
                html += createTicketCard({
                    value: 'vip',
                    label: 'VIP Ticket',
                    description: 'Complimentary for VIP members',
                    price: '$0',
                    priceCents: 0,
                    purchaseType: 'claimed',
                    badge: 'INCLUDED'
                });

            } else if (tier === 'Premium' || tier === 'Elite') {
                // Premium/Elite: $0 GA (still goes through Stripe as a $0 invoice)
                html += createTicketCard({
                    value: 'general_admission',
                    label: 'General Admission',
                    description: 'Complimentary for ' + tier + ' members',
                    price: '$0',
                    priceCents: 0,
                    purchaseType: 'claimed',
                    badge: 'INCLUDED'
                });
            }

        } else {
            // Non-member: GA Early Bird / Regular
            var earlyBirdPrice = fuseReg.pricing.ga_early_bird;
            var regularPrice   = fuseReg.pricing.ga_regular;

            if (fuseReg.isEarlyBird) {
                html += createTicketCard({
                    value: 'general_admission',
                    label: 'General Admission - Early Bird',
                    description: 'Limited time offer',
                    price: formatPrice(earlyBirdPrice),
                    priceCents: earlyBirdPrice,
                    purchaseType: 'purchased',
                    badge: 'EARLY BIRD'
                });
            } else {
                html += createTicketCard({
                    value: 'general_admission',
                    label: 'General Admission',
                    description: 'Standard price',
                    price: formatPrice(regularPrice),
                    priceCents: regularPrice,
                    purchaseType: 'purchased'
                });
            }
        }

        // Guest price — set BEFORE renderAddonOptions() which calls updateSummary()
        // VIP first guest is free ($0); all other guests pay $349.
        state.guestPrice = fuseReg.pricing.guest_regular || 34900;

        $ticketContainer.html(html);
        renderAddonOptions();

        // Auto-select the first (and usually only) ticket card
        var $firstRadio = $ticketContainer.find('input[type="radio"]:not(:disabled)').first();
        if ($firstRadio.length) {
            $firstRadio.prop('checked', true).trigger('change');
        }
    }

    /**
     * Create a ticket card HTML element.
     * Wrapped in a <label> so clicking anywhere on the card selects the radio.
     */
    function createTicketCard(options) {
        var isDisabled = !!options.disabled;
        var disabledClass = isDisabled ? ' fuse-ticket-card--disabled' : '';
        var badge = options.badge ? '<span class="fuse-ticket-badge">' + escapeHtml(options.badge) + '</span>' : '';
        var radioId = 'ticket-' + escapeHtml(options.value);

        // Outer label makes the whole card clickable
        var html = '<label for="' + radioId + '" class="fuse-ticket-card' + disabledClass + '">';

        html += '<input type="radio" id="' + radioId + '" name="ticket_selection" ';
        html += 'value="' + escapeHtml(options.value) + '" ';
        html += 'class="fuse-ticket-radio" ';
        html += 'data-price-cents="' + (options.priceCents || 0) + '" ';
        html += 'data-purchase-type="' + escapeHtml(options.purchaseType || '') + '"';
        if (isDisabled) html += ' disabled';
        html += '>';

        html += '<div class="fuse-ticket-content">';
        html += '<div class="fuse-ticket-header">';
        html += '<h4 class="fuse-ticket-label">' + escapeHtml(options.label) + '</h4>';
        html += badge;
        html += '</div>';

        if (options.description) {
            html += '<p class="fuse-ticket-description">' + escapeHtml(options.description) + '</p>';
        }

        html += '<div class="fuse-ticket-price">' + escapeHtml(options.price) + '</div>';

        if (options.note) {
            html += '<p class="fuse-ticket-note">' + escapeHtml(options.note) + '</p>';
        }

        html += '</div>';
        html += '</label>'; // close label, not div

        return html;
    }

    /**
     * Render add-on options
     */
    function renderAddonOptions() {
        var $addonContainer = $('#addon-options');
        var html = '';

        // Hall of AIME add-on
        var isVip = state.isMember && state.memberData && state.memberData.tier === 'VIP';
        var isPremiumElite = state.isMember && state.memberData &&
                             (state.memberData.tier === 'Premium' || state.memberData.tier === 'Elite');
        var hoaPrice;
        if (isVip) {
            hoaPrice = 0;
        } else if (isPremiumElite) {
            hoaPrice = fuseReg.pricing.hoa_member || 19900;
        } else {
            hoaPrice = fuseReg.isEarlyBird
                ? (fuseReg.pricing.hoa_nonmember_early || 29900)
                : (fuseReg.pricing.hoa_nonmember || 34900);
        }

        var hoaLabel = isVip ? 'Included' : formatPrice(hoaPrice);

        if (isVip) {
            // Free for VIP — auto-checked, disabled
            html += '<div class="fuse-addon-item">';
            html += '<input type="checkbox" id="addon-hoa" name="addon_hall_of_aime" class="fuse-addon-checkbox" disabled checked>';
            html += '<label for="addon-hoa" class="fuse-addon-label">';
            html += '<span class="fuse-addon-name">Hall of AIME</span>';
            html += '<span class="fuse-addon-price">' + hoaLabel + '</span>';
            html += '</label>';
            html += '</div>';
        } else {
            html += '<div class="fuse-addon-item">';
            html += '<input type="checkbox" id="addon-hoa" name="addon_hall_of_aime" class="fuse-addon-checkbox" data-price-cents="' + hoaPrice + '">';
            html += '<label for="addon-hoa" class="fuse-addon-label">';
            html += '<span class="fuse-addon-name">Hall of AIME' + (isPremiumElite ? ' (Member)' : '') + '</span>';
            html += '<span class="fuse-addon-price">+ ' + hoaLabel + '</span>';
            html += '</label>';
            html += '</div>';
        }

        // WMN at Fuse (free for all)
        html += '<div class="fuse-addon-item">';
        html += '<input type="checkbox" id="addon-wmn" name="addon_wmn" class="fuse-addon-checkbox" data-price-cents="0">';
        html += '<label for="addon-wmn" class="fuse-addon-label">';
        html += '<span class="fuse-addon-name">WMN at Fuse</span>';
        html += '<span class="fuse-addon-price">FREE</span>';
        html += '<span class="fuse-addon-note">(Women only)</span>';
        html += '</label>';
        html += '</div>';

        // VIP Luncheon — VIP members only
        if (isVip) {
            var vipLuncheonPrice = parseInt(fuseReg.pricing && fuseReg.pricing.vip_luncheon, 10) || 0;
            var vipLuncheonLabel = vipLuncheonPrice > 0 ? formatPrice(vipLuncheonPrice) : 'FREE';
            html += '<div class="fuse-addon-item">';
            html += '<input type="checkbox" id="addon-vip-luncheon" name="addon_vip_luncheon" class="fuse-addon-checkbox" data-price-cents="' + vipLuncheonPrice + '">';
            html += '<label for="addon-vip-luncheon" class="fuse-addon-label">';
            html += '<span class="fuse-addon-name">VIP Luncheon</span>';
            html += '<span class="fuse-addon-price">' + (vipLuncheonPrice > 0 ? '+ ' + vipLuncheonLabel : vipLuncheonLabel) + '</span>';
            html += '</label>';
            html += '</div>';
        }

        // Vetted VA
        var vettedVaPrice = parseInt(fuseReg.pricing && fuseReg.pricing.vetted_va, 10) || 0;
        var vettedVaLabel = vettedVaPrice > 0 ? formatPrice(vettedVaPrice) : 'FREE';
        html += '<div class="fuse-addon-item">';
        html += '<input type="checkbox" id="addon-vetted-va" name="addon_vetted_va" class="fuse-addon-checkbox" data-price-cents="' + vettedVaPrice + '">';
        html += '<label for="addon-vetted-va" class="fuse-addon-label">';
        html += '<span class="fuse-addon-name">Vetted VA</span>';
        html += '<span class="fuse-addon-price">' + (vettedVaPrice > 0 ? '+ ' + vettedVaLabel : vettedVaLabel) + '</span>';
        html += '</label>';
        html += '</div>';

        $addonContainer.html(html);

        // VIP members always get Hall of AIME — set state to match the checked+disabled UI
        if (state.isMember && state.memberData && state.memberData.tier === 'VIP') {
            state.addons.hallOfAime = true;
            state.addonPrices.hallOfAime = 0; // included, no charge
        }

        updateSummary();
    }

    /**
     * Handle ticket selection change
     */
    function handleTicketSelection(e) {
        var $radio = $(e.target);
        var ticketValue = $radio.val();
        var priceCents = parseInt($radio.data('price-cents')) || 0;
        var purchaseType = $radio.data('purchase-type');

        // Get ticket label from card
        var $card = $radio.closest('.fuse-ticket-card');
        var ticketLabel = $card.find('.fuse-ticket-label').text();

        state.selectedTicket = {
            type: ticketValue,
            label: ticketLabel,
            priceCents: priceCents,
            purchaseType: purchaseType
        };

        updateSummary();
    }

    /**
     * Handle add-on changes
     */
    function handleAddonChange(e) {
        var $checkbox = $(e.target);
        var addonName = $checkbox.attr('name');
        var priceCents = parseInt($checkbox.data('price-cents')) || 0;
        var isChecked = $checkbox.is(':checked');

        if (addonName === 'addon_hall_of_aime') {
            state.addons.hallOfAime = isChecked;
            state.addonPrices.hallOfAime = isChecked ? priceCents : 0;
        } else if (addonName === 'addon_wmn') {
            state.addons.wmn = isChecked;
        } else if (addonName === 'addon_vip_luncheon') {
            state.addons.vipLuncheon = isChecked;
            state.addonPrices.vipLuncheon = isChecked ? priceCents : 0;
        } else if (addonName === 'addon_vetted_va') {
            state.addons.vettedVa = isChecked;
            state.addonPrices.vettedVa = isChecked ? priceCents : 0;
        }

        // Mirror addon availability to all guest cards
        syncGuestAddonVisibility();
        updateSummary();
    }

    /**
     * Show/hide guest addon rows based on whether the main attendee has selected each addon.
     * Unchecks guest addons that become unavailable so they don't sneak into the order.
     */
    function syncGuestAddonVisibility() {
        var isVip = state.isMember && state.memberData && state.memberData.tier === 'VIP';
        var showHoa   = !!state.addons.hallOfAime;
        var showWmn   = !!state.addons.wmn;
        var showLunch = isVip && !!state.addons.vipLuncheon; // VIP only
        var showVa    = !!state.addons.vettedVa;

        $('#guest-list .fuse-guest-item').each(function(i) {
            var isVipGuest0 = state.isMember && state.memberData &&
                              state.memberData.tier === 'VIP' && i === 0;
            var $hoaRow   = $(this).find('.fuse-guest-addon-row--hoa');
            var $wmnRow   = $(this).find('.fuse-guest-addon-row--wmn');
            var $lunchRow = $(this).find('.fuse-guest-addon-row--lunch');
            var $vaRow    = $(this).find('.fuse-guest-addon-row--va');

            // VIP guest 0 HOA is always included — never hide it
            if (!isVipGuest0) {
                $hoaRow.toggleClass('fuse-guest-addon-row--hidden', !showHoa);
                if (!showHoa) $hoaRow.find('.guest-hoa').prop('checked', false);
            }

            $wmnRow.toggleClass('fuse-guest-addon-row--hidden', !showWmn);
            if (!showWmn) $wmnRow.find('.guest-wmn').prop('checked', false);

            $lunchRow.toggleClass('fuse-guest-addon-row--hidden', !showLunch);
            if (!showLunch) $lunchRow.find('.guest-vip-luncheon').prop('checked', false);

            $vaRow.toggleClass('fuse-guest-addon-row--hidden', !showVa);
            if (!showVa) $vaRow.find('.guest-vetted-va').prop('checked', false);

            // Hide the whole section when nothing is available
            var anyVisible = (isVipGuest0 || showHoa) || showWmn || showLunch || showVa;
            $(this).find('.fuse-guest-addons').toggleClass('fuse-guest-addons--hidden', !anyVisible);
        });

        // Refresh summary so unchecked items drop out of the total
        updateGuestState();
    }

    /**
     * Get the display price for a guest at a given index (0-based).
     * VIP: guest 0 is FREE (includes HOA), guests 1+ pay member rate.
     */
    function getGuestPriceDisplay(index) {
        var isVip = state.isMember && state.memberData && state.memberData.tier === 'VIP';
        if (isVip && index === 0) {
            return 'FREE';
        }
        var price = parseInt(state.guestPrice, 10) || 0;
        return price === 0 ? 'FREE' : '$' + (price / 100).toFixed(0);
    }

    /**
     * Re-render the header label and price on every guest card.
     * Called after removing a guest so numbering and pricing stay correct.
     */
    function refreshGuestCardHeaders() {
        $('#guest-list .fuse-guest-item').each(function(i) {
            var isVip = state.isMember && state.memberData && state.memberData.tier === 'VIP';
            var label = (isVip && i === 0) ? 'Guest 1 (Included)' : 'Guest ' + (i + 1);
            $(this).find('.fuse-guest-item-label').text(label);
            $(this).find('.fuse-guest-item-price').text(getGuestPriceDisplay(i));
            $(this).attr('data-index', i);
        });
    }

    /**
     * Add a new guest field row
     */
    function addGuestField() {
        var $guestList = $('#guest-list');
        var guestIndex = $guestList.find('.fuse-guest-item').length;
        var isVip = state.isMember && state.memberData && state.memberData.tier === 'VIP';
        var guestNum = guestIndex + 1;
        var priceDisplay = getGuestPriceDisplay(guestIndex);
        var guestLabel = (isVip && guestIndex === 0) ? 'Guest 1 (Included)' : 'Guest ' + guestNum;

        // HOA price for guests is always non-member rate (no member discount)
        var guestHoaCents = fuseReg.isEarlyBird
            ? (parseInt(fuseReg.pricing && fuseReg.pricing.hoa_nonmember_early, 10) || 29900)
            : (parseInt(fuseReg.pricing && fuseReg.pricing.hoa_nonmember, 10) || 34900);
        var guestHoaStr = '+$' + (guestHoaCents / 100).toFixed(0);

        var html = '<div class="fuse-guest-item" data-index="' + guestIndex + '">';

        // Card header with guest label + price
        html += '<div class="fuse-guest-item-header">';
        html += '<span class="fuse-guest-item-label">' + guestLabel + '</span>';
        html += '<span class="fuse-guest-item-price">' + priceDisplay + '</span>';
        html += '</div>';

        // Card body — fields
        html += '<div class="fuse-guest-item-body">';

        // Row 1: First Name + Last Name
        html += '<div class="fuse-form-row">';
        html += '<div class="fuse-field-group">';
        html += '<label class="fuse-field-label">First Name <span class="fuse-required">*</span></label>';
        html += '<input type="text" class="fuse-field-input guest-first-name" placeholder="First name" required>';
        html += '</div>';
        html += '<div class="fuse-field-group">';
        html += '<label class="fuse-field-label">Last Name <span class="fuse-required">*</span></label>';
        html += '<input type="text" class="fuse-field-input guest-last-name" placeholder="Last name" required>';
        html += '</div>';
        html += '</div>';

        // Row 2: Email + Phone
        html += '<div class="fuse-form-row">';
        html += '<div class="fuse-field-group">';
        html += '<label class="fuse-field-label">Email <span class="fuse-required">*</span></label>';
        html += '<input type="email" class="fuse-field-input guest-email" placeholder="Email" required>';
        html += '</div>';
        html += '<div class="fuse-field-group">';
        html += '<label class="fuse-field-label">Phone <span class="fuse-required">*</span></label>';
        html += '<input type="tel" class="fuse-field-input guest-phone" placeholder="Phone" required>';
        html += '</div>';
        html += '</div>';

        // Row 3: Optional add-ons for this guest (only for addons the main attendee selected)
        var hoaAvailable   = !!(state.addons.hallOfAime) || (isVip && guestIndex === 0);
        var wmnAvailable   = !!(state.addons.wmn);
        var lunchAvailable = isVip && !!(state.addons.vipLuncheon); // VIP only
        var vaAvailable    = !!(state.addons.vettedVa);
        var addonsVisible  = hoaAvailable || wmnAvailable || lunchAvailable || vaAvailable;
        var addonsHiddenClass = addonsVisible ? '' : ' fuse-guest-addons--hidden';
        html += '<div class="fuse-guest-addons' + addonsHiddenClass + '">';
        html += '<div class="fuse-guest-addons-title">Add-ons for this guest</div>';

        // Hall of AIME — VIP guest 0 always included; others only shown when main attendee has it
        if (isVip && guestIndex === 0) {
            html += '<label class="fuse-guest-addon-row fuse-guest-addon-row--hoa fuse-guest-addon-row--included">';
            html += '<input type="checkbox" class="guest-hoa" checked disabled>';
            html += '<span class="fuse-guest-addon-name">Hall of AIME</span>';
            html += '<span class="fuse-guest-addon-price fuse-guest-addon-price--free">Included</span>';
            html += '</label>';
        } else {
            var hoaHiddenClass = state.addons.hallOfAime ? '' : ' fuse-guest-addon-row--hidden';
            html += '<label class="fuse-guest-addon-row fuse-guest-addon-row--hoa' + hoaHiddenClass + '">';
            html += '<input type="checkbox" class="guest-hoa">';
            html += '<span class="fuse-guest-addon-name">Hall of AIME</span>';
            html += '<span class="fuse-guest-addon-price">' + guestHoaStr + '</span>';
            html += '</label>';
        }

        // WMN at Fuse — only shown when main attendee has it selected
        var wmnHiddenClass = state.addons.wmn ? '' : ' fuse-guest-addon-row--hidden';
        html += '<label class="fuse-guest-addon-row fuse-guest-addon-row--wmn' + wmnHiddenClass + '">';
        html += '<input type="checkbox" class="guest-wmn">';
        html += '<span class="fuse-guest-addon-name">WMN at Fuse</span>';
        html += '<span class="fuse-guest-addon-price fuse-guest-addon-price--free">FREE</span>';
        html += '</label>';

        // VIP Luncheon — only shown for VIP members when main attendee has it selected
        if (isVip) {
            var lunchPrice = parseInt(fuseReg.pricing && fuseReg.pricing.vip_luncheon, 10) || 0;
            var lunchStr = lunchPrice > 0 ? '+$' + (lunchPrice / 100).toFixed(0) : 'FREE';
            var lunchHiddenClass = state.addons.vipLuncheon ? '' : ' fuse-guest-addon-row--hidden';
            html += '<label class="fuse-guest-addon-row fuse-guest-addon-row--lunch' + lunchHiddenClass + '">';
            html += '<input type="checkbox" class="guest-vip-luncheon">';
            html += '<span class="fuse-guest-addon-name">VIP Luncheon</span>';
            html += '<span class="fuse-guest-addon-price">' + lunchStr + '</span>';
            html += '</label>';
        }

        // Vetted VA — only shown when main attendee has it selected
        var vaPrice = parseInt(fuseReg.pricing && fuseReg.pricing.vetted_va, 10) || 0;
        var vaStr = vaPrice > 0 ? '+$' + (vaPrice / 100).toFixed(0) : 'FREE';
        var vaHiddenClass = state.addons.vettedVa ? '' : ' fuse-guest-addon-row--hidden';
        html += '<label class="fuse-guest-addon-row fuse-guest-addon-row--va' + vaHiddenClass + '">';
        html += '<input type="checkbox" class="guest-vetted-va">';
        html += '<span class="fuse-guest-addon-name">Vetted VA</span>';
        html += '<span class="fuse-guest-addon-price">' + vaStr + '</span>';
        html += '</label>';

        html += '</div>'; // .fuse-guest-addons

        html += '</div>'; // .fuse-guest-item-body

        // Remove button
        html += '<div class="fuse-guest-item-actions">';
        html += '<button type="button" class="fuse-btn fuse-btn--danger fuse-btn--small guest-remove-btn">Remove Guest</button>';
        html += '</div>';

        html += '</div>'; // .fuse-guest-item

        $guestList.append(html);
    }

    /**
     * Update guest state from form rows
     */
    function updateGuestState() {
        state.guests = [];
        $('#guest-list .fuse-guest-item').each(function() {
            var firstName = $.trim($(this).find('.guest-first-name').val());
            var lastName  = $.trim($(this).find('.guest-last-name').val());
            // Fallback: if the old single .guest-name field exists (shouldn't happen, but safe)
            var legacyName = $.trim($(this).find('.guest-name').val());
            var name = firstName || legacyName
                ? $.trim((firstName + ' ' + lastName).trim()) || legacyName
                : '';
            var email  = $.trim($(this).find('.guest-email').val());
            var phone  = $.trim($(this).find('.guest-phone').val());
            var hasHoa      = $(this).find('.guest-hoa').is(':checked');
            var hasWmn      = $(this).find('.guest-wmn').is(':checked');
            var hasLunch    = $(this).find('.guest-vip-luncheon').is(':checked');
            var hasVa       = $(this).find('.guest-vetted-va').is(':checked');

            if (name) { // Only add if at least first name is filled
                state.guests.push({
                    name: name,
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                    phone: phone,
                    hasHoa: hasHoa,
                    hasWmn: hasWmn,
                    hasVipLuncheon: hasLunch,
                    hasVettedVa: hasVa
                });
            }
        });
        updateSummary();
    }

    /**
     * Update order summary
     */
    function updateSummary() {
        var summaryItems = [];
        var totalCents = 0;

        // Add main ticket
        if (state.selectedTicket) {
            summaryItems.push({
                label: state.selectedTicket.label,
                price: formatPrice(state.selectedTicket.priceCents)
            });
            totalCents += state.selectedTicket.priceCents;
        }

        // Add add-ons (always show, even at $0)
        if (state.addons.hallOfAime) {
            var hoaCost = state.addonPrices.hallOfAime || 0;
            summaryItems.push({
                label: 'Hall of AIME',
                price: hoaCost > 0 ? formatPrice(hoaCost) : 'FREE'
            });
            totalCents += hoaCost;
        }

        if (state.addons.wmn) {
            summaryItems.push({
                label: 'WMN at Fuse',
                price: 'FREE'
            });
        }

        if (state.addons.vipLuncheon) {
            var lunchCost = state.addonPrices.vipLuncheon || 0;
            summaryItems.push({
                label: 'VIP Luncheon',
                price: lunchCost > 0 ? formatPrice(lunchCost) : 'FREE'
            });
            totalCents += lunchCost;
        }

        if (state.addons.vettedVa) {
            var vaCost = state.addonPrices.vettedVa || 0;
            summaryItems.push({
                label: 'Vetted VA',
                price: vaCost > 0 ? formatPrice(vaCost) : 'FREE'
            });
            totalCents += vaCost;
        }

        // Add guest tickets + per-guest add-ons
        if (state.guests && state.guests.length > 0) {
            var isVipSummary = state.isMember && state.memberData && state.memberData.tier === 'VIP';
            var safeGuestPrice = parseInt(state.guestPrice, 10) || 0;
            // HOA for guests is always non-member rate
            var guestHoaCents = fuseReg.isEarlyBird
                ? (parseInt(fuseReg.pricing && fuseReg.pricing.hoa_nonmember_early, 10) || 29900)
                : (parseInt(fuseReg.pricing && fuseReg.pricing.hoa_nonmember, 10) || 34900);
            var guestLunchCents = parseInt(fuseReg.pricing && fuseReg.pricing.vip_luncheon, 10) || 0;
            var guestVaCents    = parseInt(fuseReg.pricing && fuseReg.pricing.vetted_va, 10) || 0;

            state.guests.forEach(function(guest, index) {
                // Determine ticket cost
                var guestCost, priceDisplay;
                if (isVipSummary && index === 0) {
                    guestCost = 0;
                    priceDisplay = 'FREE';
                } else {
                    guestCost = safeGuestPrice;
                    priceDisplay = safeGuestPrice === 0 ? 'FREE' : formatPrice(safeGuestPrice);
                }
                var guestLabel = (isVipSummary && index === 0)
                    ? 'Guest 1 + Hall of AIME: ' + escapeHtml(guest.name || 'Guest')
                    : 'Guest ' + (index + 1) + ': ' + escapeHtml(guest.name || 'Guest');
                summaryItems.push({ label: guestLabel, price: priceDisplay });
                totalCents += guestCost;

                // HOA add-on for this guest — non-member rate; VIP guest 0 already has it included
                if (guest.hasHoa && !(isVipSummary && index === 0)) {
                    summaryItems.push({
                        label: 'Hall of AIME (' + escapeHtml(guest.name || 'Guest ' + (index + 1)) + ')',
                        price: formatPrice(guestHoaCents)
                    });
                    totalCents += guestHoaCents;
                }

                // WMN add-on for this guest — always free
                if (guest.hasWmn) {
                    summaryItems.push({
                        label: 'WMN at Fuse (' + escapeHtml(guest.name || 'Guest ' + (index + 1)) + ')',
                        price: 'FREE'
                    });
                }

                // VIP Luncheon add-on for this guest
                if (guest.hasVipLuncheon) {
                    summaryItems.push({
                        label: 'VIP Luncheon (' + escapeHtml(guest.name || 'Guest ' + (index + 1)) + ')',
                        price: guestLunchCents > 0 ? formatPrice(guestLunchCents) : 'FREE'
                    });
                    totalCents += guestLunchCents;
                }

                // Vetted VA add-on for this guest
                if (guest.hasVettedVa) {
                    summaryItems.push({
                        label: 'Vetted VA (' + escapeHtml(guest.name || 'Guest ' + (index + 1)) + ')',
                        price: guestVaCents > 0 ? formatPrice(guestVaCents) : 'FREE'
                    });
                    totalCents += guestVaCents;
                }
            });
        }

        state.totalCents = totalCents;

        // Update all summary sections
        updateSummarySections(summaryItems, totalCents);
    }

    /**
     * Update summary sections on all steps
     */
    function updateSummarySections(summaryItems, totalCents) {
        var summarySteps = [2, 3, 4];

        summarySteps.forEach(function(step) {
            // Step 2 uses #summary-items / #summary-total (no number suffix)
            // Steps 3 & 4 use #summary-items-3 / #summary-items-4
            var suffix = step === 2 ? '' : '-' + step;
            var $summaryItems = $('#summary-items' + suffix);
            var $summaryTotal = $('#summary-total' + suffix);
            var html = '';

            if (summaryItems.length === 0) {
                html = '<div class="fuse-summary-item fuse-summary-item--empty"><span>No items selected yet</span></div>';
            } else {
                summaryItems.forEach(function(item) {
                    var isFree = item.price === 'FREE' || item.price === '$0.00';
                    var priceClass = isFree ? 'fuse-summary-price fuse-summary-price--free' : 'fuse-summary-price';
                    html += '<div class="fuse-summary-item">';
                    html += '<span class="fuse-summary-label">' + escapeHtml(item.label) + ':</span>';
                    html += '<span class="' + priceClass + '">' + escapeHtml(item.price) + '</span>';
                    html += '</div>';
                });
            }

            if ($summaryItems.length) {
                $summaryItems.html(html);
            }

            // Update total
            var totalFormatted = formatPrice(totalCents);
            if ($summaryTotal.length) {
                $summaryTotal.find('.fuse-amount').text(totalFormatted);
            }

            // Update payment note on step 4
            if (step === 4) {
                var $paymentNote = $('#payment-note');
                var $submitBtn = $('#btn-submit-registration');
                var $submitBtnText = $('#submit-btn-text');

                if (totalCents === 0) {
                    $paymentNote.text('You will be redirected to confirm your registration').show();
                    $submitBtnText.text('Complete Registration');
                } else {
                    $paymentNote.text('You will be redirected to secure payment').show();
                    $submitBtnText.text('Proceed to Payment');
                }
            }
        });
    }

    /**
     * Validate step 2 (ticket selection)
     */
    function validateStep2() {
        if (!state.selectedTicket) {
            alert('Please select a ticket type.');
            return false;
        }

        // Validate guests if any are added
        var isValid = true;
        $('#guest-list .fuse-guest-item').each(function() {
            var firstName = $.trim($(this).find('.guest-first-name').val());
            var lastName  = $.trim($(this).find('.guest-last-name').val());
            var email     = $.trim($(this).find('.guest-email').val());
            var phone     = $.trim($(this).find('.guest-phone').val());
            if (!firstName || !lastName) {
                alert('Please fill in the first and last name for all guests.');
                isValid = false;
                return false;
            }
            if (!email) {
                alert('Please fill in the email address for all guests.');
                isValid = false;
                return false;
            }
            if (!phone) {
                alert('Please fill in the phone number for all guests.');
                isValid = false;
                return false;
            }
        });

        if (!isValid) {
            return false;
        }

        // Update guest state before submission
        updateGuestState();

        return true;
    }

    /**
     * Validate step 3 (attendee details)
     */
    function validateStep3() {
        var firstName = $.trim($('#fuse-first-name').val());
        var lastName  = $.trim($('#fuse-last-name').val());

        // In non-member mode email comes from the editable field on this step
        var email;
        if (fuseReg.nonMemberMode) {
            email = $.trim($('#fuse-email-nonmember').val());
            $('#email-nonmember-error').empty();
            if (!email || !isValidEmail(email)) {
                $('#email-nonmember-error').text('Please enter a valid email address.');
                $('#fuse-email-nonmember').focus();
                return false;
            }
            state.email = email;
        } else {
            email = state.email; // verified in step 1
        }

        var phone   = $.trim($('#fuse-phone').val());
        var company = $.trim($('#fuse-company').val());

        if (!firstName || !lastName || !email) {
            alert('Please fill in all required fields (First Name, Last Name, Email).');
            return false;
        }
        if (!phone) {
            alert('Please enter your phone number.');
            $('#fuse-phone').focus();
            return false;
        }
        if (!company) {
            alert('Please enter your company name.');
            $('#fuse-company').focus();
            return false;
        }

        // Update state with form values
        state.firstName = firstName;
        state.lastName  = lastName;
        state.preferredName    = $.trim($('#fuse-preferred-name').val());
        state.phone            = phone;
        state.company          = company;
        state.gender           = $.trim($('#fuse-gender').val());
        state.marketingConsent = $('#fuse-marketing-consent').is(':checked');

        return true;
    }

    /**
     * Handle form submission
     */
    function handleSubmitRegistration() {
        var $btn = $('#btn-submit-registration');
        var $spinner = $btn.find('.fuse-btn-spinner');
        var $text = $btn.find('.fuse-btn-text');

        // Refresh guest state and recalculate total before submission.
        updateGuestState();

        // Show loading
        $spinner.show();
        $text.hide();
        $btn.prop('disabled', true);

        // ALL registrations go through Stripe (even $0) so every registration gets an invoice.
        createCheckout($btn, $spinner, $text);
    }

    /**
     * Get a reCAPTCHA v3 token, then call callback(token).
     * If reCAPTCHA is not configured, calls callback with an empty string.
     */
    function withRecaptchaToken(action, callback) {
        var siteKey = fuseReg.recaptchaSiteKey || '';
        if (!siteKey || typeof grecaptcha === 'undefined') {
            callback('');
            return;
        }
        grecaptcha.ready(function() {
            grecaptcha.execute(siteKey, { action: action }).then(function(token) {
                callback(token);
            }).catch(function() {
                callback('');
            });
        });
    }

    /**
     * Submit free registration via AJAX
     */
    function submitFreeRegistration($btn, $spinner, $text) {
        // Ensure guests array is fresh from the DOM right before we read it
        updateGuestState();
        withRecaptchaToken('submit_registration', function(recaptchaToken) {
        var formData = {
            action: 'fuse_submit_registration',
            nonce: fuseReg.nonce,
            recaptcha_token: recaptchaToken,
            email: state.email,
            first_name: state.firstName,
            last_name: state.lastName,
            preferred_name: state.preferredName || '',
            phone: state.phone || '',
            company: state.company || '',
            gender: state.gender || '',
            marketing_consent: state.marketingConsent ? 1 : 0,
            ticket_type: state.selectedTicket.type,
            tier: state.isMember && state.memberData ? state.memberData.tier : null,
            purchase_type: state.selectedTicket.purchaseType,
            has_hall_of_aime: state.addons.hallOfAime ? 1 : 0,
            has_wmn_at_fuse: state.addons.wmn ? 1 : 0
        };

        // Add guest info for backward compatibility (first guest only)
        if (state.guests && state.guests.length > 0) {
            formData.guest_name = state.guests[0].name || '';
            formData.guest_email = state.guests[0].email || '';
            formData.guest_phone = state.guests[0].phone || '';
            formData.guests_json = JSON.stringify(state.guests);
        }

        $.ajax({
            url: fuseReg.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(response) {
                $spinner.hide();
                $text.show();
                $btn.prop('disabled', false);

                if (response.success) {
                    showSuccessMessage();
                } else {
                    showErrorMessage(response.data && response.data.message ? response.data.message : 'An error occurred.');
                }
            },
            error: function() {
                $spinner.hide();
                $text.show();
                $btn.prop('disabled', false);
                showErrorMessage('An error occurred. Please try again.');
            }
        });
        }); // end withRecaptchaToken
    }

    /**
     * Create Stripe checkout session via AJAX
     */
    function createCheckout($btn, $spinner, $text) {
        // Ensure guests array is fresh from the DOM right before we read it
        updateGuestState();

        var items = [];
        var priceIds = fuseReg.priceIds || {};
        var isVip = state.isMember && state.memberData && state.memberData.tier === 'VIP';
        var isPremiumElite = state.isMember && state.memberData &&
                             (state.memberData.tier === 'Premium' || state.memberData.tier === 'Elite');

        // ── Main ticket (always included, even at $0) ──
        if (state.selectedTicket) {
            var ticketPriceId = '';
            if (state.selectedTicket.type === 'vip') {
                ticketPriceId = priceIds.vip || '';
            } else if (state.selectedTicket.type === 'general_admission') {
                if (isPremiumElite) {
                    ticketPriceId = priceIds.ga_member || '';
                } else {
                    ticketPriceId = fuseReg.isEarlyBird
                        ? (priceIds.ga_early_bird || '')
                        : (priceIds.ga || '');
                }
            }
            items.push({
                type: state.selectedTicket.type,
                label: state.selectedTicket.label,
                price_cents: state.selectedTicket.priceCents,
                price_id: ticketPriceId
            });
        }

        // ── Hall of AIME add-on (included at $0 for VIP) ──
        if (state.addons.hallOfAime) {
            var hoaPriceId, hoaCents;
            if (isVip) {
                hoaPriceId = priceIds.hoa_vip || '';
                hoaCents   = 0;
            } else if (isPremiumElite) {
                hoaPriceId = priceIds.hoa_member || '';
                hoaCents   = fuseReg.pricing.hoa_member || 19900;
            } else {
                hoaPriceId = fuseReg.isEarlyBird
                    ? (priceIds.hoa_nonmember_early || '')
                    : (priceIds.hoa_nonmember || '');
                hoaCents = fuseReg.isEarlyBird
                    ? (fuseReg.pricing.hoa_nonmember_early || 29900)
                    : (fuseReg.pricing.hoa_nonmember || 34900);
            }
            items.push({
                type: 'hall_of_aime',
                label: 'Hall of AIME',
                price_cents: hoaCents,
                price_id: hoaPriceId
            });
        }

        // ── WMN at Fuse add-on ($0 always) ──
        if (state.addons.wmn) {
            items.push({
                type: 'wmn_at_fuse',
                label: 'WMN at Fuse',
                price_cents: 0,
                price_id: priceIds.wmn || ''
            });
        }

        // ── VIP Luncheon add-on ──
        if (state.addons.vipLuncheon) {
            items.push({
                type: 'vip_luncheon',
                label: 'VIP Luncheon',
                price_cents: state.addonPrices.vipLuncheon || 0,
                price_id: priceIds.vip_luncheon || ''
            });
        }

        // ── Vetted VA add-on ──
        if (state.addons.vettedVa) {
            items.push({
                type: 'vetted_va',
                label: 'Vetted VA',
                price_cents: state.addonPrices.vettedVa || 0,
                price_id: priceIds.vetted_va || ''
            });
        }

        // ── Guest tickets ──
        // VIP guest 0: free ($0) VIP guest price + free HOA for that guest.
        // VIP guest 1+: member rate ($199).
        // Premium/Elite: member rate. Non-members: early-bird or regular.
        if (state.guests && state.guests.length > 0) {
            var guestPriceId = priceIds.guest_regular || '';

            // HOA price for guests is always non-member rate
            var guestHoaPriceId = fuseReg.isEarlyBird
                ? (priceIds.hoa_nonmember_early || '')
                : (priceIds.hoa_nonmember || '');
            var guestHoaCents = fuseReg.isEarlyBird
                ? (parseInt(fuseReg.pricing.hoa_nonmember_early, 10) || 29900)
                : (parseInt(fuseReg.pricing.hoa_nonmember, 10) || 34900);

            state.guests.forEach(function(guest, index) {
                var guestCents, guestPriceId, guestLabel;

                if (isVip && index === 0) {
                    // VIP first guest: $0 included ticket
                    guestCents   = 0;
                    guestPriceId = priceIds.guest_vip || '';
                    guestLabel   = 'VIP Guest (Included)';
                } else {
                    // All other guests: $349 regular price
                    guestCents   = state.guestPrice;
                    guestPriceId = guestPriceId;
                    guestLabel   = 'Guest Ticket';
                }

                if (guest.name) guestLabel += ' (' + guest.name + ')';
                items.push({
                    type: 'guest',
                    label: guestLabel,
                    price_cents: guestCents,
                    price_id: guestPriceId
                });

                // VIP first guest always gets a free HOA line item (included)
                if (isVip && index === 0) {
                    items.push({
                        type: 'hall_of_aime_guest',
                        label: 'Hall of AIME (VIP Guest, Included)',
                        price_cents: 0,
                        price_id: priceIds.hoa_vip || ''
                    });
                }

                // Optional HOA for this guest — non-member rate, never for VIP guest 0
                if (guest.hasHoa && !(isVip && index === 0)) {
                    items.push({
                        type: 'hall_of_aime_guest',
                        label: 'Hall of AIME (' + (guest.name || 'Guest') + ')',
                        price_cents: guestHoaCents,
                        price_id: guestHoaPriceId
                    });
                }

                // Optional WMN at Fuse for this guest — always $0
                if (guest.hasWmn) {
                    items.push({
                        type: 'wmn_at_fuse_guest',
                        label: 'WMN at Fuse (' + (guest.name || 'Guest') + ')',
                        price_cents: 0,
                        price_id: priceIds.wmn || ''
                    });
                }

                // Optional VIP Luncheon for this guest
                if (guest.hasVipLuncheon) {
                    items.push({
                        type: 'vip_luncheon_guest',
                        label: 'VIP Luncheon (' + (guest.name || 'Guest') + ')',
                        price_cents: parseInt(fuseReg.pricing && fuseReg.pricing.vip_luncheon, 10) || 0,
                        price_id: priceIds.vip_luncheon || ''
                    });
                }

                // Optional Vetted VA for this guest
                if (guest.hasVettedVa) {
                    items.push({
                        type: 'vetted_va_guest',
                        label: 'Vetted VA (' + (guest.name || 'Guest') + ')',
                        price_cents: parseInt(fuseReg.pricing && fuseReg.pricing.vetted_va, 10) || 0,
                        price_id: priceIds.vetted_va || ''
                    });
                }
            });
        }

        // Debug: log items being sent to PHP/Stripe
        console.log('[Fuse] createCheckout items (' + items.length + '):', JSON.stringify(items, null, 2));
        console.log('[Fuse] state.guests (' + (state.guests ? state.guests.length : 0) + '):', JSON.stringify(state.guests, null, 2));
        console.log('[Fuse] priceIds:', JSON.stringify(fuseReg.priceIds, null, 2));

        withRecaptchaToken('create_checkout', function(recaptchaToken) {
        var formData = {
            action: 'fuse_create_checkout',
            nonce: fuseReg.nonce,
            recaptcha_token: recaptchaToken,
            email: state.email,
            first_name: state.firstName,
            last_name: state.lastName,
            preferred_name: state.preferredName || '',
            phone: state.phone || '',
            company: state.company || '',
            gender: state.gender || '',
            marketing_consent: state.marketingConsent ? 1 : 0,
            ticket_type: state.selectedTicket.type,
            tier: state.isMember && state.memberData ? state.memberData.tier : null,
            purchase_type: state.selectedTicket.purchaseType,
            has_hall_of_aime: state.addons.hallOfAime ? 1 : 0,
            has_wmn_at_fuse: state.addons.wmn ? 1 : 0,
            has_vip_luncheon: state.addons.vipLuncheon ? 1 : 0,
            has_vetted_va: state.addons.vettedVa ? 1 : 0,
            // Send price fields separately so PHP can build Stripe line items
            price_cents: state.selectedTicket.priceCents || 0,
            addon_hoa_cents: state.addonPrices.hallOfAime || 0,
            items: JSON.stringify(items)
        };

        // Add guest info for backward compatibility (first guest only)
        if (state.guests && state.guests.length > 0) {
            formData.guest_name = state.guests[0].name || '';
            formData.guest_email = state.guests[0].email || '';
            formData.guest_phone = state.guests[0].phone || '';
            formData.guests_json = JSON.stringify(state.guests);
        }

        $.ajax({
            url: fuseReg.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(response) {
                $spinner.hide();
                $text.show();
                $btn.prop('disabled', false);

                if (response.success && response.data && response.data.checkout_url) {
                    // Redirect to Stripe checkout
                    window.location.href = response.data.checkout_url;
                } else {
                    showErrorMessage(response.data && response.data.message ? response.data.message : 'Failed to create checkout session.');
                }
            },
            error: function() {
                $spinner.hide();
                $text.show();
                $btn.prop('disabled', false);
                showErrorMessage('An error occurred. Please try again.');
            }
        });
        }); // end withRecaptchaToken
    }

    /**
     * Show success message and hide form
     */
    function showSuccessMessage() {
        $form.hide();
        $('#success-message').show();
    }

    /**
     * Show error message
     */
    function showErrorMessage(message) {
        var $notice = $('#form-notice');
        $notice.html('<div class="fuse-form-error">' + escapeHtml(message) + '</div>').show();
        $(window).scrollTop(0);
    }

    /**
     * Navigate to a specific step
     */
    function goToStep(stepNumber) {
        var previousStep = state.step;
        state.step = stepNumber;

        // Update progress bar: active + completed states
        $('.fuse-progress-step').each(function() {
            var s = parseInt($(this).data('step'));
            $(this)
                .removeClass('fuse-progress-step--active fuse-progress-step--completed fuse-progress-step--clickable')
                .removeAttr('title');

            if (s === stepNumber) {
                $(this).addClass('fuse-progress-step--active');
            } else if (s < stepNumber) {
                // Past steps: completed + clickable to go back
                $(this).addClass('fuse-progress-step--completed fuse-progress-step--clickable');
                $(this).attr('title', 'Go back to step ' + s);
            }
            // Future steps: no special class, stays faded
        });

        // Show only the active step content
        $('.fuse-reg-step').removeClass('fuse-reg-step--active');
        $('.fuse-reg-step[data-step="' + stepNumber + '"]').addClass('fuse-reg-step--active');

        // When arriving at step 3, populate the email field
        if (stepNumber === 3) {
            if (fuseReg.nonMemberMode) {
                // Non-member mode: email is entered by the user here; pre-fill if already captured
                if (state.email) {
                    $('#fuse-email-nonmember').val(state.email);
                }
            } else if (state.email) {
                prefillEmailField(state.email);
            }
        }

        // Populate confirmation screen when reaching step 4
        if (stepNumber === 4) {
            updateConfirmation();
            updateSummary(); // ensure summary totals are fresh
        }

        // Scroll to top of form
        var $wrapper = $('.fuse-reg-wrapper');
        if ($wrapper.length) {
            $('html, body').animate({ scrollTop: $wrapper.offset().top - 20 }, 200);
        }
    }

    /**
     * Handle clicks on completed (past) progress steps to navigate back
     */
    function handleProgressStepClick() {
        var $step = $(this);
        if (!$step.hasClass('fuse-progress-step--clickable')) return;

        var targetStep = parseInt($step.data('step'));
        if (targetStep < state.step) {
            goToStep(targetStep);
        }
    }

    /**
     * Update confirmation section on step 4
     */
    function updateConfirmation() {
        var fullName = state.firstName + ' ' + state.lastName;
        if (state.preferredName) {
            fullName += ' (' + state.preferredName + ')';
        }

        $('#confirm-name').text(fullName);
        $('#confirm-email').text(state.email);
        $('#confirm-company').text(state.company || '-');
        $('#confirm-phone').text(state.phone || '-');

        if (state.selectedTicket) {
            $('#confirm-ticket').text(state.selectedTicket.label);
        }

        var addons = [];
        if (state.addons.hallOfAime) {
            addons.push('Hall of AIME');
        }
        if (state.addons.wmn) {
            addons.push('WMN at Fuse');
        }
        $('#confirm-addons').text(addons.length > 0 ? addons.join(', ') : 'None');
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        var pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return pattern.test(email);
    }

    /**
     * Format price from cents to dollars
     * @param {number} cents
     * @return {string} Formatted price like "$1,234.56"
     */
    function formatPrice(cents) {
        if (cents === 0) {
            return '$0.00';
        }

        var dollars = (cents / 100).toFixed(2);
        var parts = dollars.split('.');
        var intPart = parts[0];
        var decPart = parts[1];

        // Add comma separators
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        return '$' + intPart + '.' + decPart;
    }

    /**
     * Escape HTML special characters
     */
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) {
            return map[m];
        });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initForm();
    });

})(jQuery);
