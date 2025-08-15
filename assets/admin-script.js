jQuery(document).ready(function($) {

    // ===== CAMPAIGNS MANAGEMENT =====

    // Handle campaign form submission
    $('#charity-campaign-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var $message = $('#charity-message');
        var formAction = $('#form_action').val();

        // Get description from TinyMCE if available
        var description = '';
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('campaign_description')) {
            description = tinyMCE.get('campaign_description').getContent();
        } else {
            description = $('#campaign_description').val();
        }

        // Validate required fields
        if (!$('#campaign_title').val()) {
            showMessage($message, 'Vui lòng nhập tên chiến dịch', 'error');
            return;
        }

        // Prepare data
        var formData = {
            action: formAction === 'update' ? 'charity_update_campaign' : 'charity_create_campaign',
            nonce: charity_ajax.nonce,
            campaign_id: $('#campaign_id').val(),
            title: $('#campaign_title').val(),
            description: description,
            short_desc: $('#campaign_short_desc').val(),
            categories: $('#campaign_category').val(),
            image_id: $('#campaign_image_id').val(),
            goal: $('#campaign_goal').val() || 0,
            status: $('#campaign_status').val()
        };

        // Add raised amount for update action
        if (formAction === 'update' && $('#campaign_raised').length) {
            formData.raised = $('#campaign_raised').val() || 0;
        }

        // Show loading state
        $submitBtn.prop('disabled', true).html(charity_ajax.loading_text + ' <span class="charity-loading"></span>');

        // Send AJAX request
        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage($message, response.data.message, 'success');
                    if (response.data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1500);
                    }
                } else {
                    showMessage($message, response.data || charity_ajax.error_text, 'error');
                }
            },
            error: function() {
                showMessage($message, 'Có lỗi xảy ra khi kết nối với server', 'error');
            },
            complete: function() {
                var buttonText = formAction === 'update' ? 'Cập nhật chiến dịch' : 'Tạo chiến dịch';
                $submitBtn.prop('disabled', false).html(buttonText);
            }
        });
    });

    // Handle delete campaign
    $(document).on('click', '.delete-campaign-btn', function(e) {
        e.preventDefault();

        if (!confirm(charity_ajax.confirm_delete)) {
            return;
        }

        var $btn = $(this);
        var campaignId = $btn.data('campaign-id');
        var $row = $btn.closest('tr');

        $btn.prop('disabled', true).html('<span class="charity-loading"></span>');

        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'charity_delete_campaign',
                nonce: charity_ajax.nonce,
                campaign_id: campaignId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                        // Check if table is empty
                        if ($('#campaigns-tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data || charity_ajax.error_text);
                }
            },
            error: function() {
                alert('Có lỗi xảy ra khi kết nối với server');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
            }
        });
    });

    // Filter campaigns
    $('#filter_campaigns_btn').on('click', function() {
        loadCampaigns();
    });

    // Refresh campaigns
    $('#refresh_campaigns_btn').on('click', function() {
        loadCampaigns();
    });

    // Load campaigns with filters
    function loadCampaigns() {
        var $tbody = $('#campaigns-tbody');
        var status = $('#filter_campaign_status').val();

        $tbody.html('<tr><td colspan="10" style="text-align: center;">Đang tải... <span class="charity-loading"></span></td></tr>');

        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'charity_load_campaigns',
                nonce: charity_ajax.nonce,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    $tbody.html(response.data.html);
                } else {
                    $tbody.html('<tr><td colspan="10" style="text-align: center; color: red;">Có lỗi xảy ra</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="10" style="text-align: center; color: red;">Lỗi kết nối</td></tr>');
            }
        });
    }

    // Handle recalculate raised amount button
    $('#recalculate_raised_btn').on('click', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var campaignId = $btn.data('campaign-id');
        var $input = $('#campaign_raised');
        var $info = $('#raised-calculation-info');
        var $details = $('#raised-details');

        if (!campaignId) {
            alert('Lỗi: Không tìm thấy ID chiến dịch');
            return;
        }

        // Show loading state
        $btn.prop('disabled', true).html('<span class="charity-loading"></span> Đang tính toán...');

        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'charity_recalculate_raised',
                nonce: charity_ajax.nonce,
                campaign_id: campaignId
            },
            success: function(response) {
                if (response.success) {
                    // Update the input field
                    $input.val(response.data.total_raised);

                    // Show calculation details
                    var detailsHtml = '<p><strong>' + response.data.message + '</strong></p>';
                    detailsHtml += '<p>Tổng số tiền: ' + formatCurrency(response.data.total_raised) + '</p>';

                    if (response.data.details && response.data.details.length > 0) {
                        detailsHtml += '<p><strong>Danh sách ủng hộ gần nhất:</strong></p><ul>';
                        response.data.details.forEach(function(detail) {
                            detailsHtml += '<li>#' + detail.order_id + ' - ' + detail.donor_name + ': ' +
                                         formatCurrency(detail.amount) + ' (' + detail.date + ')</li>';
                        });
                        detailsHtml += '</ul>';

                        if (response.data.donation_count > 10) {
                            detailsHtml += '<p><em>... và ' + (response.data.donation_count - 10) + ' khoản ủng hộ khác</em></p>';
                        }
                    } else {
                        detailsHtml += '<p><em>Chưa có khoản ủng hộ nào được hoàn thành.</em></p>';
                    }

                    $details.html(detailsHtml);
                    $info.slideDown();

                    // Flash the input to show it changed
                    $input.addClass('highlight-change');
                    setTimeout(function() {
                        $input.removeClass('highlight-change');
                    }, 2000);

                } else {
                    alert(response.data || 'Có lỗi xảy ra khi tính toán');
                }
            },
            error: function() {
                alert('Có lỗi xảy ra khi kết nối với server');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update-alt"></span> Tính lại từ ủng hộ');
            }
        });
    });

    // ===== DONATIONS MANAGEMENT =====

    // Handle donation form submission
    $('#charity-donation-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var $message = $('#charity-message');
        var formAction = $('#form_action').val();

        // Validate required fields
        if (!$('#donation_campaign').val() && formAction === 'create') {
            showMessage($message, 'Vui lòng chọn chiến dịch', 'error');
            return;
        }

        if (!$('#donor_name').val()) {
            showMessage($message, 'Vui lòng nhập tên người ủng hộ', 'error');
            return;
        }

        if (!$('#donation_amount').val() || $('#donation_amount').val() < 1000) {
            showMessage($message, 'Số tiền ủng hộ phải tối thiểu 1,000 VNĐ', 'error');
            return;
        }

        // Prepare data
        var formData = {
            action: formAction === 'update' ? 'charity_update_donation' : 'charity_add_donation',
            nonce: charity_ajax.nonce,
            donation_id: $('#donation_id').val(),
            campaign_id: $('#donation_campaign').val() || $('input[name="donation_campaign"]').val(),
            donor_name: $('#donor_name').val(),
            donor_email: $('#donor_email').val(),
            donor_phone: $('#donor_phone').val(),
            donor_address: $('#donor_address').val(),
            amount: $('#donation_amount').val(),
            method: $('#donation_method').val(),
            status: $('#donation_status').val(),
            note: $('#donation_note').val(),
            is_anonymous: $('#is_anonymous').is(':checked') ? 1 : 0
        };

        // Show loading state
        $submitBtn.prop('disabled', true).html(charity_ajax.loading_text + ' <span class="charity-loading"></span>');

        // Send AJAX request
        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage($message, response.data.message, 'success');
                    if (response.data.redirect_url) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1500);
                    }
                } else {
                    showMessage($message, response.data || charity_ajax.error_text, 'error');
                }
            },
            error: function() {
                showMessage($message, 'Có lỗi xảy ra khi kết nối với server', 'error');
            },
            complete: function() {
                var buttonText = formAction === 'update' ? 'Cập nhật ủng hộ' : 'Thêm ủng hộ';
                $submitBtn.prop('disabled', false).html(buttonText);
            }
        });
    });

    // Handle delete donation
    $(document).on('click', '.delete-donation-btn', function(e) {
        e.preventDefault();

        if (!confirm(charity_ajax.confirm_delete)) {
            return;
        }

        var $btn = $(this);
        var donationId = $btn.data('donation-id');
        var $row = $btn.closest('tr');

        $btn.prop('disabled', true).html('<span class="charity-loading"></span>');

        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'charity_delete_donation',
                nonce: charity_ajax.nonce,
                donation_id: donationId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                        // Check if table is empty
                        if ($('#donations-tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data || charity_ajax.error_text);
                }
            },
            error: function() {
                alert('Có lỗi xảy ra khi kết nối với server');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
            }
        });
    });

    // Filter donations
    $('#filter_donations_btn').on('click', function() {
        loadDonations();
    });

    // Reset filters
    $('#reset_filter_btn').on('click', function() {
        $('#filter_donation_campaign').val('');
        $('#filter_donation_status').val('');
        $('#filter_date_from').val('');
        $('#filter_date_to').val('');
        loadDonations();
    });

    // Refresh donations
    $('#refresh_donations_btn').on('click', function() {
        loadDonations();
    });

    // Export donations to CSV
    $('#export_donations_btn').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="charity-loading"></span> Đang xuất...');

        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'charity_export_donations',
                nonce: charity_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    var blob = new Blob([atob(response.data.csv)], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);
                    var link = document.createElement('a');
                    link.href = url;
                    link.download = response.data.filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                } else {
                    alert(response.data || 'Có lỗi xảy ra khi xuất dữ liệu');
                }
            },
            error: function() {
                alert('Có lỗi xảy ra khi kết nối với server');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Xuất Excel');
            }
        });
    });

    // Load donations with filters
    function loadDonations() {
        var $tbody = $('#donations-tbody');

        var filters = {
            campaign_id: $('#filter_donation_campaign').val(),
            status: $('#filter_donation_status').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        };

        $tbody.html('<tr><td colspan="10" style="text-align: center;">Đang tải... <span class="charity-loading"></span></td></tr>');

        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'charity_load_donations',
                nonce: charity_ajax.nonce,
                ...filters
            },
            success: function(response) {
                if (response.success) {
                    $tbody.html(response.data.html);
                } else {
                    $tbody.html('<tr><td colspan="10" style="text-align: center; color: red;">Có lỗi xảy ra</td></tr>');
                }
            },
            error: function() {
                $tbody.html('<tr><td colspan="10" style="text-align: center; color: red;">Lỗi kết nối</td></tr>');
            }
        });
    }

    // ===== IMAGE UPLOAD =====

    var mediaUploader;

    $('#upload_image_button').on('click', function(e) {
        e.preventDefault();

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Chọn ảnh đại diện',
            button: {
                text: 'Sử dụng ảnh này'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#campaign_image_id').val(attachment.id);
            $('#campaign_image_preview').html('<img src="' + attachment.url + '" alt="Preview" />');
            $('#remove_image_button').show();
        });

        mediaUploader.open();
    });

    // Remove image
    $('#remove_image_button').on('click', function(e) {
        e.preventDefault();
        $('#campaign_image_id').val('');
        $('#campaign_image_preview').html('');
        $(this).hide();
    });

    // ===== UTILITY FUNCTIONS =====

    // Show message function
    function showMessage($element, message, type) {
        $element.removeClass('success error').addClass(type).html(message);

        // Auto hide after 5 seconds
        setTimeout(function() {
            $element.fadeOut(function() {
                $(this).html('').removeClass('success error').show();
            });
        }, 5000);
    }

    // Format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    }

    // Auto-refresh donations list every 60 seconds (optional)
    if ($('#donations-tbody').length && window.location.href.indexOf('action=') === -1) {
        setInterval(function() {
            loadDonations();
        }, 60000);
    }

    // Initialize tooltips if available
    if ($.fn.tooltip) {
        $('.button-small').tooltip();
    }

    // Handle enter key in filter inputs
    $('#filter_date_from, #filter_date_to').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            loadDonations();
        }
    });

    // ===== SETTINGS MANAGEMENT =====

    // Handle settings form submission
    $('#charity-settings-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var $message = $('#charity-message');

        // Show loading state
        $submitBtn.prop('disabled', true).html(charity_ajax.loading_text + ' <span class="charity-loading"></span>');

        // Prepare form data
        var formData = {
            action: 'charity_save_settings',
            nonce: charity_ajax.nonce,
            import_all_products: $('#import_all_products').is(':checked') ? 'yes' : 'no',
            auto_set_campaign: $('#auto_set_campaign').is(':checked') ? 'yes' : 'no',
            enable_anonymous_donations: $('#enable_anonymous_donations').is(':checked') ? 'yes' : 'no',
            default_goal_amount: $('#default_goal_amount').val() || 1000000
        };

        // Send AJAX request
        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showMessage($message, response.data.message, 'success');
                } else {
                    showMessage($message, response.data || charity_ajax.error_text, 'error');
                }
            },
            error: function() {
                showMessage($message, 'Có lỗi xảy ra khi kết nối với server', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html('Lưu cài đặt');
            }
        });
    });

    // Handle import all products checkbox
    $('#import_all_products').on('change', function() {
        var $importOptions = $('#import_options');
        if ($(this).is(':checked')) {
            $importOptions.slideDown();
        } else {
            $importOptions.slideUp();
        }
    });

    // Handle start import button
    $('#start_import_btn').on('click', function() {
        var $btn = $(this);
        var $progress = $('#import_progress');
        var $progressBar = $('#import_progress_bar');
        var $status = $('#import_status');

        if (!confirm('Bạn có chắc chắn muốn chuyển đổi tất cả sản phẩm thành chiến dịch từ thiện?')) {
            return;
        }

        // Show progress section
        $progress.show();
        $btn.prop('disabled', true);

        // Start import process
        importProducts(0, $progressBar, $status, $btn);
    });

    // Function to import products in batches
    function importProducts(offset, $progressBar, $status, $btn) {
        $.ajax({
            url: charity_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'charity_import_products',
                nonce: charity_ajax.nonce,
                offset: offset
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;

                    // Update status
                    $status.html('<p>' + data.message + '</p>');

                    if (data.errors && data.errors.length > 0) {
                        $status.append('<div style="color: #dc3545; margin-top: 10px;"><strong>Lỗi:</strong><ul></ul></div>');
                        data.errors.forEach(function(error) {
                            $status.find('ul').append('<li>' + error + '</li>');
                        });
                    }

                    if (!data.completed) {
                        // Calculate progress
                        var totalProcessed = offset + data.processed;
                        var totalRemaining = data.remaining;
                        var totalProducts = totalProcessed + totalRemaining;
                        var progressPercent = Math.round((totalProcessed / totalProducts) * 100);

                        // Update progress bar
                        $progressBar.css('width', progressPercent + '%').text(progressPercent + '%');

                        // Continue with next batch
                        setTimeout(function() {
                            importProducts(offset + data.processed, $progressBar, $status, $btn);
                        }, 1000);
                    } else {
                        // Import completed
                        $progressBar.css('width', '100%').text('100%');
                        $status.append('<p style="color: #28a745; font-weight: bold;">Hoàn thành! Tất cả sản phẩm đã được chuyển đổi thành chiến dịch từ thiện.</p>');
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Hoàn thành');

                        // Reload page after 3 seconds to show updated stats
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    }
                } else {
                    $status.html('<p style="color: #dc3545;">Lỗi: ' + (response.data || 'Có lỗi xảy ra') + '</p>');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $status.html('<p style="color: #dc3545;">Lỗi kết nối với server</p>');
                $btn.prop('disabled', false);
            }
        });
    }

    // Handle reset settings button
    $('#reset_settings_btn').on('click', function() {
        if (!confirm('Bạn có chắc chắn muốn khôi phục cài đặt mặc định?')) {
            return;
        }

        // Reset to default values
        $('#import_all_products').prop('checked', false).trigger('change');
        $('#auto_set_campaign').prop('checked', false);
        $('#enable_anonymous_donations').prop('checked', true);
        $('#default_goal_amount').val(1000000);

        showMessage($('#charity-message'), 'Đã khôi phục cài đặt mặc định', 'success');
    });
});
