(function($) {
    'use strict';

    function escapeSelector(str) {
        if (!str) return str;
        return str.replace(/[!"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~]/g, '\\$&');
    }

    var RoleManager = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.action-edit-name', this.openEditNameModal.bind(this));
            $(document).on('click', '.action-reset-name', this.resetName.bind(this));
            $(document).on('click', '.action-delete-role', this.openDeleteConfirmModal.bind(this));
            $(document).on('click', '.action-edit-caps', this.openEditCapsModal.bind(this));
            $(document).on('click', '.modal-close, .modal-cancel, .modal-overlay', this.closeModal.bind(this));
            $(document).on('submit', '#edit-name-form', this.saveName.bind(this));
            $(document).on('submit', '#delete-confirm-form', this.deleteRole.bind(this));
            $(document).on('submit', '#edit-caps-form', this.saveCapabilities.bind(this));
            $(document).on('submit', '#create-role-form', this.createRole.bind(this));
            $(document).on('input', '#caps-search-input', this.filterCapabilities.bind(this));
            $(document).on('change', '#select-all-caps', this.toggleAllCaps.bind(this));
            $(document).on('click', '#clear-logs-btn', this.clearLogs.bind(this));
            $(document).on('submit', '#restricted-caps-form', this.saveRestrictedCaps.bind(this));
            $(document).on('input', '#restricted-search-input', this.filterRestricted.bind(this));
            $(document).on('change', '#select-all-restricted', this.toggleAllRestricted.bind(this));
        },

        openEditNameModal: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var role = $btn.data('role');
            var name = $btn.data('name');

            $('#edit-role-name').val(role);
            $('#edit-display-name').val(name);
            $('#edit-name-modal').show();
        },

        openDeleteConfirmModal: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var role = $btn.data('role');

            $('#delete-role-name').val(role);
            $('#delete-role-display').text(role);
            $('#delete-confirm-input').val('');
            $('#delete-confirm-modal').show();
        },

        openEditCapsModal: function(e) {
            e.preventDefault();
            var $btn = $(e.currentTarget);
            var role = $btn.data('role');

            var $row = $btn.closest('tr[data-role="' + role + '"]');
            var isDefault = $row.find('.badge-default').length > 0;

            if (isDefault) {
                alert(roleManager.i18n.cannot_edit_default);
                return;
            }

            $('#caps-role-name').val(role);
            $('#caps-role-title').text(role);
            $('#caps-search-input').val('');
            $('.cap-row').removeClass('hidden').removeClass('inherited');
            $('.cap-checkbox').not(':disabled').prop('checked', false);

            $.ajax({
                url: roleManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'role_manager_get_role_caps',
                    nonce: roleManager.nonce,
                    role_name: role
                },
                success: function(response) {
                    if (response.success) {
                        var caps = response.data.capabilities;
                        var inherited = response.data.inherited || {};
                        var parentRole = response.data.parent_role || '';

                        $.each(inherited, function(cap, value) {
                            var $row = $('tr.cap-row[data-cap="' + escapeSelector(cap) + '"]');
                            if ($row.length) {
                                var $checkbox = $row.find('.cap-checkbox');
                                var wasDisabled = $checkbox.prop('disabled');
                                
                                $row.addClass('inherited');
                                $checkbox.prop('checked', true).prop('disabled', true);
                                
                                var $status = $row.find('.cap-status');
                                if ($status.length) {
                                    if (wasDisabled) {
                                        $status.html('<span class="badge badge-inherited">' + roleManager.i18n.inherited + '</span><span class="badge badge-restricted">' + roleManager.i18n.restricted + '</span>');
                                    } else {
                                        $status.html('<span class="badge badge-inherited">' + roleManager.i18n.inherited + '</span>');
                                    }
                                }
                            }
                        });

                        $.each(caps, function(cap, grant) {
                            var $checkbox = $('input[name="capabilities[' + escapeSelector(cap) + ']"]');
                            if ($checkbox.length && !$checkbox.prop('disabled')) {
                                $checkbox.prop('checked', grant);
                            }
                        });

                        if (parentRole) {
                            $('#caps-parent-info').show().text(roleManager.i18n.inherits_from.replace('%s', parentRole));
                        } else {
                            $('#caps-parent-info').hide();
                        }
                    }
                }
            });

            $('#edit-caps-modal').show();
        },

        closeModal: function(e) {
            if ($(e.target).hasClass('modal-overlay') || 
                $(e.target).hasClass('modal-close') || 
                $(e.target).hasClass('modal-cancel')) {
                $('.role-manager-modal').hide();
            }
        },

        saveName: function(e) {
            e.preventDefault();
            var $form = $(e.currentTarget);
            var $btn = $form.find('button[type="submit"]');

            $btn.prop('disabled', true).text(roleManager.i18n.saving);

            $.ajax({
                url: roleManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'role_manager_update_name',
                    nonce: roleManager.nonce,
                    role_name: $('#edit-role-name').val(),
                    new_name: $('#edit-display-name').val()
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || roleManager.i18n.error);
                        $btn.prop('disabled', false).text(roleManager.i18n.save);
                    }
                },
                error: function() {
                    alert(roleManager.i18n.error);
                    $btn.prop('disabled', false).text(roleManager.i18n.save);
                }
            });
        },

        resetName: function(e) {
            e.preventDefault();
            if (!confirm(roleManager.i18n.confirm_reset)) {
                return;
            }

            var $btn = $(e.currentTarget);
            var role = $btn.data('role');

            $btn.prop('disabled', true);

            $.ajax({
                url: roleManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'role_manager_reset_name',
                    nonce: roleManager.nonce,
                    role_name: role
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || roleManager.i18n.error);
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(roleManager.i18n.error);
                    $btn.prop('disabled', false);
                }
            });
        },

        createRole: function(e) {
            e.preventDefault();
            var $form = $(e.currentTarget);
            var $btn = $form.find('button[type="submit"]');

            var roleId = $('#new_role_id').val();
            var roleName = $('#new_role_name').val();
            var parentRole = $('#new_role_parent').val();

            if (!roleId || !roleName) {
                alert(roleManager.i18n.fill_required);
                return;
            }

            $btn.prop('disabled', true).text(roleManager.i18n.saving);

            $.ajax({
                url: roleManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'role_manager_create_role',
                    nonce: roleManager.nonce,
                    role_id: roleId,
                    role_name: roleName,
                    parent_role: parentRole
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || roleManager.i18n.error);
                        $btn.prop('disabled', false).text(roleManager.i18n.create_role);
                    }
                },
                error: function() {
                    alert(roleManager.i18n.error);
                    $btn.prop('disabled', false).text(roleManager.i18n.create_role);
                }
            });
        },

        deleteRole: function(e) {
            e.preventDefault();
            var $form = $(e.currentTarget);
            var $btn = $form.find('button[type="submit"]');
            var role = $('#delete-role-name').val();
            var confirm = $('#delete-confirm-input').val();

            if (confirm !== role) {
                alert(roleManager.i18n.confirm_not_match);
                return;
            }

            $btn.prop('disabled', true).text(roleManager.i18n.saving);

            $.ajax({
                url: roleManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'role_manager_delete_role',
                    nonce: roleManager.nonce,
                    role_name: role,
                    confirm: confirm
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || roleManager.i18n.error);
                        $btn.prop('disabled', false).text(roleManager.i18n.confirm_delete_btn);
                    }
                },
                error: function() {
                    alert(roleManager.i18n.error);
                    $btn.prop('disabled', false).text(roleManager.i18n.confirm_delete_btn);
                }
            });
        },

        saveCapabilities: function(e) {
            e.preventDefault();
            var $form = $(e.currentTarget);
            var $btn = $form.find('button[type="submit"]');
            var role = $('#caps-role-name').val();

            var capabilities = {};
            $form.find('.cap-checkbox:checked').each(function() {
                var name = $(this).attr('name');
                var match = name.match(/capabilities\[(.+)\]/);
                if (match) {
                    capabilities[match[1]] = true;
                }
            });

            $btn.prop('disabled', true).text(roleManager.i18n.saving);

            $.ajax({
                url: roleManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'role_manager_update_caps',
                    nonce: roleManager.nonce,
                    role_name: role,
                    capabilities: JSON.stringify(capabilities)
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || roleManager.i18n.error);
                        $btn.prop('disabled', false).text(roleManager.i18n.save_caps);
                    }
                },
                error: function() {
                    alert(roleManager.i18n.error);
                    $btn.prop('disabled', false).text(roleManager.i18n.save_caps);
                }
            });
        },

        filterCapabilities: function(e) {
            var search = $(e.currentTarget).val().toLowerCase();
            $('.cap-row').each(function() {
                var cap = $(this).data('cap').toLowerCase();
                if (cap.indexOf(search) > -1) {
                    $(this).removeClass('hidden');
                } else {
                    $(this).addClass('hidden');
                }
            });
        },

        toggleAllCaps: function(e) {
            var checked = $(e.currentTarget).prop('checked');
            $('.cap-checkbox:visible:not(:disabled)').prop('checked', checked);
        },

        clearLogs: function(e) {
            e.preventDefault();
            if (!confirm(roleManager.i18n.confirm_clear_logs)) {
                return;
            }

            var $btn = $(e.currentTarget);
            $btn.prop('disabled', true);

            $.ajax({
                url: roleManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'role_manager_clear_logs',
                    nonce: roleManager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || roleManager.i18n.error);
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert(roleManager.i18n.error);
                    $btn.prop('disabled', false);
                }
            });
        },

        saveRestrictedCaps: function(e) {
            e.preventDefault();
            var $form = $(e.currentTarget);
            var $btn = $form.find('button[type="submit"]');

            var caps = [];
            $form.find('.restricted-checkbox:checked').each(function() {
                caps.push($(this).val());
            });

            $btn.prop('disabled', true).text(roleManager.i18n.saving);

            $.ajax({
                url: roleManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'role_manager_update_restricted',
                    nonce: roleManager.nonce,
                    restricted_caps: JSON.stringify(caps)
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        $btn.prop('disabled', false).text(roleManager.i18n.save_settings);
                    } else {
                        alert(response.data.message || roleManager.i18n.error);
                        $btn.prop('disabled', false).text(roleManager.i18n.save_settings);
                    }
                },
                error: function() {
                    alert(roleManager.i18n.error);
                    $btn.prop('disabled', false).text(roleManager.i18n.save_settings);
                }
            });
        },

        filterRestricted: function(e) {
            var search = $(e.currentTarget).val().toLowerCase();
            $('.restricted-row').each(function() {
                var cap = $(this).data('cap').toLowerCase();
                if (cap.indexOf(search) > -1) {
                    $(this).removeClass('hidden');
                } else {
                    $(this).addClass('hidden');
                }
            });
        },

        toggleAllRestricted: function(e) {
            var checked = $(e.currentTarget).prop('checked');
            $('.restricted-checkbox:visible').prop('checked', checked);
        }
    };

    $(document).ready(function() {
        RoleManager.init();

        $('.cap-row').each(function() {
            var $checkbox = $(this).find('.cap-checkbox');
            var $status = $(this).find('.cap-status');
            
            if ($checkbox.prop('disabled') && !$(this).hasClass('inherited')) {
                $status.html('<span class="badge badge-restricted">' + roleManager.i18n.restricted + '</span>');
            }
        });
    });

})(jQuery);
