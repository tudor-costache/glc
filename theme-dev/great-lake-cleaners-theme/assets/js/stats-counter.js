( function() {
    var strip = document.querySelector( '.glc-stats-strip' );
    if ( ! strip ) return;

    var counters = strip.querySelectorAll( '.glc-count' );
    if ( ! counters.length ) return;

    function fmtNum( n ) {
        return n.toString().replace( /\B(?=(\d{3})+(?!\d))/g, ',' );
    }

    function runCounters() {
        var duration = 6000;
        var start    = null;

        // Start at most 50 below the target so large numbers don't take forever
        var targets = Array.prototype.map.call( counters, function( el ) {
            var target = parseInt( el.dataset.count, 10 );
            return { el: el, target: target, from: Math.max( 0, target - 50 ) };
        } );

        function step( timestamp ) {
            if ( ! start ) start = timestamp;
            var progress = Math.min( ( timestamp - start ) / duration, 1 );
            // Ease-out cubic — fast start, settles gently at the target
            var eased = 1 - Math.pow( 1 - progress, 3 );

            targets.forEach( function( t ) {
                t.el.textContent = fmtNum( Math.round( t.from + eased * ( t.target - t.from ) ) );
            } );

            if ( progress < 1 ) {
                requestAnimationFrame( step );
            }
        }

        // Seed the starting values just before animating (element not yet visible)
        targets.forEach( function( t ) { t.el.textContent = fmtNum( t.from ); } );
        requestAnimationFrame( step );
    }

    if ( 'IntersectionObserver' in window ) {
        var observer = new IntersectionObserver( function( entries ) {
            entries.forEach( function( entry ) {
                if ( entry.isIntersecting ) {
                    observer.disconnect();
                    runCounters();
                }
            } );
        }, { threshold: 0.3 } );
        observer.observe( strip );
    } else {
        // No IntersectionObserver — run immediately
        runCounters();
    }
} )();
