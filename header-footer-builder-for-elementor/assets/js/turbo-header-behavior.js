jQuery(function ($) {
  // Skip behavior only inside our own CPT template editors
  if ( $('body').is('.tahefobu-header-template-editor, .tahefobu-footer-template-editor') ) return;

  // Inside the Elementor editor preview iframe, just make the header visible and stop.
  if ( window.location.search.indexOf('elementor-preview') !== -1 ) {
    $('#tahefobu-header, .turbo-header-template').first().addClass('tahefobu-ready');
    return;
  }

  // Prefer the new wrapper if present, else fall back to your existing class
  var $wrap = $('#tahefobu-header');
  if (!$wrap.length) {
    $wrap = $('.turbo-header-template').first();
    if (!$wrap.length) return;
  }
  $wrap.addClass('tahefobu-ready');

  // Read sticky/animation flags from data attributes
  var sticky = $wrap.data('sticky');
  var anim   = $wrap.data('animation');
  // Back-compat if data-* not present: infer from classes
  sticky = String(sticky === undefined ? $wrap.hasClass('ta-sticky-header') : sticky) === '1';
  anim   = String(anim   === undefined ? $wrap.hasClass('ta-header-scroll-animation') : anim) === '1';

  // WordPress pushes body down by the admin bar height via margin-top on <body>.
  // For sticky headers, the body margin already accounts for the admin bar height,
  // so we keep the sticky top at 0 to avoid double-offsetting the header.
  // For animation-only mode, we still position the element directly under the bar.
  var adminBarH = $('#wpadminbar').length ? $('#wpadminbar').outerHeight() : 0;

  if (anim && !sticky) {
    $wrap.css({
      position: 'sticky',
      top: adminBarH + 'px'
    });
  } else {
    $wrap.css('--ta-sticky-top', '0px');
  }

  // ── Sticky-only or Sticky+Animation mode ─────────────────────────────────
  // CSS already sets top: var(--ta-sticky-top) which we set above.
  // The fixed fallback (ta-sticky-active) also uses that var.

  var $spacer = null, headerTop = 0, headerH = 0;

  function recalc() {
    headerH   = $wrap.outerHeight();
    headerTop = ($spacer && $spacer.is(':visible')) ? $spacer.offset().top : $wrap.offset().top;
  }

  function onScrollSticky() {
    var sc = window.pageYOffset || document.documentElement.scrollTop;

    if (sc > headerTop) {
      if (!$wrap.hasClass('ta-sticky-active')) {
        headerH = $wrap.outerHeight();
        if (!$spacer) $spacer = $('<div class="ta-header-spacer" />').insertBefore($wrap).hide();
        $spacer.height(headerH).show();   // prevent layout jump
        $wrap.addClass('ta-sticky-active');
      }
    } else {
      if ($wrap.hasClass('ta-sticky-active')) {
        $wrap.removeClass('ta-sticky-active');
        if ($spacer) $spacer.hide();
      }
    }
  }

  if (sticky) {
    recalc();
    $(window).on('scroll.taSticky resize.taSticky', function () {
      recalc();
      onScrollSticky();
    });
    onScrollSticky();
  }

  // ── Scroll animation ─────────────────────────────────────────────────────
  if (anim) {
    var lastY = window.pageYOffset || document.documentElement.scrollTop;
    var hideThreshold = 80; // px scrolled down before hiding

    // Start fully visible
    $wrap.removeClass('ta-scroll-down ta-header-hide ta-header-hidden')
         .addClass('ta-scroll-up ta-header-show');

    $(window).on('scroll.taAnim', function () {
      var y    = window.pageYOffset || document.documentElement.scrollTop;
      var diff = y - lastY;

      if (diff > 0 && y > hideThreshold) {
        // Scrolling DOWN — hide header
        $wrap.addClass('ta-scroll-down ta-header-hide ta-header-hidden')
             .removeClass('ta-scroll-up ta-header-show');
      } else if (diff < 0) {
        // Scrolling UP — show header
        $wrap.addClass('ta-scroll-up ta-header-show')
             .removeClass('ta-scroll-down ta-header-hide ta-header-hidden');
      }

      lastY = y;
    });
  }
});
