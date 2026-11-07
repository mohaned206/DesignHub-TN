// js/app.js : petites interactions côté navigateur.
// Important : JavaScript ne sert qu'au confort. Toutes les vérifications importantes
// (droits d'accès, validation des données) sont faites en PHP, côté serveur.

// 1) Menu mobile : le bouton (3 traits) affiche / cache la navigation
var menuButton = document.querySelector('.menu-toggle');
if (menuButton) {
    menuButton.addEventListener('click', function () {
        document.getElementById('main-nav').classList.toggle('open');
    });
}

// 2) Confirmation avant une action sensible : <form data-confirm="Message ?">
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!confirm(form.dataset.confirm)) {
            event.preventDefault();   // l'utilisateur a cliqué sur "Annuler"
        }
    });
});

// 3) Liste déroulante qui envoie son formulaire dès qu'on change de choix : <select data-autosubmit>
document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () {
        select.form.submit();
    });
});

// 4) Image introuvable (lien mort, pas d'Internet) : on cache l'image ou on affiche un cadre de remplacement
document.querySelectorAll('img[data-fallback]').forEach(function (img) {
    function replace() {
        if (img.dataset.fallback === 'placeholder') {
            var box = document.createElement('div');
            box.className = 'img-placeholder';
            box.textContent = 'Image indisponible';
            img.replaceWith(box);
        } else {
            img.style.display = 'none';   // l'avatar garde alors ses initiales
        }
    }
    img.addEventListener('error', replace);
    if (img.complete && img.naturalWidth === 0) {
        replace();   // l'erreur s'est déjà produite avant l'exécution du script
    }
});

// 6) Agrandissement des images : <img data-lightbox data-caption="Titre">
//    Clic = image en grand ; flèches du clavier / boutons = précédente et suivante ; Échap ou clic dehors = fermer.
(function () {
    var imgs = Array.prototype.slice.call(document.querySelectorAll('img[data-lightbox]'));
    if (!imgs.length) { return; }
    var box = document.createElement('div');
    box.className = 'lightbox';
    box.hidden = true;
    box.innerHTML = '<button type="button" class="lb-close" aria-label="Fermer">×</button>' +
        '<button type="button" class="lb-prev" aria-label="Précédente">‹</button>' +
        '<img alt="">' +
        '<button type="button" class="lb-next" aria-label="Suivante">›</button>' +
        '<p class="lb-caption"></p>';
    document.body.appendChild(box);
    var big = box.querySelector('img');
    var caption = box.querySelector('.lb-caption');
    var index = 0;

    function show(i) {
        index = (i + imgs.length) % imgs.length;
        big.src = imgs[index].src;
        big.alt = imgs[index].alt;
        caption.textContent = (imgs[index].getAttribute('data-caption') || imgs[index].alt) + '  (' + (index + 1) + '/' + imgs.length + ')';
    }
    function open(i) { show(i); box.hidden = false; document.body.style.overflow = 'hidden'; }
    function close() { box.hidden = true; document.body.style.overflow = ''; }

    imgs.forEach(function (img, i) {
        img.style.cursor = 'zoom-in';
        img.addEventListener('click', function () { open(i); });
    });
    box.querySelector('.lb-close').addEventListener('click', close);
    box.querySelector('.lb-prev').addEventListener('click', function () { show(index - 1); });
    box.querySelector('.lb-next').addEventListener('click', function () { show(index + 1); });
    box.addEventListener('click', function (ev) { if (ev.target === box) { close(); } });
    document.addEventListener('keydown', function (ev) {
        if (box.hidden) { return; }
        if (ev.key === 'Escape') { close(); }
        if (ev.key === 'ArrowLeft') { show(index - 1); }
        if (ev.key === 'ArrowRight') { show(index + 1); }
    });
})();

// 7) Effets d'interface (confort visuel uniquement ; rien n'en dépend)
(function () {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // En-tête : ombre dès que la page défile
    var header = document.querySelector('.site-header');
    if (header) {
        var onScroll = function () { header.classList.toggle('scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // Compteurs animés : <strong data-count="42" data-suffix=" %">
    function countUp(el) {
        var target = parseFloat(el.getAttribute('data-count'));
        var suffix = el.getAttribute('data-suffix') || '';
        if (isNaN(target) || reduce) { return; }
        var start = null, dur = 1100;
        function fmt(v) { return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') + suffix; }
        function step(t) {
            if (start === null) { start = t; }
            var k = Math.min(1, (t - start) / dur);
            el.textContent = fmt(target * (1 - Math.pow(1 - k, 3)));
            if (k < 1) { requestAnimationFrame(step); }
        }
        requestAnimationFrame(step);
    }

    // Apparition progressive des blocs au défilement : <div class="reveal">
    var items = document.querySelectorAll('.reveal');
    var counters = document.querySelectorAll('[data-count]');
    if ('IntersectionObserver' in window && !reduce) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        items.forEach(function (el, i) { el.style.setProperty('--d', ((i % 3) * 0.08) + 's'); io.observe(el); });

        var io2 = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { countUp(en.target); io2.unobserve(en.target); }
            });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { io2.observe(el); });
    } else {
        items.forEach(function (el) { el.classList.add('in'); });
    }

    // Halo qui suit la souris + légère inclinaison sur les cartes de section
    if (!reduce) {
        document.querySelectorAll('.sec-card').forEach(function (card) {
            card.addEventListener('mousemove', function (ev) {
                var r = card.getBoundingClientRect();
                var x = ev.clientX - r.left, y = ev.clientY - r.top;
                card.style.setProperty('--mx', x + 'px');
                card.style.setProperty('--my', y + 'px');
                var rx = ((y / r.height) - 0.5) * -5, ry = ((x / r.width) - 0.5) * 5;
                card.style.transform = 'translateY(-6px) perspective(900px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
            });
            card.addEventListener('mouseleave', function () { card.style.transform = ''; });
        });
    }
})();

// 8) Fenêtre de confirmation (administration) : <button data-modal="id" data-type data-id data-label data-warn>
document.querySelectorAll('[data-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var dlg = document.getElementById(btn.getAttribute('data-modal'));
        if (!dlg || typeof dlg.showModal !== 'function') { return; }
        dlg.querySelector('[name="target_type"]').value = btn.getAttribute('data-type') || '';
        dlg.querySelector('[name="target_id"]').value = btn.getAttribute('data-id') || '';
        var t = dlg.querySelector('.target'); if (t) { t.textContent = btn.getAttribute('data-label') || ''; }
        var c = dlg.querySelector('.cascade'); if (c) { c.textContent = btn.getAttribute('data-warn') || ''; }
        var ta = dlg.querySelector('textarea'); if (ta) { ta.value = ''; }
        dlg.showModal();
    });
});
document.querySelectorAll('dialog [data-close]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('dialog').close(); });
});
document.querySelectorAll('dialog.modal').forEach(function (d) {
    d.addEventListener('click', function (ev) { if (ev.target === d) { d.close(); } });
});
