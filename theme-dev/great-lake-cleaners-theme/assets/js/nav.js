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

    // ── Sub-menu: mobile click toggle, desktop keyboard support ──────────────
    var parents = menu.querySelectorAll( '.menu-item-has-children' );

    parents.forEach( function( item ) {
        var link    = item.querySelector( ':scope > a' );
        var submenu = item.querySelector( ':scope > .sub-menu' );
        if ( ! link || ! submenu ) return;

        // Set ARIA attributes
        var id = 'glc-sub-' + Math.random().toString(36).slice(2);
        submenu.id = id;
        link.setAttribute( 'aria-haspopup', 'true' );
        link.setAttribute( 'aria-expanded', 'false' );
        link.setAttribute( 'aria-controls', id );

        link.addEventListener( 'click', function( e ) {
            // Only intercept on mobile (hamburger visible = menu is flex column)
            if ( window.getComputedStyle( toggle ).display === 'none' ) return;
            // Two-tap: first tap opens submenu; second tap navigates
            if ( item.classList.contains( 'glc-submenu-open' ) ) return;
            e.preventDefault();
            item.classList.add( 'glc-submenu-open' );
            link.setAttribute( 'aria-expanded', 'true' );
        } );

        // Keyboard: open on Enter/Space when focused on desktop
        link.addEventListener( 'keydown', function( e ) {
            if ( e.key === 'Enter' || e.key === ' ' ) {
                if ( window.getComputedStyle( toggle ).display !== 'none' ) return;
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
                link.setAttribute( 'aria-expanded', 'false' );
            }
        } );
    } );

    function closeAllSubmenus() {
        parents.forEach( function( item ) {
            item.classList.remove( 'glc-submenu-open' );
            var link = item.querySelector( ':scope > a' );
            if ( link ) link.setAttribute( 'aria-expanded', 'false' );
        } );
    }
} )();
