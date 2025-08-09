---
description: Configurer Windsurf pour reconnaître WordPress/WooCommerce (stubs) et lancer la Phase 1 de consolidation
---

# Contexte et objectif
- Les erreurs « fonction/méthode indéfinie » (ex: `wp_schedule_event`, `WC()`, `wp_verify_nonce`, etc.) proviennent du fait que Windsurf analyse le code du plugin hors contexte WordPress. Les APIs WP/WC ne sont pas chargées, donc l’indexation ne les « voit » pas.
- Les stubs PHP pour WP/WC fournissent des signatures de fonctions/classes pour l’analyse statique uniquement (pas d’exécution). Ils sont déjà déclarés dans `composer.json` via `require-dev`.
- Objectif: installer les stubs, forcer l’indexation par l’IDE, vérifier la disparition des faux positifs, puis poursuivre la Phase 1 (consolidation).

# Prérequis
- PHP et Composer installés (Windows): `php -v` et `composer -V` doivent fonctionner dans le terminal.
- Répertoire plugin: `life-travel-excursion/`

# Étapes
1) Vérifier la présence des stubs dans `composer.json` (déjà OK)
   - `php-stubs/wordpress-stubs` (dev)
   - `php-stubs/woocommerce-stubs` (dev)
   - Ne pas autoloader ces stubs via la clé `files` de Composer (risque de redéclaration à l’exécution). Ils servent uniquement à l’indexation.

2) Installer les dépendances Composer (incluant les stubs)
   - Ouvrir un terminal dans le dossier du plugin.
   - Commande:
     
     // turbo
     powershell -NoProfile -ExecutionPolicy Bypass -Command "composer install --no-interaction --prefer-dist"

3) Optimiser l’autoload et rafraîchir l’indexation
   - Commande:
     
     // turbo
     powershell -NoProfile -ExecutionPolicy Bypass -Command "composer dump-autoload -o"
   - Puis relancer l’indexation du langage dans Windsurf (redémarrage de la fenêtre/serveur de langage ou simple reload du workspace).

4) Vérifier la reconnaissance des APIs WP/WC
   - Ouvrir `life-travel-excursion/includes/abandoned-cart-recovery.php`.
   - Vérifier que des fonctions comme `wp_verify_nonce`, `wp_next_scheduled`, `wp_mail`, `WC()` n’apparaissent plus en « undefined ».
   - Si des avertissements persistent, assurez-vous que `vendor/` est bien présent et indexé (pas exclu par les réglages de l’IDE) et relancez l’indexation.

5) (Optionnel) Outillage d’analyse (PHPStan)
   - Installer: `composer require --dev phpstan/phpstan:^1.11`
   - Créer un `phpstan.neon` minimal et référencer les stubs via `autoload_files` ou `scanDirectories` (ne pas exécuter ces fichiers dans l’app WordPress en runtime, ils servent uniquement à l’analyse).
   - Lancer: `vendor/bin/phpstan analyse -l 5 life-travel-excursion/`

6) Validation doublons critiques (constat actuel)
   - `life_travel_excursion_display_booking_form()` — 1 occurrence trouvée.
   - `life_travel_excursion_get_pricing_details()` — 1 occurrence trouvée.
   - `life_travel_excursion_check_simultaneous_excursions()` — 1 occurrence trouvée.
   - Si vous rencontrez à nouveau des redéfinitions, supprimer la 2e occurrence et conserver la 1re.

# Bonnes pratiques
- N’ajoutez pas les stubs dans `autoload.files` de Composer, pour éviter toute collision avec WordPress/WooCommerce en environnement d’exécution.
- Gardez `vendor/` dans le workspace pour permettre à l’IDE d’indexer les stubs.
- En cas de faux positifs résiduels, déclenchez un « rebuild index » du langage.

# Prochaine étape (Phase 1)
- Poursuivre l’analyse des redondances CPT vs Produits WooCommerce, centraliser la gestion médias, et rationaliser l’UI admin.
- Consolider progressivement en s’appuyant sur la reconnaissance correcte des APIs WP/WC dans l’IDE.
