# Résumé technique — DesignHub-TN

Document de préparation à la soutenance : ce que fait le projet, comment il est construit, et comment l'expliquer.

---

## 1. Diagnostic du projet initial

| Constat dans le dossier fourni | Conséquence |
|---|---|
| 17 fichiers `.html` **strictement identiques** (même contenu, 593 lignes) ; seul `main.js` change l'écran affiché | Ce n'étaient pas 17 pages mais **une seule page** (application « single page ») déguisée |
| Tout le code logique dans `js/main.js` (963 lignes) ; les autres `.js`/`.css` et `data/*.js` ne contenaient presque rien (1 ligne) | Organisation trompeuse, code non maintenable |
| Données dans `localStorage` / `sessionStorage` | Aucune base de données, données perdues si on vide le navigateur, invisibles pour les autres utilisateurs |
| **Aucun mot de passe vérifié** : n'importe quel email « se connectait » comme client | Authentification factice |
| Données insérées dans la page avec `innerHTML` sans échappement | Faille **XSS** (un titre ou une bio contenant du HTML, par exemple `<img onerror=…>`, s'exécutait dans la page) |
| Statistiques fixes : « 120+ designers », « 350+ projets », « 98 % », note « 4.9 » | Chiffres inventés |
| « Assistant IA » et « AI Match » | Simples règles `if`, présentées comme de l'IA ; une phrase affirmait « 4x plus de demandes » sans source |
| Score de matching : minimum 15+10+15 = 40 pts même pour un designer sans rapport ; les compétences des projets n'étaient jamais comparées | Algorithme peu pertinent |
| Tailwind, Font Awesome, Google Fonts chargés depuis Internet | Ne fonctionne pas hors ligne |
| « Demandes de collaboration » : n'importe qui pouvait modifier le statut | Pas de contrôle des droits |

**Ce qui a été conservé** : l'identité visuelle (violet/bleu marine), les fonctionnalités (annuaire, filtres, marketplace, projets, matching avec détail des points, dashboard client/designer, assistant, invitations accepter/refuser), les données de démonstration (designers, ressources, projets).

---

## 2. Architecture

Architecture classique **« une page PHP = une page du site »**, sans framework :

```
Navigateur ──HTTP──▶ page.php ──▶ includes/functions.php (session, sécurité)
                          │            └▶ config/database.php (PDO)
                          ├──▶ MySQL  (requêtes préparées)
                          └──▶ includes/header.php + HTML + includes/footer.php
```

Chaque page suit **le même schéma en 4 étapes** :

1. `require functions.php` (démarre la session, ouvre la connexion).
2. **Contrôle d'accès** (`require_login()`, `require_role('client')`).
3. **Traitement du formulaire** si la requête est un POST (`csrf_check()`, validation, requête SQL, redirection).
4. **Lecture des données** puis **affichage** HTML avec `e()` sur chaque valeur.

Les actions qui modifient l'état d'un projet (inviter, accepter, terminer, annuler, évaluer) sont regroupées dans `project-action.php` (un `switch` sur le champ caché `action`).

---

## 3. Base de données (7 tables)

```
users ─┬─1──1─ designer_profiles
       ├─1──N─ portfolio_items
       ├─1──N─ resources
       ├─1──N─ projects (client_id) ──N──1─ users (designer_id, nullable)
       │          └─1──N─ project_invitations (UNIQUE project+designer)
       │          └─1──1─ reviews (UNIQUE project_id)
```

| Table | Rôle | Points importants |
|---|---|---|
| `users` | Comptes (clients et designers) | `email` UNIQUE, `password_hash`, `role` ENUM |
| `designer_profiles` | Profil du designer (1-1 avec `users`) | `skills` = texte séparé par des virgules (choix de simplicité) |
| `portfolio_items` | Réalisations | FK `designer_id`, `ON DELETE CASCADE` |
| `resources` | Marketplace | catégorie ENUM (Template, Mockup, Pack graphique), `price` 0 = gratuit |
| `projects` | Demandes des clients | `status` ENUM : ouvert → en_cours → terminé / annulé ; `designer_id` rempli à l'acceptation |
| `project_invitations` | Client propose un projet à un designer | UNIQUE (project_id, designer_id) : pas de doublon |
| `reviews` | Évaluations | `CHECK (rating BETWEEN 1 AND 5)`, UNIQUE (project_id) : 1 avis par projet |

Les **statistiques** (note moyenne, nombre de projets, etc.) ne sont **jamais stockées** : elles sont calculées avec `AVG`, `COUNT`, `GROUP BY` à partir des données réelles. S'il n'y a pas d'avis, l'écran affiche « Pas encore d'évaluation ».

---

## 4. Cycle de vie d'un projet

```
 ouvert ──(designer invité accepte)──▶ en_cours ──(client termine)──▶ termine ──▶ évaluation possible
   │
   └──(client annule)──▶ annule
```

Droits vérifiés **côté serveur** : seul le client propriétaire invite/annule/termine/évalue ; seul le designer invité répond ; l'évaluation n'est possible qu'après la fin du projet, une seule fois.

---

## 5. Algorithme de matching (`includes/matching.php`)

**Ce n'est pas de l'IA** : c'est un système de **règles** avec un score sur 100, entièrement explicable.

| Critère | Points | Règle |
|---|---|---|
| Spécialité | 40 | spécialité du designer = catégorie du projet (sinon 0) |
| Compétences | 30 | 30 × (compétences demandées possédées ÷ compétences demandées) |
| Budget | 20 | tarif horaire × 15 h ≤ budget → 20 ; dépassement ≤ 20 % → 10 ; sinon 0 |
| Portfolio | 10 | au moins une réalisation dans la catégorie du projet |

Fonction : `compute_match($projet, $designer, $categoriesPortfolio)` → renvoie le score et, pour chaque critère, une phrase d'explication affichée à l'utilisateur. Les designers sont triés par score décroissant (`usort`).
Limites à reconnaître : l'estimation de 15 h est arbitraire ; les compétences doivent être écrites de façon identique (pas de synonymes).

---

## 6. Sécurité : ce qui est en place et où

| Menace | Protection | Où |
|---|---|---|
| Mots de passe lisibles | `password_hash()` à l'inscription, `password_verify()` à la connexion | `register.php`, `login.php` |
| Injection SQL | **Requêtes préparées PDO** partout (`prepare` + `execute`) | toutes les pages |
| XSS | Fonction `e()` (`htmlspecialchars`) sur toute donnée affichée ; URL limitées à `http(s)` | `functions.php` |
| CSRF | Jeton secret dans chaque formulaire POST, vérifié par `csrf_check()` (y compris pour la déconnexion) | `functions.php` |
| Accès non autorisé | `require_login()`, `require_role()`, vérification du propriétaire dans chaque requête (`WHERE ... AND client_id = ?`) | pages privées, `project-action.php` |
| Fixation de session | `session_regenerate_id(true)` après connexion | `login.php`, `register.php` |
| Cookie volé par JavaScript | cookie de session `httponly` | `functions.php` |
| Redirection ouverte | adresse de retour validée par expression régulière | `project-action.php` |
| Énumération des comptes | même message d'erreur pour email inconnu / mauvais mot de passe | `login.php` |
| Données invalides | validation **côté serveur** de chaque champ | chaque formulaire |

Le rôle de l'utilisateur est **relu dans la base à chaque requête** (`current_user()`), jamais cru depuis le navigateur.

---

## 7. Fichiers et fonctions principales

**`includes/functions.php`** (utilisé par toutes les pages)
`e()` échappe · `redirect()` · `set_flash()/get_flash()` messages · `csrf_field()/csrf_check()` · `current_user()` · `require_login()` · `require_role()` · `split_skills()/clean_skills()` compétences · `valid_url()` · `designers_base_sql()` requête des designers avec note moyenne · `designer_card()` carte HTML · `avatar_html()` · `rating_html()` · `status_badge()`.

**`config/database.php`** : `db()` retourne l'objet PDO (une seule connexion par requête, erreurs en exceptions).

**`includes/matching.php`** : `compute_match()`.

**Pages** : voir l'arborescence du README.

**`js/app.js`** (4 interactions, aucune logique métier) : menu mobile · confirmation avant suppression · liste qui se soumet seule · image de remplacement si une URL est cassée.

---

## 8. État des fonctionnalités

| Fonctionnalité | État | Vérification |
|---|---|---|
| Accueil (statistiques réelles) | ✅ Terminé | testé |
| Annuaire : recherche texte + filtre spécialité + ville | ✅ Terminé | testé (dont tentative d'injection SQL) |
| Profil public + portfolio + avis | ✅ Terminé | testé |
| Modification du profil, ajout/suppression de réalisations (CRUD) | ✅ Terminé | testé (dont suppression par un autre designer = sans effet) |
| Marketplace : catégories, recherche, ajout/suppression par le designer | ✅ Terminé | testé |
| Inscription / connexion / déconnexion (rôles client et designer) | ✅ Terminé | testé |
| Création de projet, liste, détail, statuts, annulation, fin | ✅ Terminé | testé |
| Invitations (envoi, doublon refusé, accepter/refuser) | ✅ Terminé | testé |
| Matching à base de règles, avec détail des points | ✅ Terminé | testé |
| Évaluations (1 par projet terminé) + moyenne | ✅ Terminé | testé |
| Tableaux de bord client / designer (statistiques SQL) | ✅ Terminé | testé |
| Assistant portfolio (règles, **simulation**) | ✅ Terminé | testé |
| Protection CSRF, XSS, injection SQL, contrôle des rôles | ✅ Terminé | tests automatisés (58 vérifications) |
| Affichage mobile (menu ☰, grilles) | ⚠️ Vérifié par capture d'écran (390 px et 1200 px), pas sur de vrais appareils | |
| Tests sur **XAMPP / Windows / Apache** | ⚠️ **Non testé** | Testé sur PHP 8.3 (serveur intégré) + MariaDB 10.11 sous Linux. XAMPP utilise la même famille de logiciels, mais je n'ai pas pu l'essayer |
| Navigateurs autres que Chromium | ⚠️ Non testé | |
| Envoi de fichiers image, mot de passe oublié, limitation des tentatives de connexion, HTTPS | ❌ Non implémenté | hors périmètre du mini-projet |
| Vraie IA, paiement, messagerie | ❌ Volontairement absent | non demandé |

---

## 9. Questions probables du jury (et pistes de réponse)

1. **Pourquoi PHP + MySQL plutôt que `localStorage` ?** Les données doivent être partagées entre utilisateurs et survivre ; `localStorage` est local à un navigateur et modifiable par l'utilisateur.
2. **Comment sont protégés les mots de passe ?** `password_hash` (algorithme bcrypt, sel aléatoire intégré) ; on ne stocke jamais le mot de passe, `password_verify` compare.
3. **Qu'est-ce qu'une requête préparée ?** La requête SQL et les valeurs sont envoyées séparément ; la valeur ne peut pas être interprétée comme du SQL.
4. **C'est quoi XSS / CSRF ?** XSS : du code injecté dans une page ; contre-mesure `e()`. CSRF : un site tiers force l'envoi d'un formulaire ; contre-mesure : jeton secret par session.
5. **Le matching est-il de l'IA ?** Non, c'est un système de règles ; son avantage est qu'il est explicable. Une évolution possible serait d'apprendre les poids à partir des projets réussis.
6. **Pourquoi les compétences sont-elles dans une seule colonne ?** Simplicité pour un mini-projet. Limite : pas de requêtes précises par compétence ; amélioration : tables `skills` et `designer_skills` (relation N-N).
7. **Comment éviter qu'un client modifie le projet d'un autre ?** Chaque requête contient `WHERE id = ? AND client_id = ?` avec l'identifiant pris dans la session, pas dans le formulaire.
8. **Que se passe-t-il si deux designers acceptent en même temps ?** Le passage en « en cours » est un `UPDATE ... WHERE status = 'ouvert'` dans une **transaction** : seul le premier réussit, le second reçoit « projet plus disponible » ; les autres invitations passent à « annulée ». (Ce cas de concurrence précis n'a pas été testé avec deux requêtes réellement simultanées.)

---

## 10. Pistes d'amélioration (si on vous le demande)

Tables `skills` N-N · pagination · envoi de fichiers d'images · réinitialisation du mot de passe par email · limitation des tentatives de connexion · rôle administrateur · messagerie entre client et designer · vraie API d'IA pour l'assistant (avec clé stockée hors du code).
