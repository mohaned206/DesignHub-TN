-- Mise à jour SANS perte de données : ajoute le rôle administrateur.
-- phpMyAdmin > base designhub_tn > Importer (ou onglet SQL) > exécuter une seule fois.
-- Compte créé : admin@designhub.tn / mot de passe admin1234
SET NAMES utf8mb4;
USE designhub_tn;
ALTER TABLE users MODIFY role ENUM('client','designer','admin') NOT NULL;
CREATE TABLE IF NOT EXISTS admin_logs (
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
INSERT IGNORE INTO users (full_name, email, password_hash, role)
VALUES ('Administrateur DesignHub', 'admin@designhub.tn', '$2y$10$rkGQ2LrQGtXtQzMBS9WKNugTyqxT9A0DiUPMLnOvKDtknimWMzHgi', 'admin');
