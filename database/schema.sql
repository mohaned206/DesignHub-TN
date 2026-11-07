-- ============================================================
-- DesignHub-TN : base de données MySQL / MariaDB (XAMPP)
-- Import : phpMyAdmin > Importer > choisir ce fichier
-- Tous les comptes de démonstration ont le mot de passe : demo1234
-- ============================================================

SET NAMES utf8mb4;   -- le fichier est en UTF-8 (accents)

-- On repart d'une base vide : évite tout état intermédiaire d'un import précédent
SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS designhub_tn;
CREATE DATABASE designhub_tn
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE designhub_tn;

-- ------------------------------------------------------------
-- 1) Utilisateurs (clients ET designers) : sert à l'authentification
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,              -- résultat de password_hash(), jamais le mot de passe
  role          ENUM('client','designer','admin') NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2) Profil du designer (relation 1-1 avec users)
--    skills = liste de compétences séparées par des virgules
-- ------------------------------------------------------------
CREATE TABLE designer_profiles (
  user_id     INT UNSIGNED PRIMARY KEY,
  specialty   VARCHAR(50)  NOT NULL DEFAULT 'UI/UX Design',
  skills      VARCHAR(255) NOT NULL DEFAULT '',
  city        VARCHAR(60)  NOT NULL DEFAULT 'Tunis',
  hourly_rate DECIMAL(8,2) NOT NULL DEFAULT 50,     -- TND / heure
  bio         TEXT,
  avatar_url  VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3) Portfolio : plusieurs réalisations par designer
-- ------------------------------------------------------------
CREATE TABLE portfolio_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  designer_id INT UNSIGNED NOT NULL,
  title       VARCHAR(120) NOT NULL,
  category    VARCHAR(50)  NOT NULL,
  image_url   VARCHAR(255) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (designer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4) Marketplace : ressources graphiques publiées par les designers
-- ------------------------------------------------------------
CREATE TABLE resources (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id   INT UNSIGNED NOT NULL,
  title       VARCHAR(120) NOT NULL,
  category    ENUM('Template','Mockup','Pack graphique') NOT NULL,
  folder      VARCHAR(100) DEFAULT NULL,           -- dossier (collection) regroupant plusieurs ressources du même auteur
  description VARCHAR(500) NOT NULL DEFAULT '',
  price       DECIMAL(8,2) NOT NULL DEFAULT 0,      -- 0 = gratuit (aucun paiement réel dans le projet)
  image_url   VARCHAR(255) DEFAULT NULL,           -- aperçu : URL ou chemin uploads/previews/...
  file_path   VARCHAR(120) DEFAULT NULL,           -- fichier source privé (nom aléatoire dans uploads/files/)
  file_name   VARCHAR(150) DEFAULT NULL,           -- nom d'origine affiché au téléchargement (ex. logo.psd)
  file_size   INT UNSIGNED DEFAULT NULL,           -- taille en octets
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5) Projets publiés par les clients
--    status : ouvert -> en_cours (un designer accepte) -> termine
--             ou annule (le client annule tant qu'il est ouvert)
-- ------------------------------------------------------------
CREATE TABLE projects (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id     INT UNSIGNED NOT NULL,
  designer_id   INT UNSIGNED DEFAULT NULL,          -- rempli quand un designer accepte
  title         VARCHAR(150) NOT NULL,
  category      VARCHAR(50)  NOT NULL,
  skills        VARCHAR(255) NOT NULL DEFAULT '',   -- compétences demandées (séparées par des virgules)
  budget        DECIMAL(10,2) NOT NULL,             -- TND
  deadline_days INT UNSIGNED NOT NULL,
  description   TEXT NOT NULL,
  status        ENUM('ouvert','en_cours','termine','annule') NOT NULL DEFAULT 'ouvert',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id)   REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (designer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6) Invitations : un client propose un projet à un designer
--    UNIQUE : on ne peut pas inviter deux fois le même designer
-- ------------------------------------------------------------
CREATE TABLE project_invitations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  designer_id INT UNSIGNED NOT NULL,
  status      ENUM('en_attente','acceptee','refusee','annulee') NOT NULL DEFAULT 'en_attente',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_invitation (project_id, designer_id),
  FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (designer_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7) Évaluations : un client note un designer après un projet terminé
--    UNIQUE(project_id) : une seule évaluation par projet
-- ------------------------------------------------------------
CREATE TABLE reviews (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL UNIQUE,
  client_id   INT UNSIGNED NOT NULL,
  designer_id INT UNSIGNED NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  comment     VARCHAR(500) NOT NULL DEFAULT '',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),
  FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id)   REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (designer_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;


-- ------------------------------------------------------------
-- 8) Propositions : un designer se propose sur un projet ouvert (prix + délai + message)
--    Le client accepte ou refuse. UNIQUE : une seule proposition par designer et par projet
-- ------------------------------------------------------------
CREATE TABLE proposals (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  designer_id INT UNSIGNED NOT NULL,
  price       DECIMAL(10,2) NOT NULL,               -- TND
  delay_days  INT UNSIGNED NOT NULL,
  message     VARCHAR(600) NOT NULL,
  status      ENUM('en_attente','acceptee','refusee','annulee') NOT NULL DEFAULT 'en_attente',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_proposal (project_id, designer_id),
  FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (designer_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9) Collaboration : tâches (étapes) d'un projet en cours
-- ------------------------------------------------------------
CREATE TABLE project_tasks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  status      ENUM('a_faire','en_cours','fait') NOT NULL DEFAULT 'a_faire',
  created_by  INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10) Collaboration : messages échangés entre le client et le designer d'un projet
-- ------------------------------------------------------------
CREATE TABLE project_messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  body        VARCHAR(1000) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11) Commandes : un utilisateur obtient une ressource du marketplace
--     SIMULATION : aucun paiement réel. UNIQUE : une ressource n'est obtenue qu'une fois par personne
-- ------------------------------------------------------------
CREATE TABLE orders (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resource_id INT UNSIGNED NOT NULL,
  buyer_id    INT UNSIGNED NOT NULL,
  price       DECIMAL(8,2) NOT NULL DEFAULT 0,      -- prix au moment de la commande
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_order (resource_id, buyer_id),
  FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id)    REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- Journal de modération : chaque suppression faite par un administrateur (qui, quoi, quand, pourquoi)
CREATE TABLE admin_logs (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id     INT UNSIGNED NULL,
  action       VARCHAR(40)  NOT NULL,
  target_type  VARCHAR(20)  NOT NULL,
  target_id    INT UNSIGNED NOT NULL,
  target_label VARCHAR(190) NOT NULL,
  reason       VARCHAR(300) NOT NULL DEFAULT '',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- DONNÉES DE DÉMONSTRATION (fictives)
-- Mot de passe de tous les comptes : demo1234
-- ============================================================
SET @pwd = '$2y$10$dDJeF6Lb4sYM/rFgwQwIiOdnwUlTBEqrFPkUYDWog5/ezWcBrww3m';

INSERT INTO users (id, full_name, email, password_hash, role) VALUES
  (1, 'Tech Start Tunisie',  'client@designhub.tn',  @pwd, 'client'),
  (2, 'Boutique Médina',     'client2@designhub.tn', @pwd, 'client'),
  (3, 'Yassine Ben Salem',   'yassine@designhub.tn', @pwd, 'designer'),
  (4, 'Syrine Trabelsi',     'syrine@designhub.tn',  @pwd, 'designer'),
  (5, 'Karim Chaabane',      'karim@designhub.tn',   @pwd, 'designer'),
  (6, 'Amira Gharbi',        'amira@designhub.tn',   @pwd, 'designer');

-- Compte administrateur : admin@designhub.tn / admin1234
INSERT INTO users (id, full_name, email, password_hash, role) VALUES
  (7, 'Administrateur DesignHub', 'admin@designhub.tn', '$2y$10$rkGQ2LrQGtXtQzMBS9WKNugTyqxT9A0DiUPMLnOvKDtknimWMzHgi', 'admin');

INSERT INTO designer_profiles (user_id, specialty, skills, city, hourly_rate, bio, avatar_url) VALUES
  (3, 'UI/UX Design', 'figma, prototypage, design system, mobile', 'Tunis', 70,
   'UI/UX Designer avec 5 ans d''expérience dans la conception de produits numériques et d''interfaces SaaS en Tunisie.',
   'images/avatars/yassine.jpg'),
  (4, 'Branding & Logo', 'illustrator, charte graphique, logo, packaging', 'Sousse', 55,
   'Spécialiste en identité de marque, création de logos épurés et direction artistique pour startups et PME.',
   'images/avatars/syrine.jpg'),
  (5, 'Illustration & 3D', 'blender, cinema 4d, rendu 3d, illustration', 'Sfax', 80,
   'Artiste 3D et illustrateur spécialisé dans la modélisation Blender et les visuels isométriques.',
   'images/avatars/karim.jpg'),
  (6, 'Motion Design', 'after effects, animation, montage, logo animé', 'Ariana', 65,
   'Motion designer : animations de logos, vidéos explicatives et habillage pour les réseaux sociaux.', 'images/avatars/amira.jpg');

INSERT INTO portfolio_items (designer_id, title, category, image_url, description) VALUES
  (3, 'Application Web FinTech Carthage', 'UI/UX Design',
   'images/portfolio/fintech.jpg',
   'Interface moderne pour la gestion bancaire locale.'),
  (3, 'E-commerce Artisanat Tunisien', 'UI/UX Design',
   'images/portfolio/ecommerce.jpg',
   'Expérience d''achat mobile pour des produits d''artisanat.'),
  (4, 'Branding Café Culturel Sousse', 'Branding & Logo',
   'images/portfolio/cafe.jpg',
   'Refonte complète du logo, des menus et de l''enseigne.'),
  (5, 'Objets 3D Isométriques Smart City', 'Illustration & 3D',
   'images/portfolio/smartcity.jpg',
   'Modélisation 3D pour une présentation d''architecture.');

INSERT INTO resources (author_id, title, category, description, price, image_url) VALUES
  (3, 'Tunisia Travel Mobile App UI Kit', 'Template',
   'Kit d''écrans mobiles (accueil, réservation, profil) au format Figma.', 0,
   'images/resources/uikit.jpg'),
  (4, 'Branding Packaging Mockup Box', 'Mockup',
   'Mockup de boîte d''emballage au format PSD pour présenter une identité de marque.', 0,
   'images/resources/packaging.jpg'),
  (5, 'Pack Icônes Vectorielles Méditerranée', 'Pack graphique',
   '120 icônes vectorielles (SVG) inspirées de l''artisanat méditerranéen.', 15,
   'images/resources/icons.jpg'),
  (5, 'Studio 3D Géométrique pour Blender', 'Pack graphique',
   'Scène de studio prête à l''emploi pour présenter vos objets 3D.', 25,
   'images/resources/studio3d.jpg'),
  (6, 'Template Animation de Logo (After Effects)', 'Template',
   'Trois modèles d''animation de logo modifiables.', 20, 'images/resources/logoanim.jpg');

INSERT INTO projects (id, client_id, designer_id, title, category, skills, budget, deadline_days, description, status) VALUES
  (1, 1, NULL, 'Charte graphique et logo pour startup numérique', 'Branding & Logo',
   'illustrator, charte graphique, logo', 1500, 10,
   'Nous cherchons un designer pour créer notre identité visuelle complète : logo, typographie et guide de marque.', 'ouvert'),
  (2, 1, NULL, 'Design UI/UX d''un tableau de bord B2B', 'UI/UX Design',
   'figma, prototypage, design system', 2200, 20,
   'Maquettage complet sous Figma d''une application web d''analyse de données logistiques.', 'ouvert'),
  (3, 2, 4, 'Refonte du logo de la boutique', 'Branding & Logo',
   'illustrator, logo', 900, 7,
   'Moderniser le logo de notre boutique d''artisanat à la Médina.', 'termine'),
  (4, 1, 6, 'Animation du logo pour les réseaux sociaux', 'Motion Design',
   'after effects, logo animé', 700, 5,
   'Animation de 5 secondes de notre logo pour Instagram et LinkedIn.', 'en_cours');

INSERT INTO project_invitations (project_id, designer_id, status) VALUES
  (1, 4, 'en_attente'),
  (3, 4, 'acceptee'),
  (4, 6, 'acceptee');

-- Évaluation de démonstration (fictive)
INSERT INTO reviews (project_id, client_id, designer_id, rating, comment) VALUES
  (3, 2, 4, 5, 'Travail soigné et livré dans les délais.');

-- ------------------------------------------------------------
-- Données de démonstration ajoutées (fictives) : tendances, collaboration, commandes
-- ------------------------------------------------------------
INSERT INTO projects (id, client_id, designer_id, title, category, skills, budget, deadline_days, description, status, created_at) VALUES
  (5, 2, NULL, 'Identité visuelle pour un restaurant', 'Branding & Logo',
   'logo, charte graphique, photoshop', 1200, 14,
   'Création du logo, des menus et de la signalétique d''un nouveau restaurant à La Marsa.', 'ouvert', DATE_SUB(NOW(), INTERVAL 12 DAY)),
  (6, 1, NULL, 'Application mobile de réservation', 'UI/UX Design',
   'figma, mobile, prototypage', 3000, 30,
   'Conception des écrans et du prototype cliquable d''une application mobile de réservation de salles.', 'ouvert', DATE_SUB(NOW(), INTERVAL 6 DAY)),
  (7, 2, NULL, 'Illustrations 3D pour un site e-commerce', 'Illustration & 3D',
   'blender, rendu 3d', 1800, 15,
   'Une série de 6 visuels 3D pour la page d''accueil d''une boutique en ligne.', 'ouvert', DATE_SUB(NOW(), INTERVAL 3 DAY)),
  (8, 1, NULL, 'Refonte UI d''un site vitrine', 'UI/UX Design',
   'figma, design system, responsive', 1600, 15,
   'Moderniser l''interface d''un site vitrine : nouvelle grille, composants et version mobile.', 'ouvert', DATE_SUB(NOW(), INTERVAL 2 DAY)),
  (9, 2, 3, 'Maquettes d''une application de livraison', 'UI/UX Design',
   'figma, mobile', 2000, 20,
   'Maquettes complètes d''une application de livraison de repas.', 'termine', DATE_SUB(NOW(), INTERVAL 70 DAY));

INSERT INTO project_invitations (project_id, designer_id, status) VALUES
  (9, 3, 'acceptee');

INSERT INTO reviews (project_id, client_id, designer_id, rating, comment) VALUES
  (9, 2, 3, 4, 'Bonnes maquettes, communication claire.');

INSERT INTO proposals (project_id, designer_id, price, delay_days, message, status) VALUES
  (2, 3, 2000, 18, 'Bonjour, j''ai conçu plusieurs tableaux de bord SaaS sous Figma. Je peux livrer le design system et le prototype en 18 jours.', 'en_attente'),
  (8, 3, 1500, 12, 'Je propose une refonte complète avec composants réutilisables et version mobile.', 'en_attente');

INSERT INTO project_tasks (project_id, title, status, created_by) VALUES
  (4, 'Validation du storyboard', 'fait', 6),
  (4, 'Animation du logo (5 secondes)', 'en_cours', 6),
  (4, 'Export aux formats Instagram et LinkedIn', 'a_faire', 6);

INSERT INTO project_messages (project_id, user_id, body) VALUES
  (4, 1, 'Bonjour Amira, voici notre logo en SVG. Nous aimerions un rendu dynamique mais sobre.'),
  (4, 6, 'Merci ! Je vous envoie le storyboard demain pour validation.'),
  (4, 1, 'Parfait, nous restons disponibles pour vos retours.');

INSERT INTO orders (resource_id, buyer_id, price) VALUES
  (1, 1, 0),
  (3, 1, 15),
  (3, 4, 15),
  (4, 2, 25);

SET FOREIGN_KEY_CHECKS = 1;
