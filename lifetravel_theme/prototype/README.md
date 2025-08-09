# Life Travel - Prototype Website

## Identité visuelle et développement

Ce prototype de site Web pour Life Travel utilise une approche mobile-first et respecte une palette de couleurs extraite directement du logo officiel.

### Palette de couleurs

Les couleurs officielles de Life Travel sont :
- **Bleu principal** : `#0073B2` - Utilisé pour les titres, boutons et éléments principaux
- **Vert accent** : `#8BC84B` - Utilisé pour les accents, boutons d'appel à l'action et éléments de mise en évidence
- **Blanc** : `#FFFFFF` - Arrière-plan principal
- **Gris foncé** : `#333333` - Texte principal
- **Gris moyen** : `#666666` - Texte secondaire

Pour modifier la palette de couleurs, vous pouvez ajuster les variables CSS dans le fichier `css/style.css` :

```css
:root {
    --color-primary: #0073B2; /* Bleu Life Travel */
    --color-primary-rgb: 0, 115, 178;
    --color-primary-dark: #005E90;
    --color-secondary: #8BC84B; /* Vert Life Travel */
    --color-secondary-rgb: 139, 200, 75;
    --color-secondary-dark: #72A93C;
    /* ... autres variables ... */
}
```

### Fonctionnalité de recherche

La barre de recherche est optimisée pour une utilisation mobile et desktop. Elle s'affiche en overlay sur le contenu et offre une expérience utilisateur fluide.

#### Activer/désactiver la recherche

Pour activer ou désactiver la fonctionnalité de recherche, modifiez le fichier `js/main.js` :

```javascript
// Pour désactiver la recherche, commentez ou supprimez ce bloc
document.addEventListener('DOMContentLoaded', function() {
    const searchToggle = document.querySelector('.search-toggle');
    const searchForm = document.querySelector('.search-form');
    
    searchToggle.addEventListener('click', function() {
        searchForm.classList.toggle('active');
        if (searchForm.classList.contains('active')) {
            searchForm.querySelector('input').focus();
        }
    });
    
    // Fermer la recherche en cliquant en dehors
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.search-container')) {
            searchForm.classList.remove('active');
        }
    });
});
```

### Explorer les excursions

La section "Explorer les excursions" est conçue pour être mobile-first avec des filtres visuels pour destination, date, durée et prix. Les cartes d'excursion sont responsives et s'adaptent à tous les écrans.

### Bonnes pratiques pour plus de 50 excursions

Lorsque vous avez un grand nombre d'excursions (plus de 50), voici quelques bonnes pratiques à suivre :

1. **Utiliser la pagination** : Diviser les excursions en pages de 9-12 éléments par page.
2. **Implémenter la recherche avancée** : Permettre aux utilisateurs de filtrer par catégorie, lieu, prix, durée et disponibilité.
3. **Lazy loading des images** : Charger les images à la demande lorsqu'elles entrent dans le viewport.
4. **Optimisation des images** : Redimensionner et compresser toutes les images d'excursions pour réduire le temps de chargement.
5. **Mise en cache des résultats de filtrage** : Stocker les résultats des filtres courants en localStorage pour améliorer les performances.

### Problèmes connus et correctifs

1. **Problème d'affichage du champ de recherche** : 
   - Correction : La taille et l'affichage du champ de recherche ont été améliorés pour tous les appareils.

2. **Navigation mobile** :
   - Correction : Le menu hamburger et le dropdown mobile ont été optimisés pour une meilleure ergonomie.

### Structure du projet

```
life-travel/
├── css/
│   └── style.css
├── img/
│   ├── logo.svg
│   └── [images du site]
├── js/
│   └── main.js
├── index.html
├── excursions.html
├── sur-mesure.html
├── calendrier.html
├── a-propos.html
├── contact.html
└── [autres pages du site]
```

### Déploiement

Ce prototype est conçu pour être déployé facilement sur n'importe quel serveur web statique, y compris Netlify, GitHub Pages, ou un hébergement partagé.

## Licence

Tous droits réservés © Life Travel 2025
