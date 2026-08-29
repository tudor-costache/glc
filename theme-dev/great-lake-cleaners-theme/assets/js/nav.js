( function() {
    var toggle = document.querySelector( '.glc-menu-toggle' );
    var menu   = document.querySelector( '.glc-nav-menu' );

    if ( ! toggle || ! menu ) return;

    // ── Mobile hamburger ──────────────────────────────────────────────────────
    toggle.addEventListener( 'click', function() {
        var expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
        toggle.setAttribute( 'aria-expanded', String( ! expanded ) );
        menu.classList.toggle( 'is-open', ! expanded );
    } );

    document.addEventListener( 'click', function( e ) {
        if ( ! e.target.closest( '#glc-site-header' ) ) {
            toggle.setAttribute( 'aria-expanded', 'false' );
            menu.classList.remove( 'is-open' );
            closeAllSubmenus();
        }
    } );

    // ── Sub-menu: desktop hover / keyboard disclosure ───────────────────────
    // Mobile (hamburger visible): CSS renders each sub-menu as a static, always
    // -expanded nested list, so the parent link is just a link — no tap
    // interception, no popup semantics.
    // Desktop: mouse hover reveals the sub-menu (CSS); keyboard users toggle it
    // with Enter / Space / Escape, and it closes when focus leaves the item.
    var parents  = menu.querySelectorAll( '.menu-item-has-children' );
    var onMobile = function() {
        return window.getComputedStyle( toggle ).display !== 'none';
    };

    parents.forEach( function( item ) {
        var link    = item.querySelector( ':scope > a' );
        var submenu = item.querySelector( ':scope > .sub-menu' );
        if ( ! link || ! submenu ) return;

        var id = 'glc-sub-' + Math.random().toString(36).slice(2);
        submenu.id = id;
        link.setAttribute( 'aria-controls', id );
        // Popup semantics only apply where the sub-menu is actually hidden
        // until triggered — i.e. desktop. On mobile it's a visible nested list.
        if ( ! onMobile() ) {
            link.setAttribute( 'aria-haspopup', 'true' );
            link.setAttribute( 'aria-expanded', 'false' );
        }

        // Keyboard (desktop only): Enter/Space opens the submenu instead of
        // following the link; Escape closes it.
        link.addEventListener( 'keydown', function( e ) {
            if ( onMobile() ) return;
            if ( e.key === 'Enter' || e.key === ' ' ) {
                e.preventDefault();
                var open = item.classList.toggle( 'glc-submenu-open' );
                link.setAttribute( 'aria-expanded', String( open ) );
            }
            if ( e.key === 'Escape' ) {
                item.classList.remove( 'glc-submenu-open' );
                link.setAttribute( 'aria-expanded', 'false' );
                link.focus();
            }
        } );

        // Close sub-menu when focus leaves the parent item entirely
        item.addEventListener( 'focusout', function( e ) {
            if ( ! item.contains( e.relatedTarget ) ) {
                item.classList.remove( 'glc-submenu-open' );
                if ( link.hasAttribute( 'aria-expanded' ) ) {
                    link.setAttribute( 'aria-expanded', 'false' );
                }
            }
        } );
    } );

    function closeAllSubmenus() {
        parents.forEach( function( item ) {
            item.classList.remove( 'glc-submenu-open' );
            var link = item.querySelector( ':scope > a' );
            if ( link && link.hasAttribute( 'aria-expanded' ) ) {
                link.setAttribute( 'aria-expanded', 'false' );
            }
        } );
    }

    // ── Compact header on scroll ──────────────────────────────────────────────
    var header = document.getElementById( 'glc-site-header' );
    if ( header ) {
        var ticking = false;
        function updateCompact() {
            var y = window.scrollY;
            if ( ! header.classList.contains( 'is-compact' ) && y > 80 ) {
                header.classList.add( 'is-compact' );
            } else if ( header.classList.contains( 'is-compact' ) && y < 40 ) {
                header.classList.remove( 'is-compact' );
            }
            ticking = false;
        }
        window.addEventListener( 'scroll', function() {
            if ( ! ticking ) {
                window.requestAnimationFrame( updateCompact );
                ticking = true;
            }
        }, { passive: true } );
        if ( window.scrollY > 80 ) header.classList.add( 'is-compact' );
    }
} )();
