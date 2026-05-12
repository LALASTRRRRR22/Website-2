// ДомУслуг — main.js (Liquid Glass Redesign)

(function () {
    'use strict';

    /* ── Particle system ─────────────────────────────────── */
    function initParticles() {
        var canvas = document.getElementById('particles-canvas');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var particles = [];
        var W, H;

        function resize() {
            W = canvas.width  = window.innerWidth;
            H = canvas.height = window.innerHeight;
        }
        resize();
        window.addEventListener('resize', resize);

        var COUNT = Math.min(80, Math.floor(window.innerWidth / 18));

        for (var i = 0; i < COUNT; i++) {
            particles.push({
                x:  Math.random() * window.innerWidth,
                y:  Math.random() * window.innerHeight,
                r:  Math.random() * 1.8 + 0.4,
                dx: (Math.random() - 0.5) * 0.35,
                dy: (Math.random() - 0.5) * 0.35,
                o:  Math.random() * 0.5 + 0.1
            });
        }

        function draw() {
            ctx.clearRect(0, 0, W, H);
            particles.forEach(function (p) {
                p.x += p.dx;
                p.y += p.dy;
                if (p.x < 0) p.x = W;
                if (p.x > W) p.x = 0;
                if (p.y < 0) p.y = H;
                if (p.y > H) p.y = 0;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(167,139,250,' + p.o + ')';
                ctx.fill();
            });

            /* draw connections */
            for (var a = 0; a < particles.length; a++) {
                for (var b = a + 1; b < particles.length; b++) {
                    var dx = particles[a].x - particles[b].x;
                    var dy = particles[a].y - particles[b].y;
                    var dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 130) {
                        ctx.beginPath();
                        ctx.moveTo(particles[a].x, particles[a].y);
                        ctx.lineTo(particles[b].x, particles[b].y);
                        ctx.strokeStyle = 'rgba(124,58,237,' + (0.12 * (1 - dist / 130)) + ')';
                        ctx.lineWidth = 0.6;
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(draw);
        }
        draw();
    }

    /* ── Cursor glow ─────────────────────────────────────── */
    function initCursorGlow() {
        var glow = document.getElementById('cursor-glow');
        if (!glow) return;
        document.addEventListener('mousemove', function (e) {
            glow.style.left = e.clientX + 'px';
            glow.style.top  = e.clientY + 'px';
        });
    }

    /* ── Read-progress bar ───────────────────────────────── */
    function initProgressBar() {
        var bar = document.getElementById('progress-bar');
        if (!bar) return;
        function update() {
            var docH    = document.documentElement.scrollHeight - window.innerHeight;
            var scrolled = docH > 0 ? (window.pageYOffset / docH) * 100 : 0;
            bar.style.width = scrolled + '%';
        }
        window.addEventListener('scroll', update, { passive: true });
        update();
    }

    /* ── Scroll reveal ───────────────────────────────────── */
    function initScrollReveal() {
        var els = document.querySelectorAll('[data-animate]');
        if (!els.length) return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        els.forEach(function (el) {
            var type  = el.getAttribute('data-animate');
            var delay = el.getAttribute('data-animate-delay');
            el.classList.add(type === 'fade-up' ? 'animate-fade-up' : 'animate-fade-in');
            if (delay === '1' || delay === '2' || delay === '3') {
                el.classList.add('animate-delay-' + delay);
            }
            observer.observe(el);
        });
    }

    /* ── Animated counters in stats section ──────────────── */
    function initCounters() {
        var statValues = document.querySelectorAll('.stat-value');
        if (!statValues.length) return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var el      = entry.target;
                var rawText = el.textContent.replace(/\s/g, '').replace('+', '');
                var target  = parseInt(rawText, 10);
                if (isNaN(target)) return;
                observer.unobserve(el);

                var start    = 0;
                var duration = 1800;
                var startTime = null;

                function step(ts) {
                    if (!startTime) startTime = ts;
                    var progress = Math.min((ts - startTime) / duration, 1);
                    var eased    = 1 - Math.pow(1 - progress, 3);
                    var current  = Math.floor(eased * target);
                    el.textContent = current.toLocaleString('ru-RU') + '+';
                    if (progress < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            });
        }, { threshold: 0.5 });

        statValues.forEach(function (el) { observer.observe(el); });
    }

    /* ── Card 3-D tilt on hover ──────────────────────────── */
    function initCardTilt() {
        var cards = document.querySelectorAll('.public-order-card, .adv-card, .how-step, .stat-item, .category-card');
        cards.forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                var rect   = card.getBoundingClientRect();
                var x      = e.clientX - rect.left;
                var y      = e.clientY - rect.top;
                var cx     = rect.width  / 2;
                var cy     = rect.height / 2;
                var rx     = ((y - cy) / cy) * 6;
                var ry     = ((cx - x) / cx) * 6;
                card.style.transform = 'translateY(-8px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg) scale(1.01)';
            });
            card.addEventListener('mouseleave', function () {
                card.style.transform = '';
                card.style.transition = 'transform 0.4s cubic-bezier(0.25,0.46,0.45,0.94)';
                setTimeout(function () { card.style.transition = ''; }, 400);
            });
        });
    }

    /* ── Ripple effect on buttons ────────────────────────── */
    function initRipple() {
        document.querySelectorAll('.steam-btn, .auth-btn-submit').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var rect   = btn.getBoundingClientRect();
                var ripple = document.createElement('span');
                var size   = Math.max(rect.width, rect.height);
                ripple.style.cssText = [
                    'position:absolute',
                    'width:' + size + 'px',
                    'height:' + size + 'px',
                    'left:'  + (e.clientX - rect.left  - size / 2) + 'px',
                    'top:'   + (e.clientY - rect.top   - size / 2) + 'px',
                    'background:rgba(255,255,255,0.22)',
                    'border-radius:50%',
                    'transform:scale(0)',
                    'animation:rippleAnim 0.55s ease-out forwards',
                    'pointer-events:none'
                ].join(';');
                btn.appendChild(ripple);
                ripple.addEventListener('animationend', function () { ripple.remove(); });
            });
        });

        /* inject keyframes once */
        if (!document.getElementById('ripple-style')) {
            var s = document.createElement('style');
            s.id  = 'ripple-style';
            s.textContent = '@keyframes rippleAnim{to{transform:scale(4);opacity:0}}';
            document.head.appendChild(s);
        }
    }

    /* ── Navbar scroll shadow ────────────────────────────── */
    function initNavbarShadow() {
        var bar = document.querySelector('.steam-topbar');
        if (!bar) return;
        window.addEventListener('scroll', function () {
            if (window.pageYOffset > 10) {
                bar.style.boxShadow = '0 4px 50px rgba(0,0,0,0.65)';
            } else {
                bar.style.boxShadow = '0 2px 40px rgba(0,0,0,0.5)';
            }
        }, { passive: true });
    }

    /* ── Auth panel toggle ───────────────────────────────── */
    function initAuthToggle() {
        var container = document.querySelector('.auth-container');
        var signInBtn = document.getElementById('signInBtn');
        var signUpBtn = document.getElementById('signUpBtn');
        if (!container) return;
        if (signUpBtn) signUpBtn.addEventListener('click', function (e) { e.preventDefault(); container.classList.add('right-panel-active'); });
        if (signInBtn) signInBtn.addEventListener('click', function (e) { e.preventDefault(); container.classList.remove('right-panel-active'); });
    }

    /* ── Auto-dismiss alerts ─────────────────────────────── */
    function initAlerts() {
        document.querySelectorAll('.steam-alert, .auth-alert').forEach(function (el) {
            setTimeout(function () {
                el.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                el.style.opacity    = '0';
                el.style.transform  = 'translateY(-10px)';
                el.addEventListener('transitionend', function () { if (el.parentNode) el.parentNode.removeChild(el); }, { once: true });
            }, 5000);
        });
    }

    /* ── Smooth nav link active state ───────────────────── */
    function initNavHighlight() {
        var links = document.querySelectorAll('.steam-link');
        var current = window.location.pathname.split('/').pop() || 'index.php';
        links.forEach(function (link) {
            if (link.getAttribute('href') && link.getAttribute('href').indexOf(current) !== -1) {
                link.classList.add('steam-link-active');
            }
        });
    }

    /* ── Page load fade-in ───────────────────────────────── */
    function initPageFade() {
        document.body.style.opacity = '0';
        document.body.style.transition = 'opacity 0.5s ease';
        setTimeout(function () {
            document.body.style.opacity = '1';
            document.body.classList.add('loaded');
        }, 60);
    }

    /* ── Stagger children of grids ──────────────────────── */
    function initGridStagger() {
        document.querySelectorAll('.public-order-grid, .categories-grid, .how-grid, .adv-list, .stats-grid').forEach(function (grid) {
            var children = grid.querySelectorAll(':scope > *');
            children.forEach(function (child, i) {
                child.style.transitionDelay = (i * 0.06) + 's';
            });
        });
    }

    /* ── Boot ────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        initPageFade();
        initParticles();
        initCursorGlow();
        initProgressBar();
        initScrollReveal();
        initCounters();
        initCardTilt();
        initRipple();
        initNavbarShadow();
        initAuthToggle();
        initAlerts();
        initNavHighlight();
        initGridStagger();
    });

}());
