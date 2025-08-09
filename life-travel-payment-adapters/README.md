# Life Travel Payment Adapters

Plugin WordPress pour la gestion des adaptateurs de paiement utilisés par Life Travel.

## Description

Ce plugin fournit des adaptateurs pour les passerelles de paiement IwomiPay (MTN MoMo et Orange Money) utilisées par Life Travel. Il agit comme une couche intermédiaire entre les plugins de paiement originaux et le site WordPress.

## Avantages

* Séparation des préoccupations
* Facilitation des mises à jour des plugins tiers
* Centralisation des personnalisations spécifiques à Life Travel
* Journalisation et suivi avancé des transactions

## Architecture

Le plugin utilise une architecture basée sur l'adaptation des classes originales :

```
life-travel-payment-adapters/
├── life-travel-payment-adapters.php   # Fichier principal
├── includes/
│   ├── class-payment-manager.php      # Gestionnaire central
│   ├── class-momo-adapter.php         # Adaptateur MTN MoMo
│   └── class-om-adapter.php           # Adaptateur Orange Money
└── assets/                            # Ressources CSS/JS/images
```

## Installation

1. Installez et activez les plugins IwomiPay pour MTN MoMo et Orange Money
2. Installez et activez ce plugin
3. Configurez les passerelles de paiement via WooCommerce > Réglages > Paiements

## Dépendances

* WordPress 5.6+
* WooCommerce 4.0+
* Plugins IwomiPay pour MTN MoMo et Orange Money

## Développement

Pour contribuer au développement :

1. Clonez ce dépôt
2. Effectuez vos modifications
3. Soumettez une pull request

## Stratégie d'adaptation

Ce plugin implémente le pattern Adaptateur (Adapter Pattern) pour permettre à Life Travel d'utiliser les plugins IwomiPay tout en :

1. Ajoutant des fonctionnalités propres à Life Travel (logging, hooks, UI personnalisée)
2. Facilitant les mises à jour des plugins IwomiPay sans impacter le site
3. Centralisant la gestion des paiements dans une seule interface
