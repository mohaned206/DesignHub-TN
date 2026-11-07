-- ============================================================
-- DesignHub-TN : mise à jour SANS perte de données
-- À utiliser si vous avez déjà importé schema.sql auparavant.
-- phpMyAdmin > base designhub_tn > Importer > ce fichier.
-- Ajoute uniquement les 4 nouvelles tables (si elles n'existent pas déjà).
-- ============================================================
SET NAMES utf8mb4;
USE designhub_tn;

CREATE TABLE IF NOT EXISTS proposals (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  designer_id INT UNSIGNED NOT NULL,
  price       DECIMAL(10,2) NOT NULL,
  delay_days  INT UNSIGNED NOT NULL,
  message     VARCHAR(600) NOT NULL,
  status      ENUM('en_attente','acceptee','refusee','annulee') NOT NULL DEFAULT 'en_attente',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_proposal (project_id, designer_id),
  FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (designer_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_tasks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  status      ENUM('a_faire','en_cours','fait') NOT NULL DEFAULT 'a_faire',
  created_by  INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  body        VARCHAR(1000) NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  resource_id INT UNSIGNED NOT NULL,
  buyer_id    INT UNSIGNED NOT NULL,
  price       DECIMAL(8,2) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_order (resource_id, buyer_id),
  FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id)    REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB;
