document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.nav-toggle');
    const menu = document.querySelector('.nav-menu');

    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            const isOpen = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                menu.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    if (typeof Lenis !== 'undefined') {
        const lenis = new Lenis({
            lerp: 0.08,
            smoothWheel: true,
            wheelMultiplier: 0.9,
            touchMultiplier: 1
        });

        function animate(time) {
            lenis.raf(time);
            requestAnimationFrame(animate);
        }
        requestAnimationFrame(animate);
    }

    const reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        reveals.forEach(function (element) { revealObserver.observe(element); });
    } else {
        reveals.forEach(function (element) { element.classList.add('is-visible'); });
    }

    const counters = document.querySelectorAll('.counter');
    const counterObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting || entry.target.dataset.counted === 'true') return;

            const counter = entry.target;
            const target = Number(counter.dataset.target || 0);
            const duration = 900;
            const startedAt = performance.now();
            counter.dataset.counted = 'true';

            function count(now) {
                const progress = Math.min((now - startedAt) / duration, 1);
                counter.textContent = Math.floor(progress * target).toLocaleString();
                if (progress < 1) {
                    requestAnimationFrame(count);
                } else {
                    counter.textContent = target.toLocaleString() + '+';
                }
            }
            requestAnimationFrame(count);
            counterObserver.unobserve(counter);
        });
    }, { threshold: 0.5 });
    counters.forEach(function (counter) { counterObserver.observe(counter); });
});
