/**
 * Mega Menu admin UI (Appearance → Menus).
 *
 * Handles the tabbed settings modal, template selection + creation, the
 * menu-level metabox, and save/load via the tahefobu/v1 REST endpoints.
 */
(function ($) {
	'use strict';

	var iconPicker = null;

	function openSettingsModal(itemId) {
		$('#tahefobu-menu-modal-menu-id').val(itemId);
		$('#tahefobu-menu-modal-menu-has-child').val(0);

		// Reset to the Content tab.
		$('.tahefobu-megamenu-tabs > li').removeClass('tahefobu-active');
		$('.tahefobu-megamenu-tabs > li:first').addClass('tahefobu-active');
		$('.tahefobu-tab-pane').removeClass('tahefobu-active');
		$('#tahefobu-tab-content').addClass('tahefobu-active');

		$('#tahefobu-megamenu-modal').addClass('is-open');

		loadSettings(itemId);
	}

	function closeSettingsModal() {
		$('#tahefobu-megamenu-modal').removeClass('is-open');
	}

	function loadSettings(itemId) {
		$.ajax({
			url: tahefobuMegaMenu.restUrl + 'megamenu/get_menuitem_settings',
			type: 'GET',
			data: { menu_id: itemId },
			headers: { 'X-WP-Nonce': tahefobuMegaMenu.nonce },
			dataType: 'json',
			success: function (data) {
				data = data || {};

				$('#tahefobu-menu-item-enable').prop('checked', parseInt(data.menu_enable, 10) === 1);
				$('#tahefobu-menu-icon-color-field').wpColorPicker('color', data.menu_icon_color || '');
				$('#tahefobu-menu-icon-field').val(data.menu_icon || '');
				$('#tahefobu-menu-vertical-menu-width-field').val(data.vertical_menu_width || '');

				// Mobile submenu content type.
				$('#tahefobu-mobile-submenu-content-type input[name=content_type]').prop('checked', false);
				var contentType = data.mobile_submenu_content_type || 'builder_content';
				$('#tahefobu-mobile-submenu-content-type input[value=' + contentType + ']').prop('checked', true);

				// Position type.
				$('#tahefobu-vertical-megamenu-position-type input[name=position_type]').prop('checked', false);
				var positionType = data.vertical_megamenu_position_type || 'relative_position';
				$('#tahefobu-vertical-megamenu-position-type input[value=' + positionType + ']').prop('checked', true);

				// Ajax load.
				$('#tahefobu-enable-ajax-load input[name=megamenu_ajax_load]').prop('checked', false);
				var ajaxLoad = data.megamenu_ajax_load || 'no';
				$('#tahefobu-enable-ajax-load input[value=' + ajaxLoad + ']').prop('checked', true);

				// Width type.
				$('#tahefobu-megamenu-width-type input[name=width_type]').prop('checked', false);
				var widthType = data.megamenu_width_type || 'default_width';
				$('#tahefobu-megamenu-width-type input[value=' + widthType + ']').prop('checked', true);

				// Template.
				$('#tahefobu-menu-template-field').val(data.template || '');

				if (iconPicker) {
					iconPicker.refreshPicker();
				}

				$('#tahefobu-menu-item-enable').trigger('change');
				toggleMenuWidth();
			}
		});
	}

	function toggleMenuWidth() {
		if ($('#width_type_custom').is(':checked')) {
			$('.tahefobu-menu-width-container').addClass('is-enabled');
		} else {
			$('.tahefobu-menu-width-container').removeClass('is-enabled');
		}
	}

	function updateStatus(itemId, enabled) {
		var $status = $('.tahefobu-megamenu-trigger[data-item-id="' + itemId + '"]').siblings('.tahefobu-megamenu-status');
		if (!$status.length) {
			return;
		}
		if (enabled) {
			$status.addClass('is-enabled').removeClass('is-disabled').text('Enabled');
		} else {
			$status.addClass('is-disabled').removeClass('is-enabled').text('Disabled');
		}
	}

	function collectSettings() {
		return {
			menu_id: parseInt($('#tahefobu-menu-modal-menu-id').val(), 10) || 0,
			menu_has_child: $('#tahefobu-menu-modal-menu-has-child').val(),
			menu_enable: $('#tahefobu-menu-item-enable').is(':checked') ? 1 : 0,
			menu_icon: $('#tahefobu-menu-icon-field').val(),
			menu_icon_color: $('#tahefobu-menu-icon-color-field').val(),
			mobile_submenu_content_type: $('#tahefobu-mobile-submenu-content-type input[name=content_type]:checked').val(),
			vertical_megamenu_position_type: $('#tahefobu-vertical-megamenu-position-type input[name=position_type]:checked').val(),
			vertical_menu_width: $('#tahefobu-menu-vertical-menu-width-field').val(),
			megamenu_width_type: $('#tahefobu-megamenu-width-type input[name=width_type]:checked').val(),
			megamenu_ajax_load: $('#tahefobu-enable-ajax-load input[name=megamenu_ajax_load]:checked').val(),
			template: parseInt($('#tahefobu-menu-template-field').val(), 10) || 0
		};
	}

	$(function () {
		// Color pickers.
		$('.tahefobu-menu-wpcolor-picker').wpColorPicker();

		// Icon picker.
		iconPicker = $('#tahefobu-menu-icon-field').fontIconPicker({
			emptyIcon: false
		});

		// Menu-level metabox (before the menu editor).
		var metabox = '<fieldset class="menu-settings-group" id="tahefobu-options-megamenu">'
			+ '<legend class="menu-settings-group-name">Turbo Mega Menu</legend>'
			+ '<div class="menu-settings-input checkbox-input">'
			+ '<input name="tahefobu_megamenu_is_enabled" type="checkbox" id="tahefobu-menu-metabox-input-is-enabled" value="1"'
			+ (tahefobuMegaMenu.megamenuIsEnabled === '1' ? ' checked' : '') + '>'
			+ '<label for="tahefobu-menu-metabox-input-is-enabled">Enable this menu for Megamenu content</label>'
			+ '<p class="notice notice-warning" style="margin-top:10px;padding:10px;background:#f3f3f3;">After enabling this, use the Turbo Mega Menu widget to show the mega menu.</p>'
			+ '</div></fieldset>';
		$('#post-body-content').prepend(metabox);

		// Open modal.
		$(document).on('click', '.tahefobu-megamenu-trigger', function (e) {
			e.preventDefault();
			openSettingsModal($(this).data('item-id'));
		});

		// Tabs.
		$(document).on('click', '.tahefobu-tab-link', function (e) {
			e.preventDefault();
			var tab = $(this).data('tab');
			$('.tahefobu-megamenu-tabs > li').removeClass('tahefobu-active');
			$(this).parent('li').addClass('tahefobu-active');
			$('.tahefobu-tab-pane').removeClass('tahefobu-active');
			$('#tahefobu-tab-' + tab).addClass('tahefobu-active');
		});

		// Enable switch.
		$(document).on('change', '#tahefobu-menu-item-enable', function () {
			var checked = $(this).is(':checked');
			$('#tahefobu-menu-builder-warper').toggleClass('is-enabled', checked);
			$('#tahefobu-menu-template-field').prop('disabled', !checked);
			$('#tahefobu-menu-create-template').prop('disabled', !checked);
		});

		// Width type radios.
		$(document).on('change', '#tahefobu-megamenu-width-type input[name=width_type]', toggleMenuWidth);

		// Close buttons.
		$(document).on('click', '.tahefobu-btn-modal-close', closeSettingsModal);
		$(document).on('click', '.tahefobu-megamenu-modal-backdrop', closeSettingsModal);
		$(document).on('keyup', function (e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				closeSettingsModal();
			}
		});

		// Save.
		$(document).on('click', '.tahefobu-menu-item-save', function () {
			var $btn = $(this);
			var $spinner = $btn.siblings('.spinner');
			$spinner.addClass('is-active');

			var settings = collectSettings();

			$.ajax({
				url: tahefobuMegaMenu.restUrl + 'megamenu/save_menuitem_settings',
				type: 'POST',
				contentType: 'application/json',
				data: JSON.stringify({ settings: settings }),
				headers: { 'X-WP-Nonce': tahefobuMegaMenu.nonce },
				dataType: 'json',
				success: function () {
					$spinner.removeClass('is-active');
					closeSettingsModal();
					updateStatus(settings.menu_id, settings.menu_enable === 1);
				},
				error: function () {
					$spinner.removeClass('is-active');
				}
			});
		});

		// Create Mega Menu Template — create a new Elementor template, select
		// it in the dropdown, and open it in the Elementor editor (new tab).
		$(document).on('click', '#tahefobu-menu-create-template', function () {
			var $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: tahefobuMegaMenu.restUrl + 'megamenu/create_template',
				type: 'POST',
				contentType: 'application/json',
				data: JSON.stringify({ title: 'Mega Menu Template' }),
				headers: { 'X-WP-Nonce': tahefobuMegaMenu.nonce },
				dataType: 'json',
				success: function (res) {
					$btn.prop('disabled', false);
					if (res && res.id) {
						var $select = $('#tahefobu-menu-template-field');
						if (!$select.find('option[value="' + res.id + '"]').length) {
							$select.append($('<option>', { value: res.id, text: res.title || 'Mega Menu Template' }));
						}
						$select.val(res.id);
						if (res.url) {
							window.open(res.url, '_blank');
						}
					}
				},
				error: function () {
					$btn.prop('disabled', false);
				}
			});
		});
	});
})(jQuery);
