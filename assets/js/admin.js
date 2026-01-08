/**
 * SAP WooCommerce Sync Admin Scripts
 *
 * @package SAP_WooCommerce_Sync
 * @since   1.0.0
 */

/* global jQuery, sapWcSync */

(function ($) {
    'use strict';

    /**
     * Test SAP connection
     */
    function testConnection() {
        const $button = $('#test-connection');
        const $result = $('#connection-result');
        const $statusDot = $('.status-dot');
        const $statusText = $('.status-text');

        $button.prop('disabled', true).text(sapWcSync.strings.testing);
        $result.removeClass('success error').text('');

        $.ajax({
            url: sapWcSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sap_wc_sync_test_connection',
                nonce: sapWcSync.nonce
            },
            success: function (response) {
                if (response.success) {
                    $result.addClass('success').text(sapWcSync.strings.success);
                    $statusDot.removeClass('status-unknown status-error').addClass('status-success');
                    $statusText.text(sapWcSync.strings.success);
                } else {
                    const message = response.data && response.data.message
                        ? response.data.message
                        : 'Unknown error';
                    $result.addClass('error').text(sapWcSync.strings.error + ' ' + message);
                    $statusDot.removeClass('status-unknown status-success').addClass('status-error');
                    $statusText.text('Connection failed');
                }
            },
            error: function (xhr, status, error) {
                $result.addClass('error').text(sapWcSync.strings.error + ' ' + error);
                $statusDot.removeClass('status-unknown status-success').addClass('status-error');
                $statusText.text('Connection failed');
            },
            complete: function () {
                $button.prop('disabled', false).text('Test Connection');
            }
        });
    }

    /**
     * Trigger stock sync
     */
    function syncStockNow() {
        const $button = $('#sync-stock-now');

        if (!confirm(sapWcSync.strings.confirmSync)) {
            return;
        }

        $button.prop('disabled', true).text(sapWcSync.strings.syncing);

        $.ajax({
            url: sapWcSync.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sap_wc_sync_manual_stock_sync',
                nonce: sapWcSync.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    const message = response.data && response.data.message
                        ? response.data.message
                        : 'Stock sync failed';
                    alert(message);
                }
            },
            error: function (xhr, status, error) {
                alert('Request failed: ' + error);
            },
            complete: function () {
                $button.prop('disabled', false).text('Sync Stock Now');
            }
        });
    }

    /**
     * Initialize
     */
    $(document).ready(function () {
        // Test connection button
        $('#test-connection').on('click', testConnection);

        // Sync stock button
        $('#sync-stock-now').on('click', syncStockNow);
    });

})(jQuery);
