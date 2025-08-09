# Plan d'Intégration Optimisé pour Life Travel Excursion (Version IA-Collaborative)

## 1. Introduction et Objectifs Fondamentaux

Ce plan d'intégration révisé a été méticuleusement optimisé pour une implémentation assistée par une IA avancée (type Claude 3.7 Sonnet) dans un environnement de développement sophistiqué (type Windsurf). Il adopte une approche progressive, granulaire et testable, visant à livrer une solution complète et robuste en 30 jours. Ce plan conserve toutes les fonctionnalités prévues initialement mais les structure pour une collaboration homme-IA efficace, en mettant l'accent sur la clarté, la validation continue et la mitigation des risques.

### 1.1. Objectifs Prioritaires (Inchangés)

1.  **Implémentation Prudente et Haute Fiabilité**: Approche progressive avec validation continue à chaque micro-étape.
2.  **Robustesse Exceptionnelle**: Adaptation rigoureuse aux conditions réseau spécifiques du Cameroun.
3.  **Sécurité Optimale**: Application systématique des principes de validation robustes (inspirés de `sync_abandoned_cart()`) à tous les niveaux.
4.  **Expérience Utilisateur Optimisée**: Interface intuitive et performante, adaptée aux contraintes locales.
5.  **Administration Simplifiée**: Gestion claire et sécurisée via l'interface web et potentiellement WhatsApp.

### 1.2. Approche Méthodologique (Étendue pour Collaboration IA)

1.  **Développement Incrémental Assisté par IA**: Chaque module est décomposé en micro-tâches précises, implémentées et validées individuellement.
2.  **Tests Systématiques et Continus**: Validation à chaque micro-étape et tests d'intégration à la fin de chaque journée et cycle. L'IA sera sollicitée pour aider à la génération de cas de test.
3.  **Intégration Continue et Déploiement Fréquent**: Petites améliorations validées déployées régulièrement (sur un environnement de staging).
4.  **API-First (si applicable)**: Définition claire des contrats d'interface avant l'implémentation, facilitant les interactions modulaires.
5.  **Rétrocompatibilité et Robustesse**: Garantie de fonctionnement optimal, y compris en conditions réseau dégradées.
6.  **Documentation Dynamique**: La documentation (code, API, décisions) est mise à jour continuellement par l'IA sous supervision humaine.

## 2. Principes Directeurs pour la Collaboration avec l'IA (Claude 3.7 Sonnet sur Windsurf)

Pour maximiser l'efficacité de la collaboration avec l'IA, ce plan adopte les principes suivants :

1.  **Granularité Extrême des Tâches**:
    *   Chaque objectif est décomposé en tâches atomiques.
    *   Pour chaque tâche : définition des `Inputs` (contexte, fichiers), `Action de l'IA`, `Outputs Attendus`, et `Critères de Succès/Validation`.
2.  **Contexte Explicite et Ciblé**:
    *   Fourniture précise des ressources (fichiers exacts, extraits de code, fonctions/classes concernées, documentation pertinente) pour chaque tâche afin d'optimiser la fenêtre de contexte de l'IA.
3.  **Pilotage Itératif et Prompts Séquencés**:
    *   Privilégier des séries de prompts pour guider l'IA sur les tâches complexes.
    *   Validation humaine des résultats intermédiaires avant de poursuivre.
4.  **Critères de Terminaison Clairs et Vérifiables**:
    *   Chaque tâche possède un état attendu du code et/ou des tests (unitaires, d'intégration simples) à satisfaire.
5.  **Anticipation des Limites de l'IA et Supervision Active**:
    *   Inclusion de "Points de Vigilance" pour les tâches susceptibles d'induire des solutions inutilement complexes ou des erreurs subtiles.
    *   Encouragement à demander à l'IA d'expliquer son code et ses choix.
6.  **Standardisation et Réutilisation**:
    *   Mettre en place des composants standards (ex: validation, logging) dès le début et s'assurer que l'IA les réutilise systématiquement.

## 3. Plan d'Implémentation Détaillé sur 30 Jours

### 3.1. Vue d'ensemble des Cycles d'Implémentation (Inchangée)

Le plan est réorganisé en 5 cycles de développement de 6 jours chacun, chaque cycle se terminant par une journée de validation et tests.

| Cycle | Jours | Focus                                     | Validation                                     |
| :---- | :---- | :---------------------------------------- | :--------------------------------------------- |
| **1** | 1-6   | Structure, Sécurité, Admin & Fondations   | Sécurité admin, logs, services de base       |
| **2** | 7-12  | Gestion Excursions & Fonctionnalités Core | Modèles de données, CRUD excursions, réservations |
| **3** | 13-18 | Intégration WhatsApp & Notifications      | Communication multicanal, API WhatsApp         |
| **4** | 19-24 | Offline, PWA & Optimisation Réseau        | Résilience hors ligne, stratégie de cache      |
| **5** | 25-30 | Paiements, Finalisation & Tests Globaux   | Système de paiement complet, tests E2E         |

### Cycle 1: Structure, Sécurité, Administration & Fondations (Jours 1-6)

**Objectif du Cycle**: Établir une base solide, sécurisée et administrable pour le plugin, incluant les services transversaux essentiels.

#### Jour 1: Initialisation, Analyse de Compatibilité & Services de Base

**Objectif de la Journée**: Analyser l'existant, configurer les services de validation et de logging, et définir l'architecture du cache.

1.  **Tâche 1.1: Analyse de Compatibilité Approfondie**
    *   **Fichiers/Modules Clés**: `life-travel-theme/functions.php`, `life-travel-core/life-travel-core.php`, CPT `excursion_custom`, WooCommerce hooks, TranslatePress, Excursion_Addon.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Cartographier exhaustivement les hooks WordPress existants utilisés par `life-travel-theme` et `life-travel-core` et leur impact potentiel.
        *   Analyser en détail les optimisations de performance déjà implémentées (ex: `defer`, WebP, stratégies de cache existantes) pour définir une stratégie de préservation et d'amélioration.
        *   Identifier les points d'extension les plus sécurisés et modulaires pour WooCommerce.
        *   Élaborer une stratégie d'intégration non disruptive avec TranslatePress et Excursion_Addon, en privilégiant l'extension plutôt que le remplacement.
    *   **Output Attendu**: Document `docs/architecture-analysis.md` mis à jour et complet.
    *   **Critères de Validation**: Le document identifie clairement les interactions, les optimisations à préserver, et les stratégies d'intégration.

2.  **Tâche 1.2: Développement du Framework de Validation Universel**
    *   **Fichiers/Modules Clés**: Nouveau fichier `inc/core/validation-framework.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Concevoir et implémenter un framework de validation centralisé, s'inspirant des principes robustes de `sync_abandoned_cart()` (validation complète des entrées, assainissement, gestion d'erreurs).
        *   Ce framework doit être facilement utilisable dans tout le plugin.
    *   **Séquence de Prompts Suggérée**:
        1.  "Propose une structure de classe pour `ValidationFramework` avec des méthodes statiques pour les types de validation courants (string, int, email, array, etc.)."
        2.  "Intègre des méthodes pour l'assainissement des données correspondantes."
        3.  "Assure-toi que chaque méthode de validation peut retourner des messages d'erreur clairs ou lever des exceptions standardisées."
    *   **Critères de Validation**: Le framework est créé, testable unitairement, et prêt à être utilisé. Tests avec divers inputs (valides/invalides).

3.  **Tâche 1.3: Mise en Place du Système de Gestion d'Erreurs et Logging Standardisé**
    *   **Fichiers/Modules Clés**: Nouveau fichier `inc/core/error-handling.php`, utilisation de `wc_get_logger()`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Développer un système de gestion d'erreurs uniforme.
        *   Implémenter une journalisation structurée et sécurisée en utilisant **systématiquement `wc_get_logger()`** pour toutes les erreurs et événements importants du plugin. Éviter `file_put_contents` ou `error_log` directs.
        *   Permettre la classification des erreurs (ex: `DEBUG`, `INFO`, `WARNING`, `ERROR`, `CRITICAL`).
        *   Adapter le formatage des messages d'erreur au contexte (admin/frontend/log).
    *   **Critères de Validation**: Le service de logging est fonctionnel, les logs sont écrits au bon endroit, et le format est correct.

4.  **Tâche 1.4: Définition Architecturale de la Stratégie de Cache Unifiée**
    *   **Fichiers/Modules Clés**: Documentation (nouveau `docs/cache-strategy.md`), analyse de `pwa-bridge.php`, `offline-bridge.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Analyser les mécanismes de cache existants (PWA, WordPress Object Cache, optimisations du thème).
        *   Définir une architecture pour une stratégie de cache unifiée : quels types de données (assets, API, contenu offline), quelles technologies (Service Worker, Transients API, Object Cache), et comment assurer la cohérence et l'invalidation.
        *   Cette tâche est architecturale ; l'implémentation principale sera dans le Cycle 4.
    *   **Output Attendu**: Document `docs/cache-strategy.md` décrivant l'architecture, les technologies et les principes de la stratégie de cache.
    *   **Critères de Validation**: Le document est clair, cohérent et prend en compte les besoins du plugin.

#### Jour 2: Système d'Administration - Logs Immutables

**Objectif de la Journée**: Implémenter un système de journalisation sécurisé et non modifiable pour les actions administratives.

1.  **Tâche 2.1: Développement du Système de Journalisation des Modifications Administratives**
    *   **Fichiers/Modules Clés**: Nouveau `inc/admin/change-logger.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Créer un système qui enregistre chaque action administrative significative (création, modification, suppression d'excursions, de paramètres, etc.).
        *   Les logs doivent être horodatés, inclure l'utilisateur, l'IP, et une description détaillée du changement.
        *   Assurer un stockage sécurisé visant l'immutabilité (ex: table dédiée avec des triggers ou checksums, si possible dans le contexte WordPress, sinon logging append-only très strict).
    *   **Critères de Validation**: Les actions clés sont loguées avec les détails corrects. Tests de non-modification des logs.

2.  **Tâche 2.2: Création de l'Interface de Consultation des Logs Administrateur**
    *   **Fichiers/Modules Clés**: Nouveau `inc/admin/log-viewer.php`, page d'admin dédiée.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Développer une interface dans l'administration WordPress pour visualiser les logs enregistrés.
        *   Permettre le filtrage (par date, utilisateur, type d'action) et la recherche.
        *   Offrir une option d'export sécurisé (ex: CSV).
    *   **Critères de Validation**: L'interface affiche les logs, les filtres fonctionnent, l'export est possible.

#### Jour 3: Système d'Administration - Verrouillage et Notifications

**Objectif de la Journée**: Mettre en place des mécanismes pour prévenir les conflits d'édition et notifier les administrateurs.

1.  **Tâche 3.1: Implémentation du Gestionnaire de Verrouillage Administratif**
    *   **Fichiers/Modules Clés**: Nouveau `inc/admin/admin-lock-manager.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Développer un système pour bloquer les modifications simultanées sur les objets critiques (ex: une excursion en cours d'édition).
        *   Le verrouillage doit être temporaire (configurable, ex: 2-5 minutes) et se libérer automatiquement ou manuellement.
        *   Afficher des messages clairs à l'utilisateur si un objet est verrouillé.
    *   **Critères de Validation**: Le verrouillage fonctionne, les messages sont affichés, les verrous se libèrent. Simulation de modifications concurrentes.

2.  **Tâche 3.2: Développement du Système de Notification pour Administrateurs**
    *   **Fichiers/Modules Clés**: Nouveau `inc/admin/admin-notification.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Mettre en place des notifications en temps réel (ou quasi-réel via admin notices) pour les administrateurs concernant les actions importantes (ex: une autre admin a modifié une excursion, un verrou a été posé/levé).
        *   Identifier clairement l'administrateur ayant initié l'action.
    *   **Critères de Validation**: Les notifications sont affichées aux bons utilisateurs dans les bons contextes.

#### Jour 4: Interface d'Administration Unifiée

**Objectif de la Journée**: Créer une interface d'administration intuitive et centralisée pour le plugin.

1.  **Tâche 4.1: Conception du Tableau de Bord Principal du Plugin**
    *   **Fichiers/Modules Clés**: Nouveau `templates/admin/dashboard.php`, page d'admin principale.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Créer la page principale du plugin dans l'admin WordPress.
        *   Afficher une vue d'ensemble des activités récentes, des indicateurs clés (ex: nouvelles réservations, état du système de paiement si applicable), et des accès rapides aux fonctionnalités du plugin.
    *   **Critères de Validation**: Le tableau de bord s'affiche correctement et fournit des informations pertinentes.

2.  **Tâche 4.2: Application des Styles et UX pour l'Interface Admin**
    *   **Fichiers/Modules Clés**: Nouveau `assets/css/admin-styles.css`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Développer des styles CSS spécifiques pour l'interface d'administration du plugin afin d'assurer une expérience utilisateur claire, responsive et simplifiée, surtout pour les non-techniciens.
        *   Inclure des indicateurs visuels clairs (ex: état de verrouillage).
    *   **Critères de Validation**: L'interface est esthétique, responsive et facile à utiliser. Tests sur différents navigateurs et tailles d'écran.

#### Jour 5: Gestion des Utilisateurs et Permissions

**Objectif de la Journée**: Mettre en place une gestion fine des rôles et permissions pour l'accès aux fonctionnalités du plugin.

1.  **Tâche 5.1: Définition et Implémentation des Rôles Utilisateurs Spécifiques au Plugin**
    *   **Fichiers/Modules Clés**: Hooks WordPress pour la gestion des rôles (`add_role`, `add_cap`).
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Définir des rôles spécifiques (ex: "Gestionnaire d'Excursions Life Travel", "Support Client Life Travel") en plus des rôles WordPress standards.
        *   Attribuer des capacités granulaires à ces rôles pour contrôler l'accès aux différentes sections et actions du plugin (visualisation des logs, gestion des excursions, etc.).
    *   **Critères de Validation**: Les rôles sont créés, les capacités sont correctement assignées. Tests d'accès avec des utilisateurs ayant différents rôles.

2.  **Tâche 5.2: Sécurisation des Endpoints et Actions Administratives**
    *   **Fichiers/Modules Clés**: Utilisation de `current_user_can()` et des nonces WordPress (`wp_nonce_field`, `check_admin_referer`).
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   S'assurer que toutes les actions administratives (AJAX, formulaires POST) sont protégées par des vérifications de capacités et des nonces.
        *   Intégrer ces vérifications systématiquement dans tous les gestionnaires d'actions.
    *   **Critères de Validation**: Les actions non autorisées sont bloquées. Tests de tentatives d'accès direct ou de soumission de formulaires sans les droits/nonces requis.

#### Jour 6: Validation du Cycle 1 & Préparation du Cycle 2

**Objectif de la Journée**: Tester l'ensemble des fonctionnalités du Cycle 1, documenter, et préparer le cycle suivant.

1.  **Tâche 6.1: Tests d'Intégration et Validation Complète du Cycle 1**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Exécuter des scénarios de test couvrant toutes les fonctionnalités développées : analyse de compatibilité (revue), framework de validation (tests unitaires), logging (vérification des logs générés), gestion des erreurs, stratégie de cache (revue du document), journalisation admin (création/consultation), verrouillage admin (simulation), notifications admin, interface d'admin (navigation, affichage), gestion des rôles et permissions (tests d'accès).
        *   L'IA peut aider à générer des checklists de tests basées sur les tâches accomplies.
    *   **Critères de Validation**: Tous les tests sont passants. Les bugs identifiés sont corrigés ou documentés pour une résolution rapide.

2.  **Tâche 6.2: Documentation et Nettoyage du Code**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Revoir et compléter la documentation du code (PHPDoc pour les nouvelles classes et fonctions).
        *   Mettre à jour les documents `architecture-analysis.md` et `cache-strategy.md` si nécessaire.
        *   S'assurer que le code respecte les standards WordPress et est propre.
    *   **Critères de Validation**: La documentation est à jour, le code est lisible et bien commenté.

3.  **Tâche 6.3: Préparation du Cycle 2 (Gestion des Excursions)**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Identifier les fichiers et modules qui seront impactés par le Cycle 2.
        *   Préparer les stubs de classes ou fonctions si nécessaire.
        *   Anticiper les dépendances avec les services du Cycle 1.
    *   **Critères de Validation**: Un plan de travail initial pour le Cycle 2 est esquissé.

### Cycle 2: Gestion Excursions & Fonctionnalités Core (Jours 7-12)

**Objectif du Cycle**: Développer le cœur fonctionnel du plugin : la gestion complète des excursions, des réservations, et des interactions utilisateur de base.

#### Jour 7: Modèle de Données Avancé pour les Excursions

**Objectif de la Journée**: Concevoir et implémenter une structure de données robuste et flexible pour les excursions.

1.  **Tâche 7.1: Extension du CPT `excursion_custom` et Métadonnées Avancées**
    *   **Fichiers/Modules Clés**: `life-travel-core/life-travel-core.php` (ou fichier dédié aux CPTs), `inc/excursions/excursion-model.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Analyser le CPT `excursion_custom` existant et ses métadonnées.
        *   Étendre le CPT avec tous les champs nécessaires identifiés dans le plan (tarifs variables, types de participants, options personnalisables, gestion des stocks/places, lieux de départ, images, vidéos, etc.). Utiliser des metaboxes personnalisées robustes si Advanced Custom Fields (ACF) n'est pas une option ou pour un contrôle plus fin.
        *   Prévoir la gestion multilingue des champs (intégration avec TranslatePress).
        *   Assurer une validation stricte des données saisies via le framework de validation (Tâche 1.2).
    *   **Critères de Validation**: Le CPT est étendu, toutes les métadonnées sont enregistrables et récupérables. La validation fonctionne.

2.  **Tâche 7.2: Logique de Gestion des Types de Participants et Tarification**
    *   **Fichiers/Modules Clés**: `inc/excursions/pricing-engine.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Développer la logique pour gérer différents types de participants (adultes, enfants, étudiants, etc.) avec des tarifs spécifiques.
        *   Implémenter la gestion des tarifs saisonniers, des promotions, et des prix de groupe.
        *   Permettre la configuration de ces options via l'interface d'administration de l'excursion.
    *   **Critères de Validation**: Les tarifs peuvent être configurés et calculés correctement en fonction des types de participants et des conditions.

#### Jour 8: CRUD pour la Gestion des Excursions

**Objectif de la Journée**: Mettre en place les interfaces d'administration pour créer, lire, mettre à jour et supprimer les excursions.

1.  **Tâche 8.1: Interface d'Administration pour la Création/Édition des Excursions**
    *   **Fichiers/Modules Clés**: Intégration avec l'éditeur de post WordPress pour le CPT `excursion_custom`, `templates/admin/excursion-edit-ui.php` (pour les metaboxes ou UI personnalisée).
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Concevoir une interface d'administration intuitive pour gérer toutes les informations des excursions (champs du CPT et métadonnées).
        *   Utiliser l'éditeur Gutenberg autant que possible pour une expérience moderne, sinon des metaboxes claires et bien organisées.
        *   Intégrer le verrouillage administratif (Tâche 3.1) et les notifications (Tâche 3.2).
    *   **Critères de Validation**: Les excursions peuvent être créées et modifiées facilement. L'interface est conviviale et les verrouillages/notifications fonctionnent.

2.  **Tâche 8.2: Interface d'Affichage Liste des Excursions (Admin)**
    *   **Fichiers/Modules Clés**: Personnalisation de l'écran de liste des posts pour `excursion_custom`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Personnaliser la table d'affichage des excursions dans l'admin WordPress pour inclure des colonnes pertinentes (ex: dates clés, statut, nombre de places restantes, prix de base).
        *   Ajouter des filtres et des actions groupées si nécessaire.
    *   **Critères de Validation**: La liste des excursions est informative et facile à gérer.

#### Jour 9: Interface Utilisateur (Frontend) - Affichage des Excursions

**Objectif de la Journée**: Développer les templates pour afficher les excursions sur le site public.

1.  **Tâche 9.1: Template pour la Page Archive des Excursions**
    *   **Fichiers/Modules Clés**: `templates/frontend/archive-excursion.php`, `templates/frontend/loop-excursion-item.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Créer un template pour afficher la liste de toutes les excursions disponibles (page d'archive).
        *   Inclure des options de filtrage (par destination, type, date, prix) et de tri.
        *   Chaque excursion dans la liste doit afficher un résumé (image, titre, prix, courte description).
        *   Assurer une conception responsive et optimisée pour la performance (lazy loading des images).
    *   **Critères de Validation**: La page d'archive s'affiche correctement, les filtres et le tri fonctionnent. Le design est responsive.

2.  **Tâche 9.2: Template pour la Page Détail d'une Excursion (Single)**
    *   **Fichiers/Modules Clés**: `templates/frontend/single-excursion.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Développer le template pour afficher toutes les informations d'une excursion spécifique.
        *   Inclure : galerie d'images/vidéos, description complète, détails (durée, lieu), carte interactive (intégration simple pour l'instant), options de tarification, formulaire de sélection de date et de participants.
        *   Bouton "Réserver" clair et visible.
    *   **Critères de Validation**: Toutes les informations de l'excursion s'affichent correctement. Le design est responsive.

#### Jour 10: Système de Réservation - Processus Initial

**Objectif de la Journée**: Implémenter la logique de base du processus de réservation.

1.  **Tâche 10.1: Logique de Sélection de Date et Participants (Frontend)**
    *   **Fichiers/Modules Clés**: `assets/js/excursion-booking-form.js`, intégration avec `single-excursion.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Développer l'interactivité du formulaire de réservation sur la page détail de l'excursion.
        *   Permettre la sélection de la date (calendrier avec disponibilités), du nombre et type de participants.
        *   Calculer dynamiquement le prix total en fonction des sélections.
        *   Validation en temps réel des sélections (disponibilité, nombre max de participants).
    *   **Critères de Validation**: Le formulaire est interactif, le prix se met à jour, la validation fonctionne.

2.  **Tâche 10.2: Ajout au Panier WooCommerce Personnalisé**
    *   **Fichiers/Modules Clés**: Hooks WooCommerce (`woocommerce_add_to_cart_handler`, etc.), `inc/checkout/cart-manager.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Intégrer le processus de réservation avec le panier WooCommerce.
        *   Lors de l'ajout au panier, stocker toutes les informations de réservation (excursion ID, date, participants, options, prix calculé) comme données d'item de panier personnalisées.
        *   Assurer la compatibilité avec `sync_abandoned_cart()` (Tâche Mémoire 3b7421b5).
    *   **Critères de Validation**: Les excursions peuvent être ajoutées au panier avec toutes les données correctes. Les données sont compatibles avec la synchronisation des paniers abandonnés.

#### Jour 11: Gestion des Paniers et Préparation du Checkout

**Objectif de la Journée**: Finaliser la gestion du panier et préparer l'intégration avec le processus de paiement.

1.  **Tâche 11.1: Personnalisation de l'Affichage du Panier**
    *   **Fichiers/Modules Clés**: Templates WooCommerce (`cart/cart.php`), `inc/checkout/cart-display.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Personnaliser l'affichage des excursions dans le panier pour montrer clairement tous les détails de la réservation (date, participants, options).
        *   Permettre la modification simple de la réservation depuis le panier (si jugé pertinent et simple à implémenter, sinon suppression et ré-ajout).
    *   **Critères de Validation**: Le panier affiche correctement les détails des excursions réservées.

2.  **Tâche 11.2: Transfert des Données de Réservation à la Commande**
    *   **Fichiers/Modules Clés**: Hooks WooCommerce (`woocommerce_checkout_create_order_line_item`), `inc/checkout/order-manager.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   S'assurer que toutes les données de réservation personnalisées de l'item de panier sont transférées à l'item de commande lors de la création de la commande.
        *   Ces données doivent être stockées comme métadonnées de l'item de commande.
    *   **Critères de Validation**: Les données de réservation sont correctement sauvegardées avec la commande.

#### Jour 12: Validation du Cycle 2 & Préparation du Cycle 3

**Objectif de la Journée**: Tester en profondeur les fonctionnalités de gestion des excursions et de réservation, documenter, et préparer le cycle suivant.

1.  **Tâche 12.1: Tests d'Intégration et Validation Complète du Cycle 2**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Scénarios de test complets : création/modification/suppression d'excursions (admin), affichage des listes et détails (frontend), sélection des options de réservation, ajout au panier, affichage du panier, création de commande (vérification des données sauvegardées).
        *   Tests de la logique de tarification avec différents cas.
        *   Tester l'intégration avec TranslatePress pour les champs traductibles.
    *   **Critères de Validation**: Toutes les fonctionnalités de gestion des excursions et de réservation de base fonctionnent comme prévu.

2.  **Tâche 12.2: Documentation et Nettoyage du Code**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Documenter les nouveaux CPT, métadonnées, templates, et logiques métier.
        *   Mettre à jour `architecture-analysis.md` avec les détails du modèle de données des excursions.
    *   **Critères de Validation**: La documentation est complète et le code est propre.

3.  **Tâche 12.3: Préparation du Cycle 3 (Intégration WhatsApp & Notifications)**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Rechercher et évaluer les API ou services pour l'intégration WhatsApp (Twilio API for WhatsApp, etc.).
        *   Identifier les points d'ancrage pour les notifications (nouvelle réservation, modification de statut, etc.).
    *   **Critères de Validation**: Une évaluation préliminaire des solutions d'intégration WhatsApp est disponible.

### Cycle 3: Intégration WhatsApp & Notifications (Jours 13-18)

**Objectif du Cycle**: Mettre en place une communication multicanal robuste, notamment via WhatsApp, pour les notifications clients et administrateurs, et améliorer la gestion des commandes.

#### Jour 13: Architecture de Communication & Sélection API WhatsApp

**Objectif de la Journée**: Définir l'architecture du système de notification et choisir la solution pour l'intégration WhatsApp.

1.  **Tâche 13.1: Conception de l'Architecture du Système de Notifications Multicanal**
    *   **Fichiers/Modules Clés**: Nouveau `inc/core/notification-service.php`, document `docs/notification-architecture.md`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Concevoir un service de notification centralisé capable de gérer différents canaux (Email, SMS, WhatsApp, Admin Notices).
        *   Le service doit permettre de définir des templates de messages pour chaque canal et événement.
        *   Prévoir une file d'attente ou un système de gestion des envois pour la robustesse (surtout pour SMS/WhatsApp).
    *   **Output Attendu**: Document `docs/notification-architecture.md` et stubs pour `NotificationService`.
    *   **Critères de Validation**: L'architecture est claire, modulaire et prend en compte les différents canaux.

2.  **Tâche 13.2: Évaluation Finale et Sélection de l'API WhatsApp**
    *   **Fichiers/Modules Clés**: Documentation, recherche.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Basé sur la recherche du Cycle 2 (Tâche 12.3), finaliser le choix de l'API WhatsApp (ex: Twilio API for WhatsApp, Meta's WhatsApp Business API).
        *   Considérer les coûts, la facilité d'intégration, la fiabilité, et les fonctionnalités (templates de messages, gestion des réponses).
        *   Créer un compte de test si possible.
    *   **Output Attendu**: Choix de l'API documenté avec justification.
    *   **Critères de Validation**: Une solution API est choisie et les étapes pour l'intégration sont identifiées.

#### Jour 14: Implémentation du Service de Notifications (Email & SMS)

**Objectif de la Journée**: Mettre en place les canaux de notification Email et SMS.

1.  **Tâche 14.1: Intégration des Notifications par Email**
    *   **Fichiers/Modules Clés**: `inc/core/notification-service.php`, intégration avec `wp_mail()` ou les systèmes d'email de WooCommerce.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Implémenter l'envoi d'emails via le `NotificationService`.
        *   Créer des templates HTML pour les emails courants (confirmation de réservation, mise à jour de commande, etc.).
        *   Assurer la personnalisation des emails avec les données de la commande/réservation.
    *   **Critères de Validation**: Les emails sont envoyés et reçus correctement, le contenu est correct et personnalisé.

2.  **Tâche 14.2: Intégration des Notifications par SMS (via Twilio ou équivalent)**
    *   **Fichiers/Modules Clés**: `inc/core/notification-service.php`, nouveau `inc/gateways/sms-gateway.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Implémenter l'envoi de SMS via le `NotificationService` en utilisant une API SMS (ex: Twilio).
        *   Gérer la configuration des crédentiels API de manière sécurisée (WordPress Options API).
        *   Créer des templates pour les SMS.
    *   **Critères de Validation**: Les SMS sont envoyés et reçus, le contenu est correct. La configuration API est sécurisée.

#### Jour 15: Intégration Initiale de WhatsApp

**Objectif de la Journée**: Connecter le plugin à l'API WhatsApp et envoyer des messages de test.

1.  **Tâche 15.1: Configuration de Base de l'API WhatsApp**
    *   **Fichiers/Modules Clés**: Nouveau `inc/gateways/whatsapp-gateway.php`, interface d'admin pour la configuration.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Mettre en place la configuration des crédentiels de l'API WhatsApp choisie, de manière sécurisée.
        *   Développer les fonctions de base pour envoyer un message via l'API.
    *   **Critères de Validation**: La connexion à l'API WhatsApp est établie. Un message de test peut être envoyé manuellement.

2.  **Tâche 15.2: Envoi des Premières Notifications WhatsApp via `NotificationService`**
    *   **Fichiers/Modules Clés**: `inc/core/notification-service.php`, `inc/gateways/whatsapp-gateway.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Intégrer le `WhatsappGateway` dans le `NotificationService`.
        *   Tester l'envoi de notifications pour des événements simples (ex: nouvelle réservation) via WhatsApp, en utilisant des templates pré-approuvés si nécessaire par l'API.
    *   **Critères de Validation**: Les notifications WhatsApp sont envoyées pour des événements spécifiques.

#### Jour 16: Gestion Avancée des Commandes et Statuts

**Objectif de la Journée**: Améliorer la gestion des statuts de commande et des actions associées.

1.  **Tâche 16.1: Création de Statuts de Commande Personnalisés**
    *   **Fichiers/Modules Clés**: Hooks WooCommerce pour les statuts (`wc_register_order_status`), `inc/checkout/order-statuses.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Définir et enregistrer des statuts de commande WooCommerce personnalisés pertinents pour les excursions (ex: "En attente de confirmation de disponibilité", "Confirmée", "Annulée par client", "Terminée").
        *   Intégrer ces statuts dans le flux de gestion des commandes.
    *   **Critères de Validation**: Les nouveaux statuts sont disponibles et utilisables dans l'interface d'admin des commandes.

2.  **Tâche 16.2: Automatisation des Notifications Basées sur les Changements de Statut**
    *   **Fichiers/Modules Clés**: `inc/core/notification-service.php`, hooks sur les changements de statut (`woocommerce_order_status_changed`).
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Déclencher automatiquement des notifications (Email, SMS, WhatsApp) lors des changements de statuts de commande importants.
        *   Permettre la configuration des notifications à envoyer pour chaque statut.
    *   **Critères de Validation**: Les notifications sont envoyées automatiquement lorsque les statuts des commandes changent.

#### Jour 17: Interface d'Administration pour la Gestion des Communications

**Objectif de la Journée**: Fournir aux administrateurs des outils pour gérer et suivre les communications.

1.  **Tâche 17.1: Interface de Configuration des Notifications**
    *   **Fichiers/Modules Clés**: Page d'admin dédiée `templates/admin/notification-settings.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Créer une interface où les administrateurs peuvent activer/désactiver les notifications pour différents événements et canaux.
        *   Permettre la personnalisation (basique) des templates de messages.
    *   **Critères de Validation**: Les paramètres de notification peuvent être configurés via l'admin.

2.  **Tâche 17.2: Journal des Communications Envoyées**
    *   **Fichiers/Modules Clés**: `inc/admin/communication-log.php`, table de base de données dédiée ou logging avancé.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Mettre en place un journal des communications envoyées (Email, SMS, WhatsApp) avec leur statut (envoyé, échoué, etc.).
        *   Afficher ce journal dans l'administration avec des options de filtrage.
    *   **Critères de Validation**: Les communications sont journalisées et consultables.

#### Jour 18: Validation du Cycle 3 & Préparation du Cycle 4

**Objectif de la Journée**: Tester l'ensemble du système de notification et de gestion des commandes, documenter, et préparer le cycle offline.

1.  **Tâche 18.1: Tests d'Intégration et Validation Complète du Cycle 3**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Tester tous les canaux de notification (Email, SMS, WhatsApp) pour divers événements (nouvelle réservation, changement de statut, etc.).
        *   Vérifier la configuration des notifications et la journalisation.
        *   Tester la gestion des statuts de commande personnalisés.
    *   **Critères de Validation**: Le système de notification est fiable sur tous les canaux. La gestion des commandes est fluide.

2.  **Tâche 18.2: Documentation et Nettoyage du Code**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Documenter l'architecture des notifications, la configuration des API (de manière générale, sans exposer de clés), et les nouveaux statuts de commande.
        *   Mettre à jour `notification-architecture.md`.
    *   **Critères de Validation**: La documentation est à jour, le code est propre.

3.  **Tâche 18.3: Préparation du Cycle 4 (Offline, PWA & Optimisation Réseau)**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Revoir en détail les fichiers `pwa-bridge.php` et `offline-bridge.php` existants.
        *   Planifier l'intégration de la stratégie de cache définie au Cycle 1 (Tâche 1.4) avec ces modules.
        *   Identifier les assets critiques à mettre en cache pour le fonctionnement offline.
    *   **Critères de Validation**: Un plan d'action détaillé pour l'implémentation PWA et offline est prêt.

### Cycle 4: Offline, PWA & Optimisation Réseau (Jours 19-24)

**Objectif du Cycle**: Assurer une expérience utilisateur résiliente et performante, même en conditions de réseau dégradé ou offline, en s'appuyant sur les PWA et une stratégie de cache optimisée.

#### Jour 19: Intégration de la Stratégie de Cache Unifiée

**Objectif de la Journée**: Mettre en œuvre la stratégie de cache définie au Cycle 1.

1.  **Tâche 19.1: Implémentation du Caching des Assets Statiques via Service Worker**
    *   **Fichiers/Modules Clés**: `pwa-bridge.php` (ou nouveau `service-worker.js` géré par `pwa-bridge.php`), `docs/cache-strategy.md`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Configurer le Service Worker pour mettre en cache les assets statiques critiques (CSS, JS, polices, images principales du thème et du plugin).
        *   Utiliser une stratégie "Cache First" ou "Stale While Revalidate" selon la nature des assets.
        *   Se baser sur le document `docs/cache-strategy.md`.
    *   **Critères de Validation**: Les assets critiques sont mis en cache par le Service Worker. Le site se charge plus rapidement après la première visite.

2.  **Tâche 19.2: Implémentation du Caching des Données Dynamiques (API, Contenu)**
    *   **Fichiers/Modules Clés**: `pwa-bridge.php`, `offline-bridge.php`, Transients API, WordPress Object Cache.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Mettre en cache les réponses d'API fréquentes (ex: liste des excursions, détails d'une excursion) côté client (via Service Worker) et côté serveur (Transients/Object Cache).
        *   Définir des durées de validité de cache appropriées et des mécanismes d'invalidation.
    *   **Critères de Validation**: Les données dynamiques sont mises en cache, réduisant les appels serveur et améliorant la réactivité.

#### Jour 20: Fonctionnalités PWA de Base

**Objectif de la Journée**: Mettre en place les fonctionnalités essentielles d'une Progressive Web App.

1.  **Tâche 20.1: Configuration du Manifeste Web App et Service Worker**
    *   **Fichiers/Modules Clés**: `pwa-bridge.php` (génération du `manifest.json`), `service-worker.js`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Générer dynamiquement un `manifest.json` complet (nom de l'app, icônes, couleurs de thème, start_url, display mode).
        *   S'assurer que le Service Worker est correctement enregistré et gère les événements `install`, `activate`, `fetch`.
    *   **Critères de Validation**: Le site est installable comme une PWA ("Ajouter à l'écran d'accueil"). Le manifeste est valide.

2.  **Tâche 20.2: Implémentation d'une Page Offline Personnalisée**
    *   **Fichiers/Modules Clés**: `service-worker.js`, `templates/frontend/offline-page.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Créer une page HTML personnalisée à afficher lorsque l'utilisateur est offline et que la ressource demandée n'est pas en cache.
        *   La page doit informer l'utilisateur de son statut offline et, si possible, lister les contenus accessibles.
    *   **Critères de Validation**: La page offline personnalisée s'affiche en l'absence de connexion.

#### Jour 21: Expérience Utilisateur Offline Avancée

**Objectif de la Journée**: Permettre l'accès à certaines fonctionnalités clés en mode offline.

1.  **Tâche 21.1: Consultation Offline des Excursions Précédemment Vues**
    *   **Fichiers/Modules Clés**: `service-worker.js`, `offline-bridge.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Mettre en cache les données des pages d'excursion visitées par l'utilisateur pour consultation offline.
        *   Afficher un indicateur visuel si les données affichées proviennent du cache et peuvent ne pas être à jour.
    *   **Critères de Validation**: Les excursions déjà visitées sont consultables en mode offline.

2.  **Tâche 21.2: Initialisation de Réservation Offline (Mise en File d'Attente)**
    *   **Fichiers/Modules Clés**: `service-worker.js`, `assets/js/offline-booking.js`, `inc/checkout/offline-queue-manager.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Permettre aux utilisateurs de sélectionner une excursion et de remplir les informations de réservation même en étant offline.
        *   Sauvegarder la tentative de réservation localement (IndexedDB via le Service Worker).
        *   Synchroniser automatiquement la réservation lorsque la connexion est rétablie (Background Sync API).
        *   S'inspirer de `sync_abandoned_cart()` pour la logique de synchronisation et de validation.
    *   **Critères de Validation**: Une réservation peut être initiée offline et est synchronisée au retour de la connexion.

#### Jour 22: Optimisation des Performances Réseau

**Objectif de la Journée**: Mettre en œuvre des techniques pour optimiser le chargement et l'utilisation des données.

1.  **Tâche 22.1: Optimisation des Images (Lazy Loading, Formats Modernes)**
    *   **Fichiers/Modules Clés**: `life-travel-theme/functions.php` (si non géré globalement), `assets/js/lazy-load.js`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   S'assurer que le lazy loading est appliqué à toutes les images (excursions, contenu).
        *   Promouvoir l'utilisation de formats d'image optimisés (WebP) avec fallback. Vérifier si le thème actuel le fait déjà bien.
    *   **Critères de Validation**: Les images se chargent de manière différée. L'utilisation de WebP est effective.

2.  **Tâche 22.2: Minification et Concaténation des Assets (CSS/JS)**
    *   **Fichiers/Modules Clés**: Outils de build (si utilisés) ou plugins WordPress d'optimisation.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Vérifier que les fichiers CSS et JS du plugin (et du thème si possible) sont minifiés et, si pertinent, concaténés pour réduire le nombre de requêtes HTTP.
        *   S'assurer de la compatibilité avec les mécanismes de cache.
    *   **Critères de Validation**: Les assets sont minifiés. Le nombre de requêtes est optimisé.

#### Jour 23: Gestion de la Synchronisation des Données

**Objectif de la Journée**: Fiabiliser la synchronisation des données entre le client et le serveur.

1.  **Tâche 23.1: Interface Utilisateur pour l'État de Synchronisation**
    *   **Fichiers/Modules Clés**: `assets/js/sync-status-ui.js`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Afficher des indicateurs visuels discrets à l'utilisateur concernant l'état de la connexion et de la synchronisation des données (ex: "Mode offline", "Synchronisation en cours...", "Données à jour").
    *   **Critères de Validation**: L'utilisateur est informé de l'état de la synchronisation.

2.  **Tâche 23.2: Gestion des Conflits de Synchronisation (Stratégie Simple)**
    *   **Fichiers/Modules Clés**: `inc/checkout/offline-queue-manager.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Pour les réservations offline, si une excursion n'est plus disponible au moment de la synchronisation, notifier l'utilisateur et annuler la réservation offline.
        *   Pour commencer, adopter une stratégie "le serveur a toujours raison" pour simplifier.
    *   **Critères de Validation**: Les conflits simples (ex: non-disponibilité) sont gérés et l'utilisateur est notifié.

#### Jour 24: Validation du Cycle 4 & Préparation du Cycle 5

**Objectif de la Journée**: Tester intensivement les fonctionnalités PWA et offline, la performance, et préparer l'intégration des paiements.

1.  **Tâche 24.1: Tests d'Intégration et Validation Complète du Cycle 4**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Tests en conditions réseau variées (rapide, lent, offline).
        *   Validation de l'installabilité de la PWA, page offline, consultation et réservation offline, synchronisation.
        *   Mesure des performances de chargement (ex: Lighthouse, WebPageTest).
    *   **Critères de Validation**: Le site fonctionne bien en offline/réseau dégradé. Les performances sont améliorées.

2.  **Tâche 24.2: Documentation et Nettoyage du Code**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Documenter la configuration du Service Worker, la stratégie de gestion offline, et les optimisations de performance.
        *   Mettre à jour `cache-strategy.md` avec les détails d'implémentation.
    *   **Critères de Validation**: La documentation est à jour.

3.  **Tâche 24.3: Préparation du Cycle 5 (Paiements & Finalisation)**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Revoir en détail les plugins IwomiPay MTN et Orange Money (zip fournis par le client, commençant par "iwomipay-").
        *   Planifier la duplication et l'adaptation du plugin `iwomipay-momo` pour Orange Money, en portant une attention particulière à la sécurisation des clés API et des callbacks (inspiré de la Mémoire 825bbac8).
        *   Identifier les hooks WooCommerce pour l'intégration des nouvelles passerelles.
    *   **Critères de Validation**: Un plan d'action pour l'intégration des paiements est prêt.

### Cycle 5: Paiements, Finalisation & Tests Globaux (Jours 25-30)

**Objectif du Cycle**: Intégrer les systèmes de paiement, finaliser les fonctionnalités et effectuer des tests globaux pour assurer la qualité et la fiabilité du plugin.

#### Jour 25: Intégration Avancée des Passerelles de Paiement (IwomiPay)

**Objectif de la Journée**: Mettre en place et sécuriser les passerelles de paiement MTN et Orange Money via IwomiPay.

1.  **Tâche 25.1: Analyse et Adaptation du Plugin `iwomipay-momo-woocommerce` pour MTN Money**
    *   **Fichiers/Modules Clés**: Plugin `iwomipay-momo-woocommerce` (fourni par le client), nouveau `inc/gateways/class-wc-gateway-iwomipay-mtn.php`, `inc/checkout/payment-processing.php`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Analyser en profondeur le plugin `iwomipay-momo-woocommerce`.
        *   L'intégrer comme une nouvelle passerelle de paiement WooCommerce.
        *   Implémenter la gestion sécurisée des clés API (via WordPress Options API, non hardcodées).
        *   Sécuriser le callback de notification de paiement (IPN) avec vérification HMAC de la signature (conformément aux discussions et au Checkpoint).
        *   Transitionner le traitement de paiement vers un modèle asynchrone pour éviter de bloquer l'interface utilisateur (amélioration de `process_payment`).
    *   **Critères de Validation**: La passerelle MTN Money est intégrée, sécurisée (clés, callback HMAC), asynchrone, et les paiements tests fonctionnent.

2.  **Tâche 25.2: Duplication et Adaptation pour Orange Money (Basé sur IwomiPay)**
    *   **Fichiers/Modules Clés**: Nouveau `inc/gateways/class-wc-gateway-iwomipay-orange.php` (dupliqué et adapté depuis MTN), plugin IwomiPay Orange Money (si distinct, sinon adapter la base MTN).
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Dupliquer la structure de la passerelle MTN et l'adapter pour Orange Money, en utilisant les spécificités API d'Orange Money via IwomiPay.
        *   Appliquer les mêmes mesures de sécurité (clés API, callback HMAC) et le modèle asynchrone que pour MTN.
        *   S'assurer que les deux passerelles peuvent coexister sans conflit.
    *   **Critères de Validation**: La passerelle Orange Money est intégrée, sécurisée, asynchrone, et les paiements tests fonctionnent. Les deux coexistent.

#### Jour 26: Finalisation des Flux Utilisateur et Interface Admin des Paiements

**Objectif de la Journée**: Raffiner l'expérience utilisateur pour le paiement et fournir des outils d'administration.

1.  **Tâche 26.1: Optimisation du Flux de Paiement Utilisateur**
    *   **Fichiers/Modules Clés**: Templates de checkout WooCommerce, `assets/js/payment-handler.js`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Assurer une redirection claire vers les plateformes de paiement IwomiPay et un retour transparent vers le site.
        *   Gérer les erreurs de paiement de manière conviviale et informative pour l'utilisateur.
        *   Mettre à jour l'état de la commande en fonction des retours de paiement (confirmé, échoué, en attente).
    *   **Critères de Validation**: Le flux de paiement est fluide, les erreurs sont bien gérées, les statuts de commande sont mis à jour.

2.  **Tâche 26.2: Interface d'Administration pour la Configuration des Paiements**
    *   **Fichiers/Modules Clés**: Settings WooCommerce pour les passerelles de paiement.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   S'assurer que les options de configuration des passerelles (clés API, mode test/production, etc.) sont accessibles et claires dans l'admin WooCommerce.
        *   Ajouter des instructions ou des liens vers la documentation pour la configuration.
    *   **Critères de Validation**: Les passerelles sont facilement configurables par un administrateur.

#### Jour 27: Tests Globaux et Déploiement

**Objectif de la Journée**: Effectuer des tests globaux et préparer le déploiement.

1.  **Tâche 27.1: Tests Globaux**
    *   **Fichiers/Modules Clés**: `tests/*`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Effectuer des tests globaux pour s'assurer que toutes les fonctionnalités fonctionnent ensemble correctement.
        *   Tester les scénarios de bout en bout.
    *   **Critères de Validation**: Les tests globaux sont passants.

2.  **Tâche 27.2: Préparation du Déploiement**
    *   **Fichiers/Modules Clés**: `README.md`, `docs/*`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Réviser et compléter la documentation pour les utilisateurs et les développeurs.
        *   S'assurer que toutes les informations nécessaires sont disponibles.
    *   **Critères de Validation**: La documentation est complète et à jour.

#### Jour 28: Déploiement et Mise à Jour

**Objectif de la Journée**: Déployer le plugin et effectuer les mises à jour nécessaires.

1.  **Tâche 28.1: Déploiement du Plugin**
    *   **Fichiers/Modules Clés**: `README.md`, `docs/*`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Déployer le plugin sur le serveur de production.
        *   S'assurer que tout fonctionne correctement.
    *   **Critères de Validation**: Le plugin est déployé et fonctionne correctement.

2.  **Tâche 28.2: Mise à Jour des Dépendances**
    *   **Fichiers/Modules Clés**: `composer.json`, `package.json`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Mettre à jour les dépendances pour s'assurer que tout est à jour.
        *   S'assurer que les mises à jour ne cassent rien.
    *   **Critères de Validation**: Les dépendances sont à jour.

#### Jour 29: Tests de Sécurité et de Performance

**Objectif de la Journée**: Effectuer des tests de sécurité et de performance.

1.  **Tâche 29.1: Tests de Sécurité**
    *   **Fichiers/Modules Clés**: `tests/*`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Effectuer des tests de sécurité pour s'assurer que le plugin est sécurisé.
        *   Tester les vulnérabilités courantes.
    *   **Critères de Validation**: Les tests de sécurité sont passants.

2.  **Tâche 29.2: Tests de Performance**
    *   **Fichiers/Modules Clés**: `tests/*`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Effectuer des tests de performance pour s'assurer que le plugin est performant.
        *   Tester les temps de chargement et les ressources utilisées.
    *   **Critères de Validation**: Les tests de performance sont passants.

#### Jour 30: Finalisation et Livraison

**Objectif de la Journée**: Finaliser le plugin et le livrer.

1.  **Tâche 30.1: Finalisation du Plugin**
    *   **Fichiers/Modules Clés**: `README.md`, `docs/*`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Réviser et compléter la documentation pour les utilisateurs et les développeurs.
        *   S'assurer que tout est prêt pour la livraison.
    *   **Critères de Validation**: Le plugin est prêt pour la livraison.

2.  **Tâche 30.2: Livraison du Plugin**
    *   **Fichiers/Modules Clés**: `README.md`, `docs/*`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Livrer le plugin au client.
        *   S'assurer que tout fonctionne correctement.
    *   **Critères de Validation**: Le plugin est livré et fonctionne correctement.

#### Jour 27: Tests d'Intégration Complets & Scénarios Complexes

**Objectif de la Journée**: Valider l'ensemble du plugin avec des scénarios utilisateurs réels et complexes.

1.  **Tâche 27.1: Tests de Bout-en-Bout (Réservation Complète avec Paiement)**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Effectuer des tests complets : recherche d'excursion, sélection, ajout au panier, réservation, paiement (MTN & Orange), notifications (Email, SMS, WhatsApp), consultation de la commande.
        *   Tester avec différents types d'utilisateurs (connecté, non connecté).
    *   **Critères de Validation**: Tous les flux utilisateurs principaux fonctionnent sans erreur.

2.  **Tâche 27.2: Tests des Cas Limites et Gestion des Erreurs**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Tester les annulations de paiement, les échecs de notification, les paiements partiels (si applicable), la perte de connexion pendant le paiement.
        *   Vérifier la robustesse de `sync_abandoned_cart()` dans ces contextes.
        *   Valider le comportement en mode offline/PWA pendant le processus de commande.
    *   **Critères de Validation**: Le plugin gère les erreurs et cas limites de manière robuste.

#### Jour 28: Sécurité, Performance & Conformité

**Objectif de la Journée**: Auditer la sécurité, optimiser les performances finales et vérifier la conformité.

1.  **Tâche 28.1: Audit de Sécurité Final**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Revoir toutes les entrées utilisateur (XSS, SQLi), la gestion des droits, la sécurité des API externes (surtout paiements), la protection CSRF (nonces).
        *   Vérifier que les logs d'admin (Cycle 1) ne contiennent pas d'infos sensibles.
        *   S'assurer que la journalisation immuable est bien en place.
    *   **Critères de Validation**: Un audit de sécurité est effectué et les vulnérabilités potentielles sont corrigées.

2.  **Tâche 28.2: Optimisations de Performance Finales et Tests de Charge**
    *   **Fichiers/Modules Clés**: Outils de profiling (Query Monitor, Xdebug), Lighthouse.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Identifier les derniers goulots d'étranglement potentiels.
        *   Mesurer les temps de chargement des pages clés et le poids des assets.
        *   Effectuer des tests de charge simulés si possible (ex: avec k6, Loader.io) sur les fonctions critiques.
    *   **Critères de Validation**: Le plugin est performant et stable sous charge.

3.  **Tâche 28.3: Vérification de la Conformité aux Standards WordPress**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   S'assurer du respect des coding standards WordPress.
        *   Vérifier la bonne utilisation des APIs WordPress (Options, Transients, Hooks, etc.).
        *   Préparer le plugin pour une éventuelle soumission au répertoire WordPress.org (internationalisation, readme).
    *   **Critères de Validation**: Le plugin respecte les bonnes pratiques et standards WordPress.

### Jour 29: Documentation Finale, Préparation du Package & Stratégie de Rollback

**Objectif de la Journée**: Finaliser toute la documentation et préparer le package de livraison.

1.  **Tâche 29.1: Finalisation de la Documentation Utilisateur et Développeur**
    *   **Fichiers/Modules Clés**: `README.md`, `docs/` (tous les documents de conception, architecture, configuration).
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Compiler et raffiner toute la documentation : installation, configuration (paiements, notifications), utilisation, dépannage.
        *   Documenter l'architecture technique, les hooks personnalisés, et les points d'extension pour les développeurs.
    *   **Critères de Validation**: La documentation est complète, claire et à jour.

2.  **Tâche 29.2: Préparation du Package de Livraison du Plugin**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Créer une version packagée du plugin (zip) incluant tous les fichiers nécessaires, en excluant les fichiers de développement (.git, node_modules si non requis en prod).
        *   Inclure un fichier `changelog.md`.
    *   **Critères de Validation**: Un package de livraison propre et fonctionnel est créé.

3.  **Tâche 29.3: Finalisation de la Stratégie de Rollback et de Continuité (Revisiter Cycle 1 Tâche 4.1)**
    *   **Fichiers/Modules Clés**: `docs/rollback-strategy.md`.
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Revoir et valider la stratégie de rollback.
        *   Documenter les étapes spécifiques pour ce plugin en cas de problème majeur post-déploiement.
    *   **Critères de Validation**: La stratégie de rollback est claire, testable et documentée.

### Jour 30: Validation Finale Client (Simulation), Rétrospective & Clôture du Projet

**Objectif de la Journée**: Obtenir une validation finale (simulée), effectuer une rétrospective, et clôturer le cycle de développement.

1.  **Tâche 30.1: Session de Validation Finale (Simulée avec l'IA comme Client)**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Présenter le plugin finalisé, parcourir les fonctionnalités clés, simuler une démo client.
        *   Recueillir les "feedbacks" finaux basés sur les objectifs initiaux.
    *   **Critères de Validation**: La validation simulée est positive, le plugin répond aux objectifs.

2.  **Tâche 30.2: Rétrospective du Projet (avec l'IA)**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   Identifier ce qui a bien fonctionné, les défis rencontrés, les leçons apprises durant les 5 cycles.
        *   Noter les points d'amélioration pour de futurs projets similaires.
    *   **Critères de Validation**: Une rétrospective est menée et les points clés sont documentés.

3.  **Tâche 30.3: Nettoyage Final du Code, Archivage et Clôture**
    *   **Contexte & Objectifs Détaillés pour Claude**:
        *   S'assurer que tout le code est commité, les branches fusionnées.
        *   Archiver le projet et la documentation.
    *   **Critères de Validation**: Le projet est proprement clôturé et archivé.

---
