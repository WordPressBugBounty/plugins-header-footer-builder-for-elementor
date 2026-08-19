/**
 * Mega Menu widget — click trigger, custom-width panel positioning, and
 * responsive hamburger toggle for mobile.
 *
 * Breakpoint switching is handled by CSS media queries (via the widget's
 * `tahefobu-mega-bp-*` prefix classes), so no JS width detection is needed —
 * the behavior is identical in the Elementor editor preview and the frontend.
 */
jQuery(function ($) {
  'use strict';

  // ── Hamburger toggle (mobile) ──────────────────────────────────────────
  $(document).on('click.tahefobuMegaToggle', '.tahefobu-mega-toggle', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $container = $(this).closest('.tahefobu-mega-menu-container');
    var $menu = $container.find('.tahefobu-mega-mobile-menu');
    var expanded = $(this).attr('aria-expanded') === 'true';

    if ($menu.length) {
      tahefobuApplyMobileWidth();
      $menu.slideToggle(250);
    }
    $(this).attr('aria-expanded', expanded ? 'false' : 'true');
    $container.toggleClass('tahefobu-mega-open');
  });

  // Close the mobile menu when clicking a regular link inside it. Dropdown
  // toggles are skipped — they open/close submenus instead of closing the menu.
  $(document).on('click.tahefobuMegaToggle', '.tahefobu-mega-mobile-menu a', function () {
    if ($(this).hasClass('tahefobu-dropdown-toggle')) {
      return;
    }
    var $container = $(this).closest('.tahefobu-mega-menu-container');
    var $toggle = $container.find('.tahefobu-mega-toggle');
    $container.find('.tahefobu-mega-mobile-menu').slideUp(200);
    $container.removeClass('tahefobu-mega-open');
    if ($toggle.length) {
      $toggle.attr('aria-expanded', 'false');
    }
  });

  // Mobile dropdown accordion for items with children / builder-content panels.
  $(document).on('click.tahefobuMegaToggle', '.tahefobu-mega-mobile-menu .tahefobu-dropdown-toggle', function (e) {
    var $li = $(this).parent('li');
    var $sub = $li.children('.tahefobu-dropdown, .tahefobu-megamenu-panel');
    if (!$sub.length) {
      return;
    }
    e.preventDefault();
    $li.toggleClass('tahefobu-mega-sub-open');
    // Drive the animation from the class state (not slideToggle) so the
    // inline display always matches the sub-open class and never desyncs.
    if ($li.hasClass('tahefobu-mega-sub-open')) {
      $sub.stop(true, true).slideDown(200);
    } else {
      $sub.stop(true, true).slideUp(200);
    }
  });

  // ── Click-trigger mega menus (desktop) ─────────────────────────────────
  $(document).on('click.tahefobuMega', '.tahefobu-mega-trigger-click .tahefobu-mega-desktop-menu .tahefobu-megamenu-has > .tahefobu-menu-nav-link', function (e) {
    e.preventDefault();
    var $li = $(this).parent('li.tahefobu-megamenu-has');
    var $container = $li.closest('.tahefobu-mega-menu-container');
    $container.find('.tahefobu-mega-desktop-menu .tahefobu-megamenu-has').not($li).removeClass('tahefobu-mega-open');
    $li.toggleClass('tahefobu-mega-open');
  });

  $(document).on('click.tahefobuMega', function (e) {
    if (!$(e.target).closest('.tahefobu-mega-trigger-click').length) {
      $('.tahefobu-mega-trigger-click .tahefobu-megamenu-has').removeClass('tahefobu-mega-open');
    }
  });

  $(document).on('keyup.tahefobuMega', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      $('.tahefobu-mega-trigger-click .tahefobu-megamenu-has').removeClass('tahefobu-mega-open');
    }
  });

  // ── Panel width ────────────────────────────────────────────────────────
  // Panel width is now fully driven by the widget's responsive "Panel Width"
  // control (CSS). The old per-item data-vertical-menu inline width is no
  // longer applied, so it cannot override the widget setting on desktop.

  // ── Full-width mobile menu: anchor to the viewport left edge ───────────
  // The container can collapse to the hamburger's width inside flex headers,
  // so a `width:100%` mobile dropdown would be clipped to that narrow box.
  // Stretch it to the full section width and pull it to the viewport's left.
  function tahefobuApplyMobileWidth() {
    $('.tahefobu-mega-menu-container.tahefobu-mega-responsive').each(function () {
      var $container = $(this);
      var $menu = $container.children('.tahefobu-mega-mobile-menu');

      if (!$menu.length) {
        return;
      }

      var $scope = $container.closest('.elementor-element');
      if (!$scope.length || !$scope.hasClass('tahefobu-mega-mobile-menu-full-width')) {
        $menu.css({ 'width': '', 'left': '' });
        return;
      }

      var $topSection = $scope.closest('.elementor-top-section');
      var containerOffset = $container.offset();

      if (!containerOffset) {
        return;
      }

      var mWidth = $topSection.length ? $topSection.outerWidth() : $(window).outerWidth();
      var mPosition = containerOffset.left;

      $menu.css({
        'width': mWidth + 'px',
        'left': -mPosition + 'px'
      });
    });
  }

  tahefobuApplyMobileWidth();
  $(window).on('resize.tahefobuMegaMobile', tahefobuApplyMobileWidth);

  // ── Desktop panel alignment relative to the viewport ───────────────────
  // Panel Width uses vw/px (screen-relative), so alignment is also anchored
  // to the viewport: left = screen left, center = screen center, right =
  // screen right (instead of the narrow menu item).
  function tahefobuApplyPanelAlign() {
    $('.tahefobu-mega-menu-container').each(function () {
      var $container = $(this);
      var $scope = $container.closest('.elementor-element');
      if (!$scope.length) {
        return;
      }

      var viewportWidth = $(window).outerWidth();

      $container.find('.tahefobu-mega-desktop-menu .tahefobu-megamenu-has > .tahefobu-megamenu-panel').each(function () {
        var $panel = $(this);
        var $li = $panel.parent();
        var liOffset = $li.offset();
        if (!liOffset) {
          return;
        }

        var liLeft = liOffset.left;
        var liRight = viewportWidth - (liOffset.left + $li.outerWidth());

        if ($scope.hasClass('tahefobu-mega-panel-align-center')) {
          $panel.css({ 'left': (viewportWidth / 2 - liLeft) + 'px', 'right': 'auto' });
        } else if ($scope.hasClass('tahefobu-mega-panel-align-right')) {
          $panel.css({ 'left': 'auto', 'right': -liRight + 'px' });
        } else {
          $panel.css({ 'left': -liLeft + 'px', 'right': 'auto' });
        }
      });
    });
  }

  tahefobuApplyPanelAlign();
  $(window).on('resize.tahefobuMegaAlign', tahefobuApplyPanelAlign);


  // ── Ajax-loaded megamenu content ────────────────────────────────────────
  function tahefobuLoadMegamenu($li) {
    var $holder = $li.find('.tahefobu-megamenu-ajax-load:not(.tahefobu-loaded)');
    if (!$holder.length) {
      return;
    }
    $holder.addClass('tahefobu-loaded');
    var id = $holder.data('id');
    var restUrl = (window.tahefobuMegaAjax && window.tahefobuMegaAjax.restUrl) ? window.tahefobuMegaAjax.restUrl : '';

    if (!restUrl || !id) {
      return;
    }

    $.get(restUrl + 'megamenu/get_megamenu_content', { id: id }, function (html) {
      if (html) {
        $holder.replaceWith(html);
      }
    }).fail(function () {
      $holder.removeClass('tahefobu-loaded');
    });
  }

  $(document).on('mouseenter.tahefobuMega', '.tahefobu-megamenu-has', function () {
    tahefobuLoadMegamenu($(this));
  });
  $(document).on('click.tahefobuMega', '.tahefobu-megamenu-has', function () {
    tahefobuLoadMegamenu($(this));
  });
});
