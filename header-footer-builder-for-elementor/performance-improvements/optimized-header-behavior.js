/**
 * Optimized Header Behavior - Performance Improved Version
 * Reduces DOM queries, uses passive event listeners, and implements throttling
 */
(function($) {
    'use strict';
    
    // Early exit for editor environments
    if (document.body.classList.contains('tahefobu-header-template-editor') || 
        document.body.classList.contains('tahefobu-footer-template-editor') ||
        window.location.search.includes('elementor-preview')) {
        return;
    }
    
    class TurboHeaderBehavior {
        constructor() {
            this.header = null;
            this.spacer = null;
            this.isSticky = false;
            this.hasAnimation = false;
            this.headerHeight = 0;
            this.headerTop = 0;
            this.lastScrollY = 0;
            this.adminBarHeight = 0;
            this.ticking = false;
            
            this.init();
        }
        
        init() {
            // Find header element
            this.header = document.getElementById('tahefobu-header') || 
                         document.querySelector('.turbo-header-template');
            
            if (!this.header) return;
            
            // Add ready class for CSS transitions
            this.header.classList.add('tahefobu-ready');
            
            // Get configuration
            this.isSticky = this.header.dataset.sticky === '1' || 
                           this.header.classList.contains('ta-sticky-header');
            this.hasAnimation = this.header.dataset.animation === '1' || 
                               this.header.classList.contains('ta-header-scroll-animation');
            
            // Calculate admin bar offset
            const adminBar = document.getElementById('wpadminbar');
            this.adminBarHeight = adminBar ? adminBar.offsetHeight : 0;
            
            // Set CSS custom property for sticky positioning
            this.header.style.setProperty('--ta-sticky-top', this.adminBarHeight + 'px');
            
            this.setupEventListeners();
            this.calculateDimensions();
            
            if (this.isSticky) {
                this.handleStickyScroll();
            }
            
            if (this.hasAnimation) {
                this.lastScrollY = window.pageYOffset;
                this.header.classList.add('ta-scroll-up');
            }
        }
        
        setupEventListeners() {
            // Use passive listeners for better performance
            if (this.isSticky || this.hasAnimation) {
                window.addEventListener('scroll', this.onScroll.bind(this), { passive: true });
            }
            
            if (this.isSticky) {
                window.addEventListener('resize', this.onResize.bind(this), { passive: true });
            }
        }
        
        onScroll() {
            if (!this.ticking) {
                requestAnimationFrame(() => {
                    if (this.isSticky) {
                        this.handleStickyScroll();
                    }
                    
                    if (this.hasAnimation) {
                        this.handleAnimationScroll();
                    }
                    
                    this.ticking = false;
                });
                this.ticking = true;
            }
        }
        
        onResize() {
            // Throttle resize events
            clearTimeout(this.resizeTimeout);
            this.resizeTimeout = setTimeout(() => {
                this.calculateDimensions();
                if (this.isSticky) {
                    this.handleStickyScroll();
                }
            }, 100);
        }
        
        calculateDimensions() {
            this.headerHeight = this.header.offsetHeight;
            this.headerTop = this.spacer && this.spacer.offsetParent ? 
                            this.spacer.offsetTop : this.header.offsetTop;
        }
        
        handleStickyScroll() {
            const scrollY = window.pageYOffset;
            
            if (scrollY > this.headerTop) {
                if (!this.header.classList.contains('ta-sticky-active')) {
                    this.activateSticky();
                }
            } else {
                if (this.header.classList.contains('ta-sticky-active')) {
                    this.deactivateSticky();
                }
            }
        }
        
        activateSticky() {
            this.headerHeight = this.header.offsetHeight;
            
            if (!this.spacer) {
                this.spacer = document.createElement('div');
                this.spacer.className = 'ta-header-spacer';
                this.spacer.style.display = 'none';
                this.header.parentNode.insertBefore(this.spacer, this.header);
            }
            
            this.spacer.style.height = this.headerHeight + 'px';
            this.spacer.style.display = 'block';
            this.header.classList.add('ta-sticky-active');
        }
        
        deactivateSticky() {
            this.header.classList.remove('ta-sticky-active');
            if (this.spacer) {
                this.spacer.style.display = 'none';
            }
        }
        
        handleAnimationScroll() {
            const currentScrollY = window.pageYOffset;
            const isScrollingDown = currentScrollY > this.lastScrollY;
            
            // Update classes for new animation system
            this.header.classList.toggle('ta-scroll-down', isScrollingDown);
            this.header.classList.toggle('ta-scroll-up', !isScrollingDown);
            
            // Backward compatibility with existing classes
            if (isScrollingDown && currentScrollY > 200) {
                this.header.classList.remove('ta-header-show');
                this.header.classList.add('ta-header-hide', 'ta-header-hidden');
            } else if (!isScrollingDown && currentScrollY > 80) {
                this.header.classList.remove('ta-header-hide', 'ta-header-hidden');
                this.header.classList.add('ta-header-show');
            }
            
            this.lastScrollY = currentScrollY;
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => new TurboHeaderBehavior());
    } else {
        new TurboHeaderBehavior();
    }
    
})(jQuery);