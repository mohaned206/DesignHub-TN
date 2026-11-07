-- Mise à jour SANS perte de données : remplace les anciennes URL d'images (Internet) par les images locales du dossier images/.
-- phpMyAdmin > base designhub_tn > Importer (ou onglet SQL) > exécuter une seule fois.
SET NAMES utf8mb4;
USE designhub_tn;
UPDATE designer_profiles SET avatar_url = 'images/avatars/yassine.jpg' WHERE user_id = 3 AND avatar_url LIKE 'http%';
UPDATE designer_profiles SET avatar_url = 'images/avatars/syrine.jpg'  WHERE user_id = 4 AND avatar_url LIKE 'http%';
UPDATE designer_profiles SET avatar_url = 'images/avatars/karim.jpg'   WHERE user_id = 5 AND avatar_url LIKE 'http%';
UPDATE designer_profiles SET avatar_url = 'images/avatars/amira.jpg'   WHERE user_id = 6 AND (avatar_url IS NULL OR avatar_url LIKE 'http%');
UPDATE portfolio_items SET image_url = 'images/portfolio/fintech.jpg'    WHERE image_url LIKE 'http%' AND title LIKE '%FinTech%';
UPDATE portfolio_items SET image_url = 'images/portfolio/ecommerce.jpg'  WHERE image_url LIKE 'http%' AND title LIKE '%E-commerce%';
UPDATE portfolio_items SET image_url = 'images/portfolio/cafe.jpg'       WHERE image_url LIKE 'http%' AND title LIKE '%Café%';
UPDATE portfolio_items SET image_url = 'images/portfolio/smartcity.jpg'  WHERE image_url LIKE 'http%' AND title LIKE '%Smart City%';
UPDATE resources SET image_url = 'images/resources/uikit.jpg'     WHERE title LIKE '%UI Kit%'   AND (image_url IS NULL OR image_url LIKE 'http%');
UPDATE resources SET image_url = 'images/resources/packaging.jpg' WHERE title LIKE '%Packaging%' AND (image_url IS NULL OR image_url LIKE 'http%');
UPDATE resources SET image_url = 'images/resources/icons.jpg'     WHERE title LIKE '%Icônes%'   AND (image_url IS NULL OR image_url LIKE 'http%');
UPDATE resources SET image_url = 'images/resources/studio3d.jpg'  WHERE title LIKE '%Studio 3D%' AND (image_url IS NULL OR image_url LIKE 'http%');
UPDATE resources SET image_url = 'images/resources/logoanim.jpg'  WHERE title LIKE '%Animation de Logo%' AND (image_url IS NULL OR image_url LIKE 'http%');
