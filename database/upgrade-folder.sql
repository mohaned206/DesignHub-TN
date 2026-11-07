-- Mise à jour SANS perte de données : ajoute la colonne « dossier » aux ressources.
-- phpMyAdmin > base designhub_tn > onglet SQL > coller > Exécuter (une seule fois).
USE designhub_tn;
ALTER TABLE resources ADD COLUMN folder VARCHAR(100) DEFAULT NULL AFTER category;
