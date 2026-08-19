/**
 * Header Effects - Transparent to Solid on Scroll
 *
 * Reads the Elementor section/container `data-settings` object (populated by
 * the "Header Effects" controls with frontend_available => true) and applies
 * the solid background + shadow once the page scrolls past the configured
 * distance. Mirrors the approach used by Sticky Header Effects for Elementor.
 */
jQuery(function ($) {
  'use strict';

  var tahefobuEffectsReady = false;
  var tahefobuEffects = []; // collected elements + settings

  function tahefobuCollectHeaderEffects() {
    tahefobuEffects = [];

    // Scope to the header wrapper; only elements carrying the Header Effects
    // settings are processed.
    var $scope = $('#tahefobu-header').length
      ? $('#tahefobu-header')
      : $('.turbo-header-template').first();

    if (!$scope.length) {
      return;
    }

    $scope.find('.elementor-element').each(function () {
      var $el = $(this);
      var settings = $el.data('settings');

      if (!settings || settings.tahefobu_header_effects !== 'yes') {
        return;
      }

      var scrollDistance = 200;
      if (
        settings.tahefobu_effects_scroll_distance &&
        settings.tahefobu_effects_scroll_distance.size !== undefined
      ) {
        scrollDistance = parseFloat(settings.tahefobu_effects_scroll_distance.size) || 200;
      }

      tahefobuEffects.push({
        $el: $el,
        settings: settings,
        scrollDistance: scrollDistance
      });
    });
  }

  function tahefobuApplySolid(entry) {
    var $el = entry.$el;
    var s = entry.settings;
    $el.removeClass('tahefobu-effects-transparent-yes');
    if (s.tahefobu_effects_background) {
      $el.css('background-color', s.tahefobu_effects_background);
    }
    if (s.tahefobu_effects_shadow === 'yes') {
      $el.addClass('tahefobu-effects-solid-shadow');
    }
  }

  function tahefobuApplyTransparent(entry) {
    var $el = entry.$el;
    var s = entry.settings;
    if (s.tahefobu_transparent_header === 'yes') {
      $el.addClass('tahefobu-effects-transparent-yes');
    }
    $el.css('background-color', '');
    $el.removeClass('tahefobu-effects-solid-shadow');
  }

  function tahefobuUpdateEffects() {
    var y = window.pageYOffset || document.documentElement.scrollTop;
    $.each(tahefobuEffects, function (i, entry) {
      if (y >= entry.scrollDistance) {
        tahefobuApplySolid(entry);
      } else {
        tahefobuApplyTransparent(entry);
      }
    });
  }

  function tahefobuInitHeaderEffects() {
    tahefobuCollectHeaderEffects();
    tahefobuUpdateEffects();

    if (tahefobuEffectsReady) {
      return;
    }

    // Single window handler — bind once, iterate all collected elements.
    $(window).on('scroll.tahefobuEffects resize.tahefobuEffects', tahefobuUpdateEffects);
    tahefobuEffectsReady = true;
  }

  tahefobuInitHeaderEffects();

  // Re-evaluate once images/scripts finish loading so heights are final and
  // any lazily-rendered Elementor sections have their data-settings in place.
  // tahefobuEffectsReady guards against re-binding the scroll handler, so only
  // the element collection and an immediate state update run on this second call.
  $(window).on('load', function () {
    tahefobuInitHeaderEffects();
  });
});
