# Plan de réorganisation du projet Life Travel

## Analyse de situation actuelle

Le projet Life Travel est actuellement divisé en deux parties principales :
1. **Site WordPress principal** - situé dans `c:\Users\aleko\CascadeProjects\life-travel`
2. **Plugin d'excursions** - situé dans `c:\Users\aleko\Documents\Life-travel.org`

Cette séparation a créé plusieurs problèmes de cohérence, de maintenance et d'expérience utilisateur, particulièrement pour les administrateurs non-techniques.

## Points d'amélioration identifiés

1. **Séparation des responsabilités (CPT excursions)**
   - Duplication des types de contenu entre plugin core et plugin excursion
   - Confusion dans la gestion des excursions

2. **Optimisation de l'administration pour non-techniciens**
   - Interface dispersée et trop technique
   - Manque de cohérence dans les menus d'administration

3. **Gestion des médias et éléments visuels**
   - Fonctionnalités visuelles partagées entre plugin et thème
   - Confusion pour les mises à jour des assets

4. **Optimisations de connexion et performances**
   - Fonctionnalités d'optimisation réseau situées dans le mauvais composant
   - Manque de visibilité sur les performances réseau

5. **Passerelles de paiement et sécurité**
   - Intégration trop étroite avec d'autres fonctionnalités
   - Manque d'interface simplifiée pour configuration

6. **Gestion des traductions et multilingue**
   - Support multilingue dispersé
   - Interface de traduction non adaptée aux non-techniciens

7. **Configuration et documentation**
   - Documentation riche mais peu accessible
   - Manque d'aide contextuelle dans l'interface

8. **Intégration des blocs Gutenberg**
   - Mauvaise répartition des blocs entre plugins
   - Confusion entre blocs génériques et spécifiques

9. **Sécurité et récupération des paniers abandonnés**
   - Bonne implémentation technique mais isolée
   - Manque de visibilité pour les administrateurs

10. **Performances mobiles et adaptabilité**
    - Code d'adaptation mobile dispersé
    - Absence d'outil de prévisualisation pour administrateurs

## Plan d'implémentation

### Phase 1 - Consolidation et documentation
- Inventaire complet des fonctionnalités et dépendances
- Cartographie des points d'intégration
- Documentation des APIs

### Phase 2 - Restructuration du backend
- Création d'un tableau de bord unifié
- Simplification des interfaces d'administration
- Regroupement logique des options

### Phase 3 - Réorganisation du code
- Déplacement des fonctionnalités vers les bons composants
- Tests unitaires pour chaque migration
- Mise à jour des références

### Phase 4 - Amélioration de l'expérience
- Ajout d'assistants visuels et de tutoriels
- Simplification des workflows
- Tests utilisateurs avec des non-techniciens
