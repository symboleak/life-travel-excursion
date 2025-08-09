# Guide d'utilisation du système de fidélité Life Travel

## Version: 1.0 (Mai 2025)

Ce document explique comment configurer et utiliser le système de points de fidélité intégré au plugin Life Travel Excursion. Ce système permet aux clients de gagner des points en réservant des excursions et en partageant sur les réseaux sociaux, puis d'utiliser ces points pour obtenir des réductions sur leurs prochaines réservations.

## Table des matières

1. [Configuration globale](#configuration-globale)
2. [Configuration par excursion](#configuration-par-excursion)
3. [Configuration des partages sociaux](#configuration-des-partages-sociaux)
4. [Gestion du tableau de bord client](#gestion-du-tableau-de-bord-client)
5. [Utilisation des points](#utilisation-des-points)
6. [Statistiques et rapports](#statistiques-et-rapports)
7. [Dépannage](#dépannage)

## Configuration globale

Le système de fidélité dispose de paramètres globaux qui s'appliquent à toutes les excursions, sauf si des paramètres spécifiques sont définis au niveau de l'excursion.

### Accès aux paramètres globaux

1. Dans l'administration WordPress, accédez à **Produits > Fidélité**
2. La page de configuration du système de fidélité s'affiche avec trois sections principales :
   - Paramètres du système de points
   - Points pour partages sociaux
   - Paramètres des notifications

### Paramètres principaux

- **Nombre de points pour 1€** : Définit combien de points équivalent à 1€ de réduction (par défaut : 100)
- **Plafond global de points** : Limite le nombre maximum de points qu'un client peut gagner en une seule fois (par défaut : 1000)
- **Réduction maximale (%)** : Pourcentage maximum du total qu'un client peut déduire avec ses points (par défaut : 25%)

### Sauvegarde des paramètres

Cliquez sur le bouton **Enregistrer les paramètres** en bas de la page pour appliquer vos modifications.

## Configuration par excursion

Chaque excursion peut avoir sa propre configuration de points de fidélité, ce qui vous permet d'attribuer plus de points pour certaines excursions que vous souhaitez promouvoir.

### Modification d'une excursion

1. Accédez à **Produits > Tous les produits**
2. Éditez l'excursion souhaitée
3. Faites défiler jusqu'à la section **6. Système de points de fidélité**

### Options disponibles

- **Type d'attribution des points** : Deux modes sont disponibles :
  - **Fixe** : Un nombre précis de points par réservation
  - **Pourcentage** : Points calculés en pourcentage du montant dépensé
- **Valeur des points** : Nombre de points fixes ou pourcentage selon le type sélectionné
- **Plafond de points (optionnel)** : Limite spécifique à cette excursion, qui remplace le plafond global

### Exemples de configuration

- Pour attribuer 50 points fixes par réservation d'excursion :
  - Type d'attribution : **Fixe**
  - Valeur des points : **50**
  
- Pour attribuer 5% du montant en points :
  - Type d'attribution : **Pourcentage**
  - Valeur des points : **5**
  - (Pour une excursion à 200€, le client gagnera 10 points)

## Configuration des partages sociaux

Le système permet également aux clients de gagner des points en partageant du contenu sur les réseaux sociaux.

### Paramétrage des points par réseau

Dans **Produits > Fidélité**, sous la section **Points pour partages sociaux**, configurez :

- **Points pour partage Facebook** : Nombre de points attribués (par défaut : 10)
- **Points pour partage Twitter** : Nombre de points attribués (par défaut : 10)
- **Points pour partage WhatsApp** : Nombre de points attribués (par défaut : 5)
- **Points pour partage Instagram** : Nombre de points attribués (par défaut : 15)

### Validation des partages

Le système vérifie automatiquement que le partage a bien été effectué avant d'attribuer les points. Une vérification anti-fraude empêche les clients de gagner des points en partageant plusieurs fois le même contenu.

## Gestion du tableau de bord client

Le système de fidélité ajoute un nouvel onglet "Mes points de fidélité" dans l'espace client.

### Contenu du tableau de bord

- **Solde actuel** : Affiche le nombre total de points du client et leur valeur en euros
- **Comment gagner plus de points** : Explique les différentes façons de gagner des points
- **Historique des points** : Liste détaillée des points gagnés avec leur provenance

### Personnalisation du tableau de bord

Le template se trouve dans `templates/myaccount/loyalty-dashboard.php` et peut être surchargé dans votre thème pour personnaliser son apparence.

## Utilisation des points

Les clients peuvent utiliser leurs points lors du passage de commande pour obtenir des réductions.

### Processus d'utilisation

1. Pendant le checkout, un formulaire "Utiliser mes points de fidélité" s'affiche
2. Le client peut choisir combien de points utiliser (dans la limite disponible)
3. En cliquant sur "Appliquer les points", une réduction est immédiatement appliquée au total
4. Les points sont déduits du solde client uniquement après validation de la commande

### Limitations

- La réduction maximale est configurée dans les paramètres globaux (par défaut : 25%)
- Le système convertit automatiquement les points en euros selon le taux configuré
- Les points ne peuvent pas être combinés avec certains codes promotionnels (configurable)

## Statistiques et rapports

Le système offre des statistiques détaillées sur l'utilisation des points de fidélité.

### Tableau de bord administrateur

Dans **Produits > Fidélité**, en bas de page, vous trouverez :

- **Utilisateurs avec points** : Nombre total de clients ayant des points
- **Total des points attribués** : Somme de tous les points en circulation
- **Top 5 des utilisateurs** : Clients ayant le plus de points
- **Distribution des points par excursion** : Répartition des points attribués par produit

### Exportation des données

Un bouton permet d'exporter les données au format CSV pour une analyse plus approfondie dans d'autres outils.

## Dépannage

### Problèmes courants

- **Les points ne s'accumulent pas** : Vérifiez que le système est correctement activé dans l'initialisation du plugin
- **Certaines excursions n'attribuent pas de points** : Vérifiez la configuration spécifique à ces excursions
- **Les notifications ne s'affichent pas** : Vérifiez les paramètres de notification dans la configuration globale

### Journalisation

Le système enregistre toutes les opérations de points dans les journaux WordPress. Pour activer la journalisation détaillée :

1. Ajoutez `define('WP_DEBUG', true);` dans votre fichier wp-config.php
2. Les logs seront disponibles dans wp-content/debug.log

### Support

En cas de problème persistant, contactez l'équipe de support Life Travel en précisant :
- La version du plugin
- Les paramètres de configuration 
- Le problème exact rencontré avec les étapes pour le reproduire
