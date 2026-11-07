</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-col">
            <h3 class="footer-title footer-brand">DesignHub-TN</h3>
            <p class="footer-text">La plateforme qui met en relation les designers tunisiens avec des projets ambitieux : portfolios, marketplace et matching intelligent.</p>
            <div class="social-row"><a href="#" aria-label="Facebook" class="social-link"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="currentColor" style="width:16px;height:16px;flex:none"><path d="M13.5 21v-7.5h2.5l.5-3h-3V8.6c0-.9.3-1.5 1.6-1.5H16.600V4.400c-.3 0-1.300-.1-2.400-.1-2.400 0-4 1.400-4 4.100v2.100H7.600v3h2.600V21z"/></svg></a><a href="#" aria-label="Twitter" class="social-link"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="currentColor" style="width:16px;height:16px;flex:none"><path d="M18.200 3h2.900l-6.300 7.200L22 21h-5.800l-4.500-5.900L6.500 21H3.600l6.700-7.700L3 3h5.900l4.100 5.400zm-1 16.300h1.600L8.900 4.600H7.200z"/></svg></a><a href="#" aria-label="Instagram" class="social-link"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="currentColor" style="width:16px;height:16px;flex:none"><path d="M12 7.400A4.600 4.600 0 1 0 12 16.600 4.600 4.600 0 0 0 12 7.400zm0 7.600a3 3 0 1 1 0-6 3 3 0 0 1 0 6zM17 6a1.100 1.100 0 1 0 0 2.200A1.100 1.100 0 0 0 17 6zM16 3H8a5 5 0 0 0-5 5v8a5 5 0 0 0 5 5h8a5 5 0 0 0 5-5V8a5 5 0 0 0-5-5zm3.400 13a3.400 3.400 0 0 1-3.400 3.400H8A3.400 3.400 0 0 1 4.600 16V8A3.400 3.400 0 0 1 8 4.600h8A3.400 3.400 0 0 1 19.400 8z"/></svg></a><a href="#" aria-label="LinkedIn" class="social-link"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="currentColor" style="width:16px;height:16px;flex:none"><path d="M4.500 9h3.700v11.500H4.500zM6.400 3.500a2.150 2.150 0 1 1 0 4.300 2.150 2.150 0 0 1 0-4.300zM10.500 9h3.500v1.600h.1c.5-.9 1.700-1.900 3.500-1.900 3.700 0 4.400 2.400 4.400 5.600v6.200h-3.700v-5.500c0-1.300 0-3-1.800-3s-2.100 1.400-2.100 2.900v5.600h-3.700z"/></svg></a><a href="#" aria-label="YouTube" class="social-link"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="currentColor" style="width:16px;height:16px;flex:none"><path d="M21.600 7.200a2.500 2.500 0 0 0-1.800-1.800C18.200 5 12 5 12 5s-6.200 0-7.800.4A2.500 2.500 0 0 0 2.400 7.200C2 8.800 2 12 2 12s0 3.200.4 4.800a2.500 2.500 0 0 0 1.800 1.800C5.800 19 12 19 12 19s6.200 0 7.800-.4a2.500 2.500 0 0 0 1.800-1.800c.4-1.600.4-4.800.4-4.800s0-3.200-.4-4.800zM10 15V9l5.200 3z"/></svg></a></div>
        </div>

        <div class="footer-col">
            <h3 class="footer-title">Navigation</h3>
            <ul class="footer-links">
                <li><a href="index.php">Accueil</a></li>
                <li><a href="designers.php">Designers</a></li>
                <li><a href="marketplace.php">Marketplace</a></li>
                <li><a href="projects.php">Projets</a></li>
                <li><a href="matching.php">Matching</a></li>
                <li><a href="trends.php">Tendances</a></li>
                <li><a href="assistant.php">Assistant portfolio</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3 class="footer-title">Mon compte</h3>
            <ul class="footer-links">
                <li><a href="login.php">Connexion</a></li>
                <li><a href="register.php">Inscription</a></li>
                <li><a href="<?= e(home_url(current_user())) ?>">Mon espace</a></li>
                <li><a href="edit-profile.php">Modifier mon profil</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h3 class="footer-title">Contact</h3>
            <ul class="footer-contact">
                <li><?= icon('pin') ?><span>Tunis, Tunisie</span></li>
                <li><?= icon('phone') ?><span>+216 00 000 000<br>Lun-Ven, 9h-18h</span></li>
                <li><?= icon('mail') ?><span>contact@designhub-tn.tn</span></li>
            </ul>
            <p class="footer-text newsletter-label">Abonnez-vous à notre newsletter :</p>
            <form class="newsletter" id="newsletter-form" novalidate>
                <input type="email" id="newsletter-email" placeholder="Votre email" aria-label="Votre email" required>
                <button type="submit" class="btn newsletter-btn">S'abonner</button>
                <p class="newsletter-msg" id="newsletter-msg" role="status" hidden></p>
            </form>
        </div>
    </div>

    <div class="container footer-bottom">
        <p>© <?= date('Y') ?> DesignHub-TN · Mini-projet universitaire (Licence GTIC). Tous droits réservés.</p>
        <p class="small">Les profils, projets et avis présents dans la base sont des données de démonstration fictives.</p>
    </div>
</footer>

<script src="js/app.js"></script>
<script>
// Newsletter (démo) : aucune donnée n'est envoyée ni enregistrée.
(function () {
    var f = document.getElementById('newsletter-form');
    if (!f) return;
    f.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var email = document.getElementById('newsletter-email');
        var msg = document.getElementById('newsletter-msg');
        var ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
        msg.hidden = false;
        msg.textContent = ok ? 'Merci pour votre intérêt ! La newsletter arrive bientôt.' : 'Veuillez saisir un email valide.';
        if (ok) email.value = '';
    });
})();
</script>
</body>
</html>
