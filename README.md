# DesignHub-TN

Plateforme web de mise en relation entre **clients** et **designers freelance** (mini-projet de Licence GTIC).
Technologies : HTML, CSS, JavaScript simple, **PHP 8 (PDO)**, **MySQL / MariaDB**, XAMPP.

## Installation sous XAMPP (Windows)

1. **Installer XAMPP** (PHP 8.0 ou plus récent) puis ouvrir le *XAMPP Control Panel*.
2. **Démarrer** les modules **Apache** et **MySQL** (boutons *Start*).
3. **Copier le dossier** `DesignHub-TN` dans `C:\xampp\htdocs\`
   (le résultat doit être `C:\xampp\htdocs\DesignHub-TN\index.php`).
4. **Créer la base de données** :
   - ouvrir <http://localhost/phpmyadmin> ;
   - cliquer sur l'onglet **Importer** ;
   - choisir le fichier `database/schema.sql` puis cliquer sur **Importer**.
   Le script crée la base `designhub_tn`, les tables et des données de démonstration.
5. **Ouvrir le site** : <http://localhost/DesignHub-TN/>

> Configuration par défaut de XAMPP : utilisateur MySQL `root`, **sans mot de passe**.
> Si le vôtre est différent, modifiez les 4 constantes en haut de `config/database.php`.

### Comptes de démonstration (mot de passe : `demo1234`)

| Rôle | Email |
|---|---|
| Client | `client@designhub.tn` (a des projets ouverts, en cours) |
| Client | `client2@designhub.tn` |
| Designer | `yassine@designhub.tn` (UI/UX) |
| Designer | `syrine@designhub.tn` (Branding, a une invitation en attente) |
| Designer | `karim@designhub.tn` · `amira@designhub.tn` |
| **Administrateur** | `admin@designhub.tn` (mot de passe : `admin1234`) |

Ces comptes sont **fictifs** et ne servent qu'à la démonstration. Vous pouvez aussi créer vos propres comptes.

### Scénario de démonstration conseillé (soutenance)

1. Se connecter en **client** → *Projets* → publier un projet (catégorie *Branding & Logo*, compétences `illustrator, logo`).
2. Ouvrir *Matching* : les designers sont classés avec le détail des points → cliquer *Proposer le projet* au premier.
3. Se déconnecter, se connecter en **designer** (`syrine@designhub.tn`) → *Mon espace* → **Accepter** l'invitation.
4. Se reconnecter en **client** → ouvrir le projet → **Marquer comme terminé** → **Évaluer** le designer.
5. Ouvrir le profil du designer : la note moyenne et l'avis apparaissent (calculés depuis la base).

## Si ça ne marche pas

| Problème | Solution |
|---|---|
| « Connexion à la base de données impossible » | MySQL n'est pas démarré dans XAMPP, ou `schema.sql` n'a pas été importé, ou mot de passe `root` différent (voir `config/database.php`). |
| Page blanche / code PHP affiché | Le site doit être ouvert par `http://localhost/...` et non en double-cliquant sur le fichier. |
| Erreur 404 | Vérifier que le dossier s'appelle bien `DesignHub-TN` dans `htdocs`. |
| Images absentes | Toutes les images sont dans le dossier `images/` (aucune URL externe). Si vous aviez déjà importé une ancienne base, exécutez `database/upgrade-images.sql`. |
| Réinitialiser les données | Réimporter `database/schema.sql` (il supprime puis recrée les tables). |

## Organisation du projet

```
DesignHub-TN/
├── index.php              Accueil (statistiques réelles + designers à la une)
├── designers.php          Annuaire : recherche et filtres (GET)
├── designer-profile.php   Profil public : portfolio, avis, invitation
├── marketplace.php        Ressources graphiques par catégorie
├── projects.php           Liste des projets + création (client)
├── project-details.php    Détail, statut, invitations, évaluation
├── project-action.php     Traite les actions sur les projets (POST)
├── matching.php           Matching dans les deux sens (clients ↔ designers)
├── trends.php             Tendances : offre/demande, compétences, activité, marketplace
├── assistant.php          Assistant portfolio : score /100, tendances, analyse IA facultative
├── resource-action.php    Commande (simulée) d'une ressource du marketplace
├── login.php / register.php / logout.php
├── dashboard.php          Espace client ou designer
├── edit-profile.php       Designer : profil, portfolio, ressources (CRUD)
├── config/database.php    Connexion MySQL (PDO)
├── config/ai.php          Clé d'API IA (facultatif)
├── includes/              functions.php · matching.php · trends.php · ai.php · header.php · footer.php
├── css/style.css          Styles
├── js/app.js              Petites interactions
├── database/schema.sql    Tables + données de démonstration
└── docs/RESUME_TECHNIQUE.md   Explication complète pour la soutenance
```

Voir **`docs/RESUME_TECHNIQUE.md`** pour l'architecture, les tables, les fonctions principales, l'état de chaque fonctionnalité et des questions probables du jury.

## Ce qui est stocké où

| Données | Où | Persistant ? |
|---|---|---|
| Comptes (mots de passe hachés), profils, portfolios, ressources, projets, invitations, avis | **MySQL** | Oui |
| Identifiant de l'utilisateur connecté, jeton CSRF, message flash | **Session PHP** (serveur) | Non : jusqu'à la déconnexion / fermeture du navigateur |
| Résultat du matching et conseils de l'assistant | Calculés à chaque affichage | Non (recalculés depuis MySQL) |

`localStorage` n'est plus utilisé : l'ancienne version stockait tout dans le navigateur.

## Limites assumées

- **Le matching n'est pas un modèle d'IA entraîné** : c'est un score à règles transparentes (voir `includes/matching.php`).
- **L'assistant portfolio** fonctionne à règles ; une analyse par IA n'est activée que si une clé d'API est mise dans `config/ai.php` (facultatif).
- Les commandes du marketplace sont **simulées** : pas de paiement, pas de fichier téléchargeable réel.
- La messagerie de projet est un fil de messages simple (pas de temps réel, pas de pièces jointes).
- Les tendances sont des statistiques descriptives sur une petite base de démonstration.
- Pas d'envoi d'images (on saisit l'URL d'une image) ni de réinitialisation de mot de passe par email.
- Pas de limitation du nombre de tentatives de connexion (à ajouter en production).
- Pas de HTTPS en local ; en production, activer HTTPS et `session.cookie_secure`.


## Nouveautés de cette version

| Domaine | Ajout |
|---|---|
| Matching | 5 critères (spécialité 35, compétences 30, budget 15, portfolio 10, **réputation 10**) ; utilisable aussi par les **designers** (projets recommandés) |
| Projets | Les designers peuvent **se proposer** (prix, délai, message) ; le client accepte ou refuse |
| Collaboration | Espace par projet : **tâches** (À faire / En cours / Fait), **barre d'avancement**, **messagerie** client ↔ designer |
| Marketplace | Filtre gratuit/payant, **tri**, compteur d'obtentions, **commande simulée** (table `orders`) |
| Tendances | Nouvelle page `trends.php` : demande vs offre par catégorie, compétences, activité sur 6 mois, ressources populaires |
| Assistant | **Score de profil /100**, compétences demandées manquantes, positionnement tarifaire, exemple de présentation, **analyse IA facultative** |
| Tableaux de bord | Budget engagé, propositions, avancement, revenus, ventes, recommandations, ressources obtenues |

> Après mise à jour, **réimportez `database/schema.sql`** (nouvelles tables : `proposals`, `project_tasks`, `project_messages`, `orders`). Cela réinitialise les données de démonstration.

### Scénario de démonstration (nouveautés)

1. Designer `yassine@designhub.tn` → *Mon espace* : projets recommandés → ouvrir un projet → **Envoyer une proposition**.
2. Client `client@designhub.tn` → *Mon espace* : propositions à examiner → **Accepter** : le projet passe « en cours ».
3. Dans le projet : ajouter des tâches, changer leur statut, écrire un message (l'avancement se met à jour).
4. *Marketplace* : trier/filtrer, **Commander** une ressource, la retrouver dans *Mon espace*.
5. *Tendances* puis *Assistant portfolio* (connecté en designer) : score, compétences à ajouter.


## Fichiers du marketplace (upload / téléchargement)

- **Publier une ressource** : *Modifier mon profil* > « Publier une ressource » : aperçu (JPG/PNG/WebP, 5 Mo) + fichier source (PSD, ZIP, AI, FIG…, 100 Mo).
- **Import en masse** : déposer `nom.jpg` + `nom.psd` dans `import/`, puis ouvrir `import-templates.php` (local uniquement).
- **Sécurité** : les aperçus sont dans `uploads/previews/` (images vérifiées par leur contenu) ; les fichiers sources sont dans `uploads/files/`, **bloqués par .htaccess** et servis uniquement par `download.php` à l'auteur et aux utilisateurs qui ont obtenu la ressource.
- Pour des PSD volumineux, augmenter `upload_max_filesize` et `post_max_size` dans `C:\xampp\php\php.ini`, puis redémarrer Apache.
- Base existante : exécuter `database/upgrade-files.sql` (ou réinstaller avec `install.php`).


## Rôle administrateur

Connexion avec `admin@designhub.tn` : l'espace `admin.php` affiche les statistiques générales et permet de supprimer un utilisateur, un projet, une ressource ou un avis non conforme. Chaque suppression exige un motif et est tracée dans le journal (`admin_logs`). Base déjà installée : importer `database/upgrade-admin.sql`.
