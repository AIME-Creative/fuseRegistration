/**
 * Fuse 2026 Registration - Admin JavaScript
 * Handles dashboard, registration list, add registration, and export functionality
 */

(function($) {
    'use strict';

    const FuseAdmin = {
        // Configuration
        config: {
            ajaxUrl: typeof fuseAdmin !== 'undefined' ? fuseAdmin.ajaxUrl : '/wp-admin/admin-ajax.php',
            nonce: typeof fuseAdmin !== 'undefined' ? fuseAdmin.nonce : '',
            registrationsPerPage: 25,
            searchDebounceTime: 300
        },

        // State
        state: {
            currentPage: 1,
            totalPages: 1,
            searchTerm: '',
            filters: {
                ticketType: '',
                tier: '',
                purchaseType: ''
            },
            allRegistrations: [],
            currentRegistration: null,
            invoiceData: {
                registrationId: null,
                lineItems: []
            }
        },

        // ==================== INITIALIZATION ====================

        init: function() {
            // Detect page by body attribute OR by which container element exists
            let currentPage = document.body.getAttribute('data-fuse-page');
            if (!currentPage) {
                if ($('#fuse-add-registration-content').length) currentPage = 'add-registration';
                else if ($('#fuse-registrations-content').length) currentPage = 'registrations';
                else if ($('#fuse-export-content').length) currentPage = 'export';
                else if ($('#fuse-dashboard-content').length) currentPage = 'dashboard';
            }

            if (currentPage === 'dashboard') {
                this.initDashboard();
            } else if (currentPage === 'registrations') {
                this.initDashboard();
                this.initRegistrationList();
            } else if (currentPage === 'add-registration') {
                this.initAddRegistration();
            } else if (currentPage === 'export') {
                this.initExport();
            }

            // Common modal functionality
            this.initModals();
        },

        // Snapshot edit form values so we can detect unsaved changes
        _editFormSnapshot: null,

        _snapshotEditForm: function() {
            this._editFormSnapshot = $('#fuse-edit-form').serialize();
        },

        _editFormIsDirty: function() {
            return this._editFormSnapshot !== null &&
                   $('#fuse-edit-form').serialize() !== this._editFormSnapshot;
        },

        _tryCloseEditModal: function() {
            if (this._editFormIsDirty()) {
                if (!window.confirm('You have unsaved changes. Close anyway and discard them?')) {
                    return; // user cancelled
                }
            }
            this._editFormSnapshot = null;
            $('#fuse-edit-modal').hide();
        },

        initModals: function() {
            const self = this;

            // ── Edit modal: close button ──────────────────────────────────
            $(document).on('click', '#fuse-edit-modal .fuse-modal-close', function(e) {
                e.preventDefault();
                self._tryCloseEditModal();
            });

            // ── Edit modal: click on dark backdrop closes it ──────────────
            $(document).on('click', '#fuse-edit-modal', function(e) {
                if ($(e.target).is('#fuse-edit-modal')) {
                    self._tryCloseEditModal();
                }
            });

            // ── Other modals: close button ────────────────────────────────
            $(document).on('click', '.fuse-modal-close', function(e) {
                e.preventDefault();
                const $modal = $(this).closest('.fuse-modal');
                if (!$modal.is('#fuse-edit-modal')) {
                    $modal.hide();
                }
            });

            // ── Other modals: click on backdrop ───────────────────────────
            $(document).on('click', '.fuse-modal', function(e) {
                if ($(e.target).hasClass('fuse-modal') && !$(this).is('#fuse-edit-modal')) {
                    $(this).hide();
                }
            });

            // ── Escape key ────────────────────────────────────────────────
            $(document).on('keydown', function(e) {
                if (e.which !== 27) return;
                // Invoice / delete modals close immediately
                if ($('#fuse-invoice-modal').is(':visible')) { $('#fuse-invoice-modal').hide(); return; }
                if ($('#fuse-delete-modal').is(':visible'))  { $('#fuse-delete-modal').hide();  return; }
                // Edit modal respects unsaved-changes guard
                if ($('#fuse-edit-modal').is(':visible'))    { self._tryCloseEditModal();        return; }
            });

            // ── Edit modal: real-time guest addon visibility ───────────────
            $(document).on('change', '#edit-hall_of_aime', function() {
                const show = $(this).is(':checked');
                $('#edit-guests-list .guest-item').each(function() {
                    const $row = $(this).find('.guest-addon-row--hoa');
                    $row.toggle(show);
                    if (!show) $row.find('.guest-hoa').prop('checked', false);
                });
            });
            $(document).on('change', '#edit-wmn_at_fuse', function() {
                const show = $(this).is(':checked');
                $('#edit-guests-list .guest-item').each(function() {
                    const $row = $(this).find('.guest-addon-row--wmn');
                    $row.toggle(show);
                    if (!show) $row.find('.guest-wmn').prop('checked', false);
                });
            });
            $(document).on('change', '#edit-vip_luncheon', function() {
                const show = $(this).is(':checked');
                $('#edit-guests-list .guest-item').each(function() {
                    const $row = $(this).find('.guest-addon-row--lunch');
                    $row.toggle(show);
                    if (!show) $row.find('.guest-vip-luncheon').prop('checked', false);
                });
            });

            // VIP Luncheon is only available when tier is VIP — show/hide in edit modal
            $(document).on('change', '#edit-tier', function() {
                const isVip = ($(this).val() || '').toLowerCase() === 'vip';
                const $lunchGroup = $('#edit-vip_luncheon').closest('.fuse-form-group');
                $lunchGroup.toggle(isVip);
                if (!isVip) {
                    $('#edit-vip_luncheon').prop('checked', false);
                    $('#edit-guests-list .guest-item').each(function() {
                        const $row = $(this).find('.guest-addon-row--lunch');
                        $row.hide().find('.guest-vip-luncheon').prop('checked', false);
                    });
                }
            });
            $(document).on('change', '#edit-vetted_va', function() {
                const show = $(this).is(':checked');
                $('#edit-guests-list .guest-item').each(function() {
                    const $row = $(this).find('.guest-addon-row--va');
                    $row.toggle(show);
                    if (!show) $row.find('.guest-vetted-va').prop('checked', false);
                });
            });

            // Invoice modal — handlers here so they work on every page
            $(document).on('click', '#fuse-send-invoice-confirm', function() {
                self.sendInvoice();
            });

            $(document).on('click', '#invoice-add-line-item', function() {
                self.addInvoiceLineItem();
            });

            $(document).on('click', '.invoice-line-item-remove', function() {
                $(this).closest('.invoice-line-item').remove();
            });
        },

        // ==================== DASHBOARD ====================

        initDashboard: function() {
            const self = this;
            this.loadDashboardStats();
        },

        loadDashboardStats: function() {
            const self = this;

            $.post(this.config.ajaxUrl, {
                action: 'fuse_admin_get_stats',
                nonce: this.config.nonce
            }, function(response) {
                if (response.success) {
                    const s = response.data;
                    const fmt = self.formatNumber.bind(self);

                    // Row 1 — primary ticket totals
                    $('#stat-total-attendees').text(fmt(s.total_attendees  || 0));
                    $('#stat-premium-elite').text(  fmt(s.premium_elite    || 0));
                    $('#stat-vip-claimed').text(    fmt(s.vip_claimed      || 0));
                    $('#stat-purchased').text(      fmt(s.purchased        || 0));

                    // Row 2 — guest breakdown
                    $('#stat-ga-guests').text(      fmt(s.ga_guests        || 0));
                    $('#stat-vip-guests').text(     fmt(s.vip_guests       || 0));

                    // Row 3 — add-ons (primary + guests)
                    $('#stat-hall-of-aime').text(   fmt(s.hall_of_aime     || 0));
                    $('#stat-wmn-at-fuse').text(    fmt(s.wmn_at_fuse      || 0));
                    $('#stat-vip-luncheon').text(   fmt(s.vip_luncheon     || 0));
                    $('#stat-vetted-va').text(      fmt(s.vetted_va        || 0));
                }
            }).fail(function() {
                console.error('Failed to load dashboard stats');
            });
        },

        // ==================== REGISTRATION LIST ====================

        initRegistrationList: function() {
            const self = this;

            // Search with debounce
            let searchTimeout;
            $('#fuse-search-box').on('keyup', function() {
                clearTimeout(searchTimeout);
                const value = $(this).val();

                searchTimeout = setTimeout(function() {
                    self.state.searchTerm = value;
                    self.state.currentPage = 1;
                    self.loadRegistrations();
                }, self.config.searchDebounceTime);
            });

            // Filters
            $('#fuse-filter-ticket-type, #fuse-filter-tier, #fuse-filter-purchase-type').on('change', function() {
                self.state.filters.ticketType = $('#fuse-filter-ticket-type').val();
                self.state.filters.tier = $('#fuse-filter-tier').val();
                self.state.filters.purchaseType = $('#fuse-filter-purchase-type').val();
                self.state.currentPage = 1;
                self.loadRegistrations();
            });

            // Pagination
            $('#fuse-pagination-prev').on('click', function() {
                if (self.state.currentPage > 1) {
                    self.state.currentPage--;
                    self.loadRegistrations();
                }
            });

            $('#fuse-pagination-next').on('click', function() {
                if (self.state.currentPage < self.state.totalPages) {
                    self.state.currentPage++;
                    self.loadRegistrations();
                }
            });

            // Export buttons
            $('#fuse-export-csv').on('click', function() {
                self.exportAsCSV(self.state.allRegistrations);
            });

            $('#fuse-export-json').on('click', function() {
                self.exportAsJSON(self.state.allRegistrations);
            });

            // Edit and invoice buttons
            $(document).on('click', '#fuse-save-registration', function() {
                self.saveRegistration();
            });

            $(document).on('click', '#fuse-send-invoice-from-edit', function() {
                self.openInvoiceModal();
            });

            $(document).on('click', '#edit-add-guest', function(e) {
                e.preventDefault();
                self.addEditGuestField();
            });

            // Initial load
            this.loadRegistrations();
        },

        loadRegistrations: function() {
            const self = this;
            const offset = (this.state.currentPage - 1) * this.config.registrationsPerPage;

            $.post(this.config.ajaxUrl, {
                action: 'fuse_admin_get_registrations',
                nonce: this.config.nonce,
                search: this.state.searchTerm,
                ticket_type: this.state.filters.ticketType,
                tier: this.state.filters.tier,
                purchase_type: this.state.filters.purchaseType,
                offset: offset,
                limit: this.config.registrationsPerPage
            }, function(response) {
                if (response.success) {
                    const data = response.data;
                    self.state.allRegistrations = data.registrations || [];
                    self.state.totalPages = Math.ceil((data.total || 0) / self.config.registrationsPerPage);

                    self.renderRegistrationsTable(self.state.allRegistrations);
                    self.updatePaginationInfo();
                }
            }).fail(function() {
                self.showNotice('Failed to load registrations', 'error');
            });
        },

        renderRegistrationsTable: function(registrations) {
            const self = this;
            const tbody = $('#fuse-registrations-tbody');

            if (!registrations || registrations.length === 0) {
                tbody.html('<tr><td colspan="13" class="text-center">No registrations found</td></tr>');
                return;
            }

            let html = '';
            registrations.forEach(function(reg) {
                const primaryName = self.escapeHtml((reg.first_name || '') + ' ' + (reg.last_name || ''));
                const guests = reg.fuse_registration_guests || [];
                const guestCount = Array.isArray(guests) ? guests.length : (guests[0]?.count || 0);

                html += '<tr class="fuse-registration-row" data-id="' + reg.id + '">';
                html += '<td>' + primaryName + '</td>';
                html += '<td>' + self.escapeHtml(reg.email || '') + '</td>';
                html += '<td>' + self.escapeHtml(reg.company || '') + '</td>';
                html += '<td>' + self.ticketTypeBadge(reg.ticket_type) + '</td>';
                html += '<td>' + self.tierBadge(reg.tier) + '</td>';
                html += '<td>' + self.purchaseTypeBadge(reg.purchase_type) + '</td>';
                html += '<td>' + ((reg.has_hall_of_aime || reg.ticket_type === 'vip') ? '✓' : '') + '</td>';
                html += '<td>' + (reg.has_wmn_at_fuse ? '✓' : '') + '</td>';
                html += '<td>' + (reg.has_vip_luncheon ? '✓' : '') + '</td>';
                html += '<td>' + (reg.has_vetted_va ? '✓' : '') + '</td>';
                html += '<td>' + guestCount + '</td>';
                html += '<td>' + self.formatDate(reg.created_at) + '</td>';
                html += '<td><button class="button fuse-delete-btn" data-id="' + reg.id + '" data-name="' + primaryName + '" title="Delete registration">Delete</button></td>';
                html += '</tr>';

                // Guest sub-rows (only when guests are returned as full objects, not count)
                if (Array.isArray(guests) && guests.length > 0 && guests[0].full_name) {
                    guests.forEach(function(guest) {
                        html += '<tr class="fuse-guest-row">';
                        html += '<td style="padding-left:28px;">' +
                            '<span class="fuse-guest-indicator">↳</span> ' +
                            self.escapeHtml(guest.full_name || '') +
                            ' <span class="fuse-guest-label">Guest of ' + primaryName + '</span>' +
                            '</td>';
                        html += '<td>' + self.escapeHtml(guest.email || '') + '</td>';
                        html += '<td></td>'; // company
                        html += '<td>' + self.ticketTypeBadge(guest.ticket_type) + '</td>';
                        var isVipGuest = guest.ticket_type === 'vip_guest';
                        html += '<td></td>'; // tier
                        html += '<td></td>'; // purchase type
                        html += '<td>' + ((isVipGuest || guest.has_hall_of_aime) ? '✓' : '') + '</td>'; // Hall of AIME
                        html += '<td>' + (guest.has_wmn_at_fuse ? '✓' : '') + '</td>'; // WMN
                        html += '<td>' + (guest.has_vip_luncheon ? '✓' : '') + '</td>'; // VIP Luncheon
                        html += '<td>' + (guest.has_vetted_va ? '✓' : '') + '</td>'; // Vetted VA
                        html += '<td></td>'; // guests
                        html += '<td></td>'; // date
                        html += '<td></td>'; // actions
                        html += '</tr>';
                    });
                }
            });

            tbody.html(html);

            // Row click → edit modal (not triggered by delete button)
            tbody.off('click', '.fuse-registration-row').on('click', '.fuse-registration-row', function(e) {
                if ($(e.target).hasClass('fuse-delete-btn') || $(e.target).closest('.fuse-delete-btn').length) return;
                const regId = $(this).data('id');
                self.loadAndEditRegistration(regId);
            });

            // Delete button
            tbody.off('click', '.fuse-delete-btn').on('click', '.fuse-delete-btn', function(e) {
                e.stopPropagation();
                const regId = $(this).data('id');
                const name  = $(this).data('name');
                self.confirmDeleteRegistration(regId, name);
            });
        },

        updatePaginationInfo: function() {
            const info = 'Page ' + this.state.currentPage + ' of ' + this.state.totalPages;
            $('#fuse-pagination-info').text(info);

            $('#fuse-pagination-prev').prop('disabled', this.state.currentPage === 1);
            $('#fuse-pagination-next').prop('disabled', this.state.currentPage === this.state.totalPages);
        },

        loadAndEditRegistration: function(registrationId) {
            const self = this;

            $.post(this.config.ajaxUrl, {
                action: 'fuse_admin_get_registration',
                nonce: this.config.nonce,
                id: registrationId
            }, function(response) {
                if (response.success) {
                    self.state.currentRegistration = response.data;
                    self.populateEditForm(response.data);

                    // Show edit modal and snapshot form for unsaved-changes detection
                    $('#fuse-edit-modal').show();
                    self._snapshotEditForm();
                }
            }).fail(function() {
                self.showNotice('Failed to load registration', 'error');
            });
        },

        populateEditForm: function(registration) {
            $('#edit-registration-id').val(registration.id);
            $('#edit-first_name').val(registration.first_name);
            $('#edit-last_name').val(registration.last_name);
            $('#edit-preferred_name').val(registration.preferred_name || '');
            $('#edit-email').val(registration.email);
            $('#edit-phone').val(registration.phone || '');
            $('#edit-company').val(registration.company || '');
            $('#edit-gender').val(registration.gender || '');
            $('#edit-ticket_type').val(registration.ticket_type);
            $('#edit-tier').val(registration.tier || '');
            $('#edit-purchase_type').val(registration.purchase_type || '');
            $('#edit-fuse_attendance').val(registration.fuse_attendance || '');
            $('#edit-hall_of_aime').prop('checked', registration.has_hall_of_aime === true || registration.has_hall_of_aime === 1);
            $('#edit-wmn_at_fuse').prop('checked', registration.has_wmn_at_fuse === true || registration.has_wmn_at_fuse === 1);
            $('#edit-vip_luncheon').prop('checked', registration.has_vip_luncheon === true || registration.has_vip_luncheon === 1);
            $('#edit-vetted_va').prop('checked', registration.has_vetted_va === true || registration.has_vetted_va === 1);
            $('#edit-notes').val(registration.notes || '');

            // Show/hide VIP Luncheon based on tier
            const editIsVip = (registration.tier || '').toLowerCase() === 'vip';
            $('#edit-vip_luncheon').closest('.fuse-form-group').toggle(editIsVip);
            if (!editIsVip) $('#edit-vip_luncheon').prop('checked', false);

            // Render guests — Supabase returns them as fuse_registration_guests
            this.renderGuestsInEdit(registration.fuse_registration_guests || []);
        },

        renderGuestsInEdit: function(guests) {
            const self = this;
            const container = $('#edit-guests-list');
            container.empty();

            const isEditVip = ($('#edit-tier').val() || '').toLowerCase() === 'vip';
            const showHoa   = $('#edit-hall_of_aime').is(':checked');
            const showWmn   = $('#edit-wmn_at_fuse').is(':checked');
            const showLunch = isEditVip && $('#edit-vip_luncheon').is(':checked'); // VIP only
            const showVa    = $('#edit-vetted_va').is(':checked');

            guests.forEach(function(guest, index) {
                const hoaChecked   = !!(guest.has_hall_of_aime) || guest.ticket_type === 'vip_guest';
                const wmnChecked   = !!(guest.has_wmn_at_fuse);
                const lunchChecked = isEditVip && !!(guest.has_vip_luncheon); // VIP only
                const vaChecked    = !!(guest.has_vetted_va);
                // Always render all rows; show based on current main-attendee checkbox state
                // (or always show if the guest already has that addon checked)
                const showHoaRow   = showHoa   || hoaChecked;
                const showWmnRow   = showWmn   || wmnChecked;
                const showLunchRow = isEditVip && (showLunch || lunchChecked); // VIP only
                const showVaRow    = showVa    || vaChecked;
                const addonsHtml = '<div class="fuse-form-row guest-item-addons">' +
                    '<label class="guest-addon-label guest-addon-row--hoa" style="' + (showHoaRow ? '' : 'display:none;') + '">' +
                    '<input type="checkbox" class="guest-hoa"' + (hoaChecked ? ' checked' : '') + '> Hall of AIME (+$299)</label>' +
                    '<label class="guest-addon-label guest-addon-row--wmn" style="' + (showWmnRow ? '' : 'display:none;') + '">' +
                    '<input type="checkbox" class="guest-wmn"' + (wmnChecked ? ' checked' : '') + '> WMN at Fuse (FREE)</label>' +
                    '<label class="guest-addon-label guest-addon-row--lunch" style="' + (showLunchRow ? '' : 'display:none;') + '">' +
                    '<input type="checkbox" class="guest-vip-luncheon"' + (lunchChecked ? ' checked' : '') + '> VIP Luncheon</label>' +
                    '<label class="guest-addon-label guest-addon-row--va" style="' + (showVaRow ? '' : 'display:none;') + '">' +
                    '<input type="checkbox" class="guest-vetted-va"' + (vaChecked ? ' checked' : '') + '> Vetted VA</label>' +
                    '</div>';

                const guestHtml = '<div class="guest-item">' +
                    '<div class="guest-item-header">' +
                    '<strong>Guest ' + (index + 1) + '</strong>' +
                    '<span class="guest-item-remove" data-index="' + index + '">Remove</span>' +
                    '</div>' +
                    '<div class="fuse-form-row">' +
                    '<div class="fuse-form-group">' +
                    '<label>Full Name</label>' +
                    '<input type="text" class="guest-full-name" value="' + self.escapeHtml(guest.full_name || '') + '">' +
                    '</div>' +
                    '<div class="fuse-form-group">' +
                    '<label>Email</label>' +
                    '<input type="email" class="guest-email" value="' + self.escapeHtml(guest.email || '') + '">' +
                    '</div>' +
                    '</div>' +
                    '<div class="fuse-form-row">' +
                    '<div class="fuse-form-group">' +
                    '<label>Phone</label>' +
                    '<input type="tel" class="guest-phone" value="' + self.escapeHtml(guest.phone || '') + '">' +
                    '</div>' +
                    '<div class="fuse-form-group">' +
                    '<label>Ticket Type</label>' +
                    '<select class="guest-ticket-type">' +
                    '<option value="guest_ticket"'      + (guest.ticket_type === 'guest_ticket'      ? ' selected' : '') + '>Guest Ticket (Non-Member)</option>' +
                    '<option value="guest_member"'      + (guest.ticket_type === 'guest_member'      ? ' selected' : '') + '>Guest (Member)</option>' +
                    '<option value="vip_guest"'         + (guest.ticket_type === 'vip_guest'         ? ' selected' : '') + '>VIP Guest</option>' +
                    '<option value="general_admission"' + (guest.ticket_type === 'general_admission' ? ' selected' : '') + '>General Admission (legacy)</option>' +
                    '</select>' +
                    '</div>' +
                    '</div>' +
                    addonsHtml +
                    '</div>';

                container.append(guestHtml);
            });

            // Remove guest handler
            container.on('click', '.guest-item-remove', function() {
                $(this).closest('.guest-item').remove();
            });
        },

        addEditGuestField: function() {
            const isEditVip = ($('#edit-tier').val() || '').toLowerCase() === 'vip';
            const showHoa   = $('#edit-hall_of_aime').is(':checked');
            const showWmn   = $('#edit-wmn_at_fuse').is(':checked');
            const showLunch = isEditVip && $('#edit-vip_luncheon').is(':checked'); // VIP only
            const showVa    = $('#edit-vetted_va').is(':checked');
            const guestIndex = $('#edit-guests-list .guest-item').length; // 0-based
            const guestCount = guestIndex + 1;

            // Smart default ticket type
            const tier = ($('#edit-tier').val() || '').toLowerCase();
            const isVip    = tier === 'vip';
            const isMember = isVip || tier === 'premium' || tier === 'elite';
            let defaultType = 'guest_ticket';
            if (isVip && guestIndex === 0) defaultType = 'vip_guest';
            else if (isMember)             defaultType = 'guest_member';

            const guestHtml = '<div class="guest-item">' +
                '<div class="guest-item-header">' +
                '<strong>Guest ' + guestCount + '</strong>' +
                '<span class="guest-item-remove" style="float:right;cursor:pointer;color:#d32f2f;">Remove</span>' +
                '</div>' +
                '<div class="fuse-form-row">' +
                '<div class="fuse-form-group">' +
                '<label>Full Name</label>' +
                '<input type="text" class="guest-full-name" placeholder="Full name">' +
                '</div>' +
                '<div class="fuse-form-group">' +
                '<label>Email</label>' +
                '<input type="email" class="guest-email" placeholder="Email">' +
                '</div>' +
                '</div>' +
                '<div class="fuse-form-row">' +
                '<div class="fuse-form-group">' +
                '<label>Phone</label>' +
                '<input type="tel" class="guest-phone" placeholder="Phone">' +
                '</div>' +
                '<div class="fuse-form-group">' +
                '<label>Ticket Type</label>' +
                '<select class="guest-ticket-type">' +
                '<option value="guest_ticket"' + (defaultType === 'guest_ticket' ? ' selected' : '') + '>Guest Ticket (Non-Member)</option>' +
                '<option value="guest_member"' + (defaultType === 'guest_member' ? ' selected' : '') + '>Guest (Member)</option>' +
                '<option value="vip_guest"'    + (defaultType === 'vip_guest'    ? ' selected' : '') + '>VIP Guest</option>' +
                '</select>' +
                '</div>' +
                '</div>' +
                '<div class="fuse-form-row guest-item-addons">' +
                '<label class="guest-addon-label guest-addon-row--hoa" style="' + (showHoa ? '' : 'display:none;') + '">' +
                '<input type="checkbox" class="guest-hoa"> Hall of AIME (+$299)</label>' +
                '<label class="guest-addon-label guest-addon-row--wmn" style="' + (showWmn ? '' : 'display:none;') + '">' +
                '<input type="checkbox" class="guest-wmn"> WMN at Fuse (FREE)</label>' +
                '<label class="guest-addon-label guest-addon-row--lunch" style="' + (showLunch ? '' : 'display:none;') + '">' +
                '<input type="checkbox" class="guest-vip-luncheon"> VIP Luncheon</label>' +
                '<label class="guest-addon-label guest-addon-row--va" style="' + (showVa ? '' : 'display:none;') + '">' +
                '<input type="checkbox" class="guest-vetted-va"> Vetted VA</label>' +
                '</div>' +
                '</div>';

            const container = $('#edit-guests-list');
            container.append(guestHtml);

            // Ensure remove works on newly added guests
            container.off('click', '.guest-item-remove').on('click', '.guest-item-remove', function() {
                $(this).closest('.guest-item').remove();
            });
        },

        saveRegistration: function() {
            const self = this;
            const regId = $('#edit-registration-id').val();

            // Gather guests
            const guests = [];
            $('#edit-guests-list .guest-item').each(function() {
                guests.push({
                    full_name:        $(this).find('.guest-full-name').val(),
                    email:            $(this).find('.guest-email').val(),
                    phone:            $(this).find('.guest-phone').val(),
                    ticket_type:      $(this).find('.guest-ticket-type').val(),
                    has_hall_of_aime: $(this).find('.guest-hoa').is(':checked') ? 1 : 0,
                    has_wmn_at_fuse:  $(this).find('.guest-wmn').is(':checked') ? 1 : 0,
                    has_vip_luncheon: $(this).find('.guest-vip-luncheon').is(':checked') ? 1 : 0,
                    has_vetted_va:    $(this).find('.guest-vetted-va').is(':checked') ? 1 : 0
                });
            });

            const data = {
                action: 'fuse_admin_save_registration',
                nonce: this.config.nonce,
                registration_id: regId,
                first_name: $('#edit-first_name').val(),
                last_name: $('#edit-last_name').val(),
                preferred_name: $('#edit-preferred_name').val(),
                email: $('#edit-email').val(),
                phone: $('#edit-phone').val(),
                company: $('#edit-company').val(),
                gender: $('#edit-gender').val(),
                ticket_type: $('#edit-ticket_type').val(),
                tier: $('#edit-tier').val(),
                purchase_type: $('#edit-purchase_type').val(),
                fuse_attendance: $('#edit-fuse_attendance').val(),
                has_hall_of_aime: $('#edit-hall_of_aime').is(':checked') ? 1 : 0,
                has_wmn_at_fuse: $('#edit-wmn_at_fuse').is(':checked') ? 1 : 0,
                has_vip_luncheon: $('#edit-vip_luncheon').is(':checked') ? 1 : 0,
                has_vetted_va: $('#edit-vetted_va').is(':checked') ? 1 : 0,
                notes: $('#edit-notes').val(),
                guests: JSON.stringify(guests)
            };

            $.post(this.config.ajaxUrl, data, function(response) {
                if (response.success) {
                    self._editFormSnapshot = null; // clear dirty flag — changes are saved
                    self.showNotice('Registration saved successfully', 'success');
                    $('#fuse-edit-modal').hide();
                    self.loadRegistrations();
                } else {
                    self.showNotice(response.data.message || 'Failed to save registration', 'error');
                }
            }).fail(function() {
                self.showNotice('Failed to save registration', 'error');
            });
        },

        // ==================== ADD REGISTRATION ====================

        initAddRegistration: function() {
            const self = this;

            // Member lookup
            $('#add-lookup-member').on('click', function(e) {
                e.preventDefault();
                self.lookupMember();
            });

            // Add guest
            $('#add-add-guest').on('click', function(e) {
                e.preventDefault();
                self.addGuestField();
            });

            // Remove guest (delegated so it works on dynamically added cards)
            $(document).on('click', '#add-guests-list .guest-item-remove', function() {
                $(this).closest('.guest-item').remove();
            });

            // Real-time addon visibility: when main attendee's HOA/WMN changes,
            // show or hide the corresponding row on every guest card already added.
            $('#add-hall_of_aime').on('change', function() {
                const show = $(this).is(':checked');
                $('#add-guests-list .guest-item').each(function() {
                    const $row = $(this).find('.guest-addon-row--hoa');
                    $row.toggle(show);
                    if (!show) $row.find('.guest-hoa').prop('checked', false);
                });
            });
            $('#add-wmn_at_fuse').on('change', function() {
                const show = $(this).is(':checked');
                $('#add-guests-list .guest-item').each(function() {
                    const $row = $(this).find('.guest-addon-row--wmn');
                    $row.toggle(show);
                    if (!show) $row.find('.guest-wmn').prop('checked', false);
                });
            });
            $('#add-vip_luncheon').on('change', function() {
                const show = $(this).is(':checked');
                $('#add-guests-list .guest-item').each(function() {
                    const $row = $(this).find('.guest-addon-row--lunch');
                    $row.toggle(show);
                    if (!show) $row.find('.guest-vip-luncheon').prop('checked', false);
                });
            });

            // VIP Luncheon is only available when tier is VIP — show/hide checkbox accordingly
            function syncAddVipLuncheonVisibility() {
                const isVip = ($('#add-tier').val() || '').toLowerCase() === 'vip';
                const $lunchGroup = $('#add-vip_luncheon').closest('.fuse-form-group');
                $lunchGroup.toggle(isVip);
                if (!isVip) {
                    $('#add-vip_luncheon').prop('checked', false);
                    // Also uncheck all guest luncheon rows
                    $('#add-guests-list .guest-item').each(function() {
                        const $row = $(this).find('.guest-addon-row--lunch');
                        $row.hide().find('.guest-vip-luncheon').prop('checked', false);
                    });
                }
            }
            $('#add-tier').on('change', syncAddVipLuncheonVisibility);
            syncAddVipLuncheonVisibility(); // run on page load
            $('#add-vetted_va').on('change', function() {
                const show = $(this).is(':checked');
                $('#add-guests-list .guest-item').each(function() {
                    const $row = $(this).find('.guest-addon-row--va');
                    $row.toggle(show);
                    if (!show) $row.find('.guest-vetted-va').prop('checked', false);
                });
            });

            // Save registration
            $('#add-save-registration').on('click', function(e) {
                e.preventDefault();
                self.saveNewRegistration(false);
            });

            // Save and send invoice
            $('#add-save-and-invoice').on('click', function(e) {
                e.preventDefault();
                self.saveNewRegistration(true);
            });

        },

        lookupMember: function() {
            const self = this;
            const email = $('#add-email').val().trim();
            const resultDiv = $('#add-member-lookup-result');

            if (!email) {
                resultDiv.removeClass('success error').css('display','block').text('Please enter an email address');
                return;
            }

            resultDiv.css('display','block').html('<em>Checking membership...</em>');

            $.post(this.config.ajaxUrl, {
                action: 'fuse_admin_lookup_member',
                nonce: this.config.nonce,
                email: email
            }, function(response) {
                if (response.success && response.data.is_member) {
                    const m = response.data;
                    resultDiv.removeClass('error').addClass('success');
                    resultDiv.html('✓ <strong>' + self.escapeHtml((m.first_name || '') + ' ' + (m.last_name || '')) + '</strong>' +
                        ' — ' + self.escapeHtml(m.tier || 'Member'));

                    // Auto-fill form fields
                    if (m.first_name) $('#add-first_name').val(m.first_name);
                    if (m.last_name)  $('#add-last_name').val(m.last_name);
                    if (m.company)    $('#add-company').val(m.company);
                    if (m.phone)      $('#add-phone').val(m.phone);
                    if (m.tier)       $('#add-tier').val(m.tier).trigger('change');

                    // Set ticket type based on member tier
                    const tier = (m.tier || '').toLowerCase();
                    if (tier === 'vip') {
                        $('#add-ticket_type').val('vip');
                    } else {
                        $('#add-ticket_type').val('general_admission');
                    }
                    // Default purchase type to Pending for manually invoiced registrations
                    $('#add-purchase_type').val('pending');
                } else {
                    const msg = (response.data && response.data.message) ? response.data.message : 'No active membership found.';
                    resultDiv.removeClass('success').addClass('error');
                    resultDiv.html('ℹ ' + self.escapeHtml(msg) + ' You can still create a manual registration.');
                }
            }).fail(function() {
                resultDiv.removeClass('success').addClass('error');
                resultDiv.text('Error communicating with server. Please try again.');
            });
        },

        addGuestField: function() {
            const guestIndex = $('#add-guests-list .guest-item').length; // 0-based
            const guestCount = guestIndex + 1;
            const isAddVip  = ($('#add-tier').val() || '').toLowerCase() === 'vip';
            const showHoa   = $('#add-hall_of_aime').is(':checked');
            const showWmn   = $('#add-wmn_at_fuse').is(':checked');
            const showLunch = isAddVip && $('#add-vip_luncheon').is(':checked'); // VIP only
            const showVa    = $('#add-vetted_va').is(':checked');

            // Smart default ticket type based on tier and position
            const tier = ($('#add-tier').val() || '').toLowerCase();
            const isVip    = tier === 'vip';
            const isMember = isVip || tier === 'premium' || tier === 'elite';
            let defaultType = 'guest_ticket';
            if (isVip && guestIndex === 0) defaultType = 'vip_guest';
            else if (isMember)             defaultType = 'guest_member';

            // Always render both addon rows — hidden state controlled by CSS display
            const guestHtml = '<div class="guest-item">' +
                '<div class="guest-item-header">' +
                '<strong>Guest ' + guestCount + '</strong>' +
                '<span class="guest-item-remove" style="float:right;cursor:pointer;color:#d32f2f;">Remove</span>' +
                '</div>' +
                '<div class="guest-item-fields">' +
                '<input type="text" class="guest-full-name" placeholder="Full Name">' +
                '<input type="email" class="guest-email" placeholder="Email">' +
                '<input type="tel" class="guest-phone" placeholder="Phone">' +
                '<select class="guest-ticket-type">' +
                '<option value="guest_ticket"' + (defaultType === 'guest_ticket' ? ' selected' : '') + '>Guest Ticket (Non-Member)</option>' +
                '<option value="guest_member"' + (defaultType === 'guest_member' ? ' selected' : '') + '>Guest (Member)</option>' +
                '<option value="vip_guest"'    + (defaultType === 'vip_guest'    ? ' selected' : '') + '>VIP Guest</option>' +
                '</select>' +
                '</div>' +
                '<div class="guest-item-addons">' +
                '<label class="guest-addon-label guest-addon-row--hoa" style="' + (showHoa ? '' : 'display:none;') + '">' +
                '<input type="checkbox" class="guest-hoa"> Hall of AIME (+$299)</label>' +
                '<label class="guest-addon-label guest-addon-row--wmn" style="' + (showWmn ? '' : 'display:none;') + '">' +
                '<input type="checkbox" class="guest-wmn"> WMN at Fuse (FREE)</label>' +
                '<label class="guest-addon-label guest-addon-row--lunch" style="' + (showLunch ? '' : 'display:none;') + '">' +
                '<input type="checkbox" class="guest-vip-luncheon"> VIP Luncheon</label>' +
                '<label class="guest-addon-label guest-addon-row--va" style="' + (showVa ? '' : 'display:none;') + '">' +
                '<input type="checkbox" class="guest-vetted-va"> Vetted VA</label>' +
                '</div>' +
                '</div>';

            $('#add-guests-list').append(guestHtml);
        },

        saveNewRegistration: function(sendInvoice) {
            const self = this;

            // Gather guests
            const guests = [];
            $('#add-guests-list .guest-item').each(function() {
                guests.push({
                    full_name:        $(this).find('.guest-full-name').val(),
                    email:            $(this).find('.guest-email').val(),
                    phone:            $(this).find('.guest-phone').val(),
                    ticket_type:      $(this).find('.guest-ticket-type').val(),
                    has_hall_of_aime: $(this).find('.guest-hoa').is(':checked') ? 1 : 0,
                    has_wmn_at_fuse:  $(this).find('.guest-wmn').is(':checked') ? 1 : 0,
                    has_vip_luncheon: $(this).find('.guest-vip-luncheon').is(':checked') ? 1 : 0,
                    has_vetted_va:    $(this).find('.guest-vetted-va').is(':checked') ? 1 : 0
                });
            });

            const data = {
                action: 'fuse_admin_save_registration',
                nonce: this.config.nonce,
                first_name: $('#add-first_name').val(),
                last_name: $('#add-last_name').val(),
                preferred_name: $('#add-preferred_name').val(),
                email: $('#add-email').val(),
                phone: $('#add-phone').val(),
                company: $('#add-company').val(),
                gender: $('#add-gender').val(),
                ticket_type: $('#add-ticket_type').val(),
                tier: $('#add-tier').val(),
                purchase_type: $('#add-purchase_type').val(),
                fuse_attendance: $('#add-fuse_attendance').val(),
                has_hall_of_aime: $('#add-hall_of_aime').is(':checked') ? 1 : 0,
                has_wmn_at_fuse: $('#add-wmn_at_fuse').is(':checked') ? 1 : 0,
                has_vip_luncheon: $('#add-vip_luncheon').is(':checked') ? 1 : 0,
                has_vetted_va: $('#add-vetted_va').is(':checked') ? 1 : 0,
                notes: $('#add-notes').val(),
                guests: JSON.stringify(guests)
            };

            $.post(this.config.ajaxUrl, data, function(response) {
                if (response.success) {
                    if (sendInvoice) {
                        // response.data is { message: "...", data: [supabase row] }
                        // Extract the actual registration object from the nested data array
                        const rawData = response.data && response.data.data;
                        const savedReg = Array.isArray(rawData) ? rawData[0] : rawData;

                        // Merge the saved record with what the user typed in the form
                        // so all fields (email, name, ticket_type, etc.) are available
                        self.state.currentRegistration = Object.assign({
                            email:          $('#add-email').val(),
                            first_name:     $('#add-first_name').val(),
                            last_name:      $('#add-last_name').val(),
                            ticket_type:    $('#add-ticket_type').val(),
                            tier:           $('#add-tier').val(),
                            has_hall_of_aime: $('#add-hall_of_aime').is(':checked'),
                            has_wmn_at_fuse:  $('#add-wmn_at_fuse').is(':checked'),
                            has_vip_luncheon: $('#add-vip_luncheon').is(':checked'),
                            has_vetted_va:    $('#add-vetted_va').is(':checked'),
                            guests:         guests, // include guests collected from form above
                        }, savedReg || {});

                        self.openInvoiceModal();
                    } else {
                        // Redirect to registrations list
                        window.location.href = fuseAdmin.registrationsUrl;
                    }
                } else {
                    self.showNotice(response.data.message || 'Failed to save registration', 'error');
                }
            }).fail(function() {
                self.showNotice('Failed to save registration', 'error');
            });
        },

        confirmDeleteRegistration: function(regId, name) {
            const self = this;
            $('#fuse-delete-name').text(name || 'this registration');
            $('#fuse-delete-confirm-btn').off('click').on('click', function() {
                $('#fuse-delete-modal').hide();
                self.deleteRegistration(regId);
            });
            $('#fuse-delete-modal').show();
        },

        deleteRegistration: function(regId) {
            const self = this;

            $.post(this.config.ajaxUrl, {
                action: 'fuse_admin_delete_registration',
                nonce: this.config.nonce,
                id: regId
            }, function(response) {
                if (response.success) {
                    self.showNotice('Registration deleted.', 'success');
                    self.loadRegistrations();
                } else {
                    self.showNotice(response.data.message || 'Failed to delete registration.', 'error');
                }
            }).fail(function() {
                self.showNotice('Server error while deleting.', 'error');
            });
        },

        openInvoiceModal: function() {
            const reg = this.state.currentRegistration;
            if (!reg) return;

            // Merge any live add/edit form values into the registration object so
            // the invoice reflects what's currently on screen (e.g. when "Save & Invoice"
            // is clicked before a full page reload).
            const merged = Object.assign({}, reg);

            // Pull name — prefer form fields when available
            const editFirst = $('#edit-first_name').val() || $('#add-first_name').val();
            const editLast  = $('#edit-last_name').val()  || $('#add-last_name').val();
            if (editFirst) merged.first_name = editFirst;
            if (editLast)  merged.last_name  = editLast;

            // Pull ticket / tier from form dropdowns when available
            const editTicket = $('#edit-ticket_type').val() || $('#add-ticket_type').val();
            const editTier   = $('#edit-tier').val()        || $('#add-tier').val();
            if (editTicket) merged.ticket_type = editTicket;
            if (editTier)   merged.tier        = editTier;

            // Pull add-on state from form checkboxes when available
            if ($('#edit-hall_of_aime').length || $('#add-hall_of_aime').length) {
                merged.has_hall_of_aime = $('#edit-hall_of_aime').is(':checked') || $('#add-hall_of_aime').is(':checked');
            }
            if ($('#edit-wmn_at_fuse').length || $('#add-wmn_at_fuse').length) {
                merged.has_wmn_at_fuse = $('#edit-wmn_at_fuse').is(':checked') || $('#add-wmn_at_fuse').is(':checked');
            }
            if ($('#edit-vip_luncheon').length || $('#add-vip_luncheon').length) {
                merged.has_vip_luncheon = $('#edit-vip_luncheon').is(':checked') || $('#add-vip_luncheon').is(':checked');
            }
            if ($('#edit-vetted_va').length || $('#add-vetted_va').length) {
                merged.has_vetted_va = $('#edit-vetted_va').is(':checked') || $('#add-vetted_va').is(':checked');
            }

            // Pull live guests from the DOM — covers both the add-new and edit flows,
            // and picks up any guests added/changed after the registration was last loaded.
            const liveGuests = [];
            $('#edit-guests-list .guest-item, #add-guests-list .guest-item').each(function() {
                const name = $(this).find('.guest-full-name').val();
                if (name) {
                    liveGuests.push({
                        full_name:        name,
                        email:            $(this).find('.guest-email').val() || '',
                        ticket_type:      $(this).find('.guest-ticket-type').val() || '',
                        has_hall_of_aime: $(this).find('.guest-hoa').is(':checked') ? 1 : 0,
                        has_wmn_at_fuse:  $(this).find('.guest-wmn').is(':checked') ? 1 : 0,
                        has_vip_luncheon: $(this).find('.guest-vip-luncheon').is(':checked') ? 1 : 0,
                        has_vetted_va:    $(this).find('.guest-vetted-va').is(':checked') ? 1 : 0,
                    });
                }
            });
            if (liveGuests.length > 0) {
                merged.guests = liveGuests;
            }

            const firstName = merged.first_name || '';
            const lastName  = merged.last_name  || '';
            $('#invoice-recipient-name').text(
                (firstName || lastName) ? (firstName + ' ' + lastName).trim() : 'this registrant'
            );

            const lineItems = this.generateDefaultLineItems(merged);
            this.state.invoiceData.registrationId = reg.id;
            this.state.invoiceData.lineItems = lineItems;

            this.renderInvoiceLineItems(lineItems);
            $('#fuse-invoice-modal').show();
        },

        generateDefaultLineItems: function(registration) {
            const self     = this;
            const items    = [];
            const pricing  = (typeof fuseAdmin !== 'undefined' && fuseAdmin.pricing)  ? fuseAdmin.pricing  : {};
            const priceIds = (typeof fuseAdmin !== 'undefined' && fuseAdmin.priceIds) ? fuseAdmin.priceIds : {};
            const isEarlyBird = (typeof fuseAdmin !== 'undefined') ? !!fuseAdmin.isEarlyBird : false;

            // ── Determine member tier ──────────────────────────────────────
            const tier = (registration.tier || '').toLowerCase();
            const isMember = tier === 'premium' || tier === 'elite' || tier === 'vip';

            // ── Main ticket ───────────────────────────────────────────────
            const ticketType = registration.ticket_type || 'general_admission';
            let ticketPrice, ticketPriceId, ticketLabel;

            if (ticketType === 'vip') {
                ticketPrice   = 0;
                ticketPriceId = '';
                ticketLabel   = 'VIP Ticket (Complimentary)';
            } else {
                // General admission — members get it free, non-members pay
                if (isMember) {
                    ticketPrice   = 0;
                    ticketPriceId = '';
                    ticketLabel   = 'General Admission (Member — Complimentary)';
                } else if (isEarlyBird) {
                    ticketPrice   = pricing.ga_early_bird || 69900;
                    ticketPriceId = priceIds.ga_early_bird || '';
                    ticketLabel   = 'General Admission (Early Bird)';
                } else {
                    ticketPrice   = pricing.ga_regular || 89900;
                    ticketPriceId = priceIds.ga || '';
                    ticketLabel   = 'General Admission';
                }
            }

            items.push({ description: ticketLabel, amount: ticketPrice, price_id: ticketPriceId });

            // ── Hall of AIME ──────────────────────────────────────────────
            if (registration.has_hall_of_aime) {
                let hoaPrice, hoaPriceId;
                if (isMember) {
                    hoaPrice   = pricing.hoa_member || 19900;
                    hoaPriceId = priceIds.hoa_member || '';
                } else if (isEarlyBird) {
                    hoaPrice   = pricing.hoa_nonmember_early || 24900;
                    hoaPriceId = priceIds.hoa_nonmember_early || '';
                } else {
                    hoaPrice   = pricing.hoa_nonmember || 34900;
                    hoaPriceId = priceIds.hoa_nonmember || '';
                }
                items.push({ description: 'Hall of AIME', amount: hoaPrice, price_id: hoaPriceId });
            }

            // ── WMN at Fuse (free) ────────────────────────────────────────
            if (registration.has_wmn_at_fuse) {
                items.push({ description: 'WMN at Fuse', amount: 0, price_id: '' });
            }

            // ── VIP Luncheon ──────────────────────────────────────────────
            if (registration.has_vip_luncheon) {
                const lunchPrice   = pricing.vip_luncheon || 0;
                const lunchPriceId = priceIds.vip_luncheon || '';
                items.push({ description: 'VIP Luncheon', amount: lunchPrice, price_id: lunchPriceId });
            }

            // ── Vetted VA ─────────────────────────────────────────────────
            if (registration.has_vetted_va) {
                const vaPrice   = pricing.vetted_va || 0;
                const vaPriceId = priceIds.vetted_va || '';
                items.push({ description: 'Vetted VA', amount: vaPrice, price_id: vaPriceId });
            }

            // ── Guest tickets ─────────────────────────────────────────────
            const guests = registration.fuse_registration_guests || registration.guests || [];
            const isVip  = ticketType === 'vip';
            guests.forEach(function(guest) {
                let guestPrice, guestPriceId;
                // All guests pay the regular $349 rate.
                guestPrice   = pricing.guest_regular || 34900;
                guestPriceId = priceIds.guest_regular || '';
                const guestName    = guest.full_name || guest.name || 'Guest';
                const guestTicketType = guest.ticket_type || '';
                const isVipGuest   = guestTicketType === 'vip_guest';
                const guestHoa     = !!(guest.has_hall_of_aime) || isVipGuest;

                // VIP guests get HOA included — fold into the ticket label
                const guestLabel = isVipGuest
                    ? 'Guest Ticket + Hall of AIME: ' + guestName
                    : 'Guest Ticket: ' + guestName;
                items.push({
                    description: guestLabel,
                    amount: guestPrice,
                    price_id: guestPriceId
                });

                // Non-VIP guests with HOA checked get a separate HOA line item.
                // Guests are never members themselves so always use the non-member rate
                // (early bird or regular depending on the current date).
                if (guestHoa && !isVipGuest) {
                    const gHoaPrice   = isEarlyBird ? (pricing.hoa_nonmember_early || 24900) : (pricing.hoa_nonmember || 34900);
                    const gHoaPriceId = isEarlyBird ? (priceIds.hoa_nonmember_early || '')   : (priceIds.hoa_nonmember || '');
                    items.push({
                        description: 'Hall of AIME: ' + guestName,
                        amount:      gHoaPrice,
                        price_id:    gHoaPriceId
                    });
                }

                // Guests with WMN at Fuse checked get a $0 line item
                if (!!(guest.has_wmn_at_fuse)) {
                    items.push({
                        description: 'WMN at Fuse: ' + guestName,
                        amount:      0,
                        price_id:    priceIds.wmn || ''
                    });
                }

                // VIP Luncheon for guest
                if (!!(guest.has_vip_luncheon)) {
                    items.push({
                        description: 'VIP Luncheon: ' + guestName,
                        amount:      pricing.vip_luncheon || 0,
                        price_id:    priceIds.vip_luncheon || ''
                    });
                }

                // Vetted VA for guest
                if (!!(guest.has_vetted_va)) {
                    items.push({
                        description: 'Vetted VA: ' + guestName,
                        amount:      pricing.vetted_va || 0,
                        price_id:    priceIds.vetted_va || ''
                    });
                }
            });

            return items;
        },

        renderInvoiceLineItems: function(items) {
            const self = this;
            const container = $('#invoice-line-items');
            container.empty();

            // Read-only summary table — hidden inputs carry the data for sendInvoice()
            let totalAmount = 0;
            const tableRows = items.map(function(item) {
                totalAmount += item.amount;
                const priceId = item.price_id || '';
                const amountDisplay = item.amount === 0
                    ? '<span style="color:#2e7d32;font-weight:500;">Free</span>'
                    : '$' + self.formatCurrency(item.amount);

                // Hidden inputs preserve values for sendInvoice() to read
                return '<div class="invoice-line-item">' +
                    '<input type="hidden" class="invoice-description" value="' + self.escapeHtml(item.description) + '">' +
                    '<input type="hidden" class="invoice-amount" value="' + (item.amount / 100).toFixed(2) + '">' +
                    '<input type="hidden" class="invoice-price-id" value="' + self.escapeHtml(priceId) + '">' +
                    '<span class="invoice-item-label">' + self.escapeHtml(item.description) + '</span>' +
                    '<span class="invoice-item-price">' + amountDisplay + '</span>' +
                    '</div>';
            });

            container.html(tableRows.join(''));

            // Total row
            container.append(
                '<div class="invoice-total-row">' +
                '<span>Total</span>' +
                '<span>$' + self.formatCurrency(totalAmount) + '</span>' +
                '</div>'
            );
        },

        addInvoiceLineItem: function() {
            // No-op — invoice items are read-only, generated from registration data
        },

        sendInvoice: function() {
            const self = this;

            // Collect line items — include $0 items so they appear on the invoice email.
            // PHP will handle $0 items appropriately (using price_id if available,
            // or adding a $0 dynamic invoice item for display purposes).
            const lineItems = [];
            $('#invoice-line-items .invoice-line-item').each(function() {
                const description = $(this).find('.invoice-description').val();
                const amount = Math.round(parseFloat($(this).find('.invoice-amount').val()) * 100);
                const priceId = $(this).find('.invoice-price-id').val() || '';

                if (description) {
                    lineItems.push({ description: description, amount: amount, price_id: priceId });
                }
            });

            if (lineItems.length === 0) {
                self.invoiceModalNotice('Please add at least one line item.', 'error');
                return;
            }

            const reg = this.state.currentRegistration;
            // Fallback: read directly from whichever form is currently open
            const email = (reg && reg.email)
                || $('#add-email').val()
                || $('#edit-email').val()
                || '';

            if (!email) {
                self.invoiceModalNotice('No email address found. Please enter the registrant\'s email and try again.', 'error');
                return;
            }

            const $btn = $('#fuse-send-invoice-confirm');
            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'fuse_admin_send_invoice',
                    nonce: this.config.nonce,
                    registration_id: this.state.invoiceData.registrationId,
                    email: email,
                    line_items: JSON.stringify(lineItems)
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Send Invoice');
                    if (response && response.success) {
                        const d = response.data || {};
                        if (d.email_sent === false) {
                            self.invoiceModalNotice(
                                'Invoice created in Stripe but the email could not be sent: ' + (d.email_error || 'unknown error') +
                                '. You can resend it from the Stripe Dashboard.',
                                'error'
                            );
                        } else {
                            self.invoiceModalNotice('Invoice created and emailed to customer!', 'success');
                            setTimeout(function() {
                                $('#fuse-invoice-modal').hide();
                                // If we came from the edit modal, close it too
                                if ($('#fuse-edit-modal').is(':visible')) {
                                    self._editFormSnapshot = null;
                                    $('#fuse-edit-modal').hide();
                                    self.loadRegistrations();
                                } else {
                                    // Came from Add New — redirect to registrations list
                                    window.location.href = fuseAdmin.registrationsUrl;
                                }
                            }, 1500);
                        }
                    } else {
                        const msg = (response && response.data && response.data.message)
                            ? response.data.message
                            : 'Failed to send invoice. Check that Stripe is configured in Settings.';
                        self.invoiceModalNotice(msg, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).text('Send Invoice');
                    if (status === 'parsererror') {
                        self.invoiceModalNotice('Authentication error — please refresh the page and try again.', 'error');
                    } else {
                        self.invoiceModalNotice('Server error (' + xhr.status + '): ' + error, 'error');
                    }
                }
            });
        },

        invoiceModalNotice: function(message, type) {
            const color = type === 'error' ? '#d32f2f' : '#2e7d32';
            const bg    = type === 'error' ? '#fdecea' : '#e8f5e9';
            const $notice = $('<div style="padding:10px 16px;margin:10px 20px 0;border-radius:4px;font-weight:500;background:' + bg + ';color:' + color + ';">' + message + '</div>');
            $('#fuse-invoice-modal .fuse-modal-body').prepend($notice);
            setTimeout(function() { $notice.fadeOut(function() { $(this).remove(); }); }, 4000);
        },

        // ==================== EXPORT ====================

        initExport: function() {
            const self = this;

            $('#export-csv-all').on('click', function() {
                self.exportAllAsCSV();
            });

            $('#export-json-all').on('click', function() {
                self.exportAllAsJSON();
            });

            $('#export-conexsys').on('click', function() {
                self.exportConexsys();
            });

            $('#copy-endpoint-url').on('click', function() {
                const url = $(this).siblings('code').text();
                self.copyToClipboard(url);
                $(this).text('Copied!').prop('disabled', true);
                setTimeout(function() {
                    $('#copy-endpoint-url').text('Copy').prop('disabled', false);
                }, 2000);
            });

            // Test API connection button
            $('#test-conexsys-api').on('click', function() {
                self.testConexsysApi();
            });

            // Display sample response
            this.displaySampleResponse();
        },

        testConexsysApi: function() {
            const $btn     = $('#test-conexsys-api');
            const $spinner = $('#test-conexsys-spinner');
            const $result  = $('#test-conexsys-result');

            $btn.prop('disabled', true);
            $spinner.show();
            $result.hide();

            // Hit the REST endpoint directly using the api_key from the quick-test URL
            const $quickUrl = $('.fuse-endpoint-url code').last();
            const testUrl   = $quickUrl.text().trim();

            if (!testUrl || testUrl.indexOf('api_key=') === -1) {
                $result.attr('class', 'fuse-api-test-result error')
                       .html('No API key found. Set one in Settings first.')
                       .show();
                $btn.prop('disabled', false);
                $spinner.hide();
                return;
            }

            $.ajax({
                url:      testUrl,
                method:   'GET',
                dataType: 'json',
                timeout:  15000,
                success: function(data) {
                    const count    = data.count || 0;
                    const rows     = data.data  || [];
                    const guests   = rows.filter(function(r) { return r.badge_type !== 'registrant'; }).length;
                    const primary  = rows.filter(function(r) { return r.badge_type === 'registrant'; }).length;
                    const hoa      = rows.filter(function(r) { return r.has_hall_of_aime; }).length;
                    const wmn      = rows.filter(function(r) { return r.has_wmn_at_fuse;  }).length;

                    $result.attr('class', 'fuse-api-test-result success').html(
                        '<strong>✓ API connection successful</strong><br>' +
                        'Total rows returned: <strong>' + count + '</strong> &nbsp;|&nbsp; ' +
                        'Primary attendees: <strong>' + primary + '</strong> &nbsp;|&nbsp; ' +
                        'Guests: <strong>' + guests + '</strong><br>' +
                        'Hall of AIME: <strong>' + hoa + '</strong> &nbsp;|&nbsp; ' +
                        'WMN at Fuse: <strong>' + wmn + '</strong>'
                    ).show();
                },
                error: function(xhr) {
                    let msg = 'Request failed';
                    if (xhr.status === 401 || xhr.status === 403) {
                        msg = 'Authentication failed — check your API key in Settings matches what Conexsys is using';
                    } else if (xhr.status === 0) {
                        msg = 'Could not reach the endpoint — check the site URL and that the REST API is accessible';
                    } else {
                        msg = 'HTTP ' + xhr.status + ' — ' + (xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : xhr.statusText);
                    }
                    $result.attr('class', 'fuse-api-test-result error')
                           .html('<strong>✗ Connection failed:</strong> ' + msg)
                           .show();
                },
                complete: function() {
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            });
        },

        exportAllAsCSV: function() {
            const self = this;

            $.post(this.config.ajaxUrl, {
                action: 'fuse_admin_export',
                nonce: this.config.nonce,
                format: 'csv',
                all: true
            }, function(response) {
                if (response.success) {
                    self.exportAsCSV(response.data.registrations || []);
                } else {
                    self.showNotice('Failed to export as CSV', 'error');
                }
            });
        },

        exportAllAsJSON: function() {
            const self = this;

            $.post(this.config.ajaxUrl, {
                action: 'fuse_admin_export',
                nonce: this.config.nonce,
                format: 'json',
                all: true
            }, function(response) {
                if (response.success) {
                    self.downloadJSON(response.data, 'registrations.json');
                } else {
                    self.showNotice('Failed to export as JSON', 'error');
                }
            });
        },

        exportConexsys: function() {
            const self = this;

            $.post(this.config.ajaxUrl, {
                action: 'fuse_admin_export',
                nonce: this.config.nonce,
                format: 'conexsys',
                all: true
            }, function(response) {
                if (response.success) {
                    self.downloadJSON(response.data, 'conexsys-export.json');
                    self.showNotice('Conexsys export created', 'success');
                } else {
                    self.showNotice('Failed to export for Conexsys', 'error');
                }
            });
        },

        displaySampleResponse: function() {
            const sample = {
                success: true,
                count: 2,
                data: [
                    {
                        badge_type: "registrant",
                        first_name: "John",
                        last_name: "Doe",
                        preferred_name: "Johnny",
                        email: "john@example.com",
                        phone: "+15550001234",
                        company: "Acme Corp",
                        ticket_type: "general_admission",
                        tier: "Premium",
                        purchase_type: "purchased",
                        has_hall_of_aime: true,
                        has_wmn_at_fuse: false,
                        guest_of: "",
                        registration_id: "123e4567-e89b-12d3-a456-426614174000",
                        created_at: "2026-04-06T10:30:00Z"
                    },
                    {
                        badge_type: "guest",
                        first_name: "Jane",
                        last_name: "Doe",
                        preferred_name: "",
                        email: "jane@example.com",
                        phone: "",
                        company: "Acme Corp",
                        ticket_type: "guest",
                        tier: "Premium",
                        purchase_type: "purchased",
                        has_hall_of_aime: false,
                        has_wmn_at_fuse: false,
                        guest_of: "John Doe",
                        registration_id: "123e4567-e89b-12d3-a456-426614174000",
                        created_at: "2026-04-06T10:30:00Z"
                    }
                ]
            };

            $('#sample-response-code').text(JSON.stringify(sample, null, 2));
        },

        // ==================== UTILITY FUNCTIONS ====================

        exportAsCSV: function(data) {
            const self = this;
            if (data.length === 0) {
                this.showNotice('No data to export', 'error');
                return;
            }

            const headers = [
                'Badge Type', 'First Name', 'Last Name', 'Email', 'Phone', 'Company',
                'Ticket Type', 'Tier', 'Purchase Type', 'Hall of AIME', 'WMN at Fuse',
                'VIP Luncheon', 'Vetted VA',
                'Guest Of', 'Date'
            ];
            let csv = headers.join(',') + '\n';

            data.forEach(function(row) {
                const guests = row.fuse_registration_guests || [];
                const fullName = (row.first_name || '') + ' ' + (row.last_name || '');

                // Primary registrant row
                const line = [
                    'Registrant',
                    self.escapeCsvField(row.first_name || ''),
                    self.escapeCsvField(row.last_name || ''),
                    self.escapeCsvField(row.email || ''),
                    self.escapeCsvField(row.phone || ''),
                    self.escapeCsvField(row.company || ''),
                    self.escapeCsvField(row.ticket_type || ''),
                    self.escapeCsvField(row.tier || ''),
                    self.escapeCsvField(row.purchase_type || ''),
                    (row.has_hall_of_aime || row.ticket_type === 'vip') ? 'Yes' : 'No',
                    row.has_wmn_at_fuse ? 'Yes' : 'No',
                    row.has_vip_luncheon ? 'Yes' : 'No',
                    row.has_vetted_va ? 'Yes' : 'No',
                    '',
                    self.formatDate(row.created_at)
                ];
                csv += line.join(',') + '\n';

                // One row per guest
                guests.forEach(function(guest) {
                    if (!guest.full_name) return;
                    const nameParts = (guest.full_name || '').trim().split(/\s+/);
                    const gFirstName = nameParts[0] || '';
                    const gLastName  = nameParts.slice(1).join(' ') || '';
                    const isVipGuest = (guest.ticket_type || '') === 'vip_guest';
                    // Ticket Type: guest_ticket or vip_guest_ticket
                    const gTicketType = isVipGuest ? 'vip_guest_ticket' : 'guest_ticket';
                    // HOA/WMN from stored fields; vip_guest always has HOA
                    const gHoa   = isVipGuest || !!guest.has_hall_of_aime;
                    const gWmn   = !!guest.has_wmn_at_fuse;
                    const gLunch = !!guest.has_vip_luncheon;
                    const gVa    = !!guest.has_vetted_va;
                    const guestLine = [
                        isVipGuest ? 'Guest + Hall of AIME' : 'Guest',
                        self.escapeCsvField(gFirstName),
                        self.escapeCsvField(gLastName),
                        self.escapeCsvField(guest.email || ''),
                        '',
                        '',
                        gTicketType,
                        '',
                        self.escapeCsvField(row.purchase_type || ''),
                        gHoa ? 'Yes' : 'No',
                        gWmn ? 'Yes' : 'No',
                        gLunch ? 'Yes' : 'No',
                        gVa ? 'Yes' : 'No',
                        self.escapeCsvField(fullName.trim()),
                        self.formatDate(row.created_at)
                    ];
                    csv += guestLine.join(',') + '\n';
                });
            });

            this.downloadFile(csv, 'registrations.csv', 'text/csv');
        },

        exportAsJSON: function(data) {
            this.downloadJSON(data, 'registrations.json');
        },

        downloadCSV: function(csv) {
            this.downloadFile(csv, 'registrations.csv', 'text/csv');
        },

        downloadJSON: function(data, filename) {
            const json = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
            this.downloadFile(json, filename, 'application/json');
        },

        downloadFile: function(content, filename, type) {
            const blob = new Blob([content], { type: type });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },

        copyToClipboard: function(text) {
            const temp = $('<input>');
            $('body').append(temp);
            temp.val(text).select();
            document.execCommand('copy');
            temp.remove();
        },

        // ==================== FORMATTING ====================

        formatNumber: function(num) {
            return Math.floor(num || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        formatCurrency: function(cents) {
            const dollars = (cents / 100).toFixed(2);
            return dollars.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        formatDate: function(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },

        formatTicketType: function(type) {
            const types = {
                'general_admission': 'General Admission',
                'vip': 'VIP'
            };
            return types[type] || type;
        },

        ticketTypeBadge: function(type) {
            const labels = {
                'general_admission': 'General Admission',
                'vip':               'VIP',
                'guest_ticket':      'Guest Ticket (Non-Member)',
                'guest_member':      'Guest (Member)',
                'vip_guest':         'VIP Guest'
            };
            const label = labels[type] || (type || '—');
            return '<span class="fuse-badge fuse-badge--ticket fuse-badge--ticket-' + (type || 'unknown') + '">' + label + '</span>';
        },

        tierBadge: function(tier) {
            if (!tier) return '<span class="fuse-badge fuse-badge--tier fuse-badge--tier-none">Non-Member</span>';
            return '<span class="fuse-badge fuse-badge--tier fuse-badge--tier-' + tier.toLowerCase() + '">' + tier + '</span>';
        },

        purchaseTypeBadge: function(type) {
            if (!type) return '';
            return '<span class="fuse-badge fuse-badge--purchase fuse-badge--purchase-' + type + '">' + type.charAt(0).toUpperCase() + type.slice(1) + '</span>';
        },

        escapeHtml: function(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        escapeCsvField: function(field) {
            if (!field) return '';
            if (field.toString().includes(',') || field.toString().includes('"') || field.toString().includes('\n')) {
                return '"' + field.toString().replace(/"/g, '""') + '"';
            }
            return field;
        },

        showNotice: function(message, type) {
            const noticeClass = 'notice-' + (type === 'error' ? 'error' : 'success');
            const html = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';

            const target = $('#fuse-registrations-content, #fuse-add-registration-content, #fuse-export-content').first();
            if (target.length) {
                target.prepend(html);
            }

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                target.find('.notice').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        FuseAdmin.init();
    });

    // Expose to global scope if needed
    window.FuseAdmin = FuseAdmin;

})(jQuery);
