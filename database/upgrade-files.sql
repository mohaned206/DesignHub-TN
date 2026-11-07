-- Mise à jour SANS perte de données : ajoute les colonnes de fichiers à la table resources.
-- phpMyAdmin > base designhub_tn > onglet SQL > coller > Exécuter (une seule fois).
USE designhub_tn;
ALTER TABLE resources
  ADD COLUMN file_path VARCHAR(120) DEFAULT NULL AFTER image_url,
  ADD COLUMN file_name VARCHAR(150) DEFAULT NULL AFTER file_path,
  ADD COLUMN file_size INT UNSIGNED DEFAULT NULL AFTER file_name;
