# 📚 GUIDE COMPLET : UPLOAD DE PLUGIN VOLUMINEUX SUR WORDPRESS LOCAL (WAMPSERVER)

## 🎯 Objectif
Ce guide vous permettra d'uploader le plugin Life Travel Excursion (>100MB) via le panneau d'administration WordPress sur votre serveur local Wampserver.

## 📋 Prérequis
- Wampserver installé et fonctionnel
- WordPress installé localement
- Accès administrateur à WordPress
- Accès aux fichiers de configuration Wampserver

---

## 🔧 ÉTAPE 1 : Configuration PHP

### 1.1 Localiser php.ini
1. **Méthode 1** : Clic droit sur l'icône Wampserver → PHP → php.ini
2. **Méthode 2** : Naviguer vers `C:\wamp64\bin\php\php[version]\php.ini`

### 1.2 Modifier les paramètres
Ouvrez php.ini dans un éditeur de texte et modifiez ces valeurs :

```ini
; Taille maximale des fichiers uploadés (200MB pour votre plugin)
upload_max_filesize = 200M

; Taille maximale des données POST (doit être ≥ upload_max_filesize)
post_max_size = 250M

; Temps d'exécution maximal (5 minutes pour les gros uploads)
max_execution_time = 300

; Temps maximal d'input (pour l'upload)
max_input_time = 300

; Limite mémoire PHP
memory_limit = 256M
```

### 1.3 Redémarrer les services
Après modification, redémarrez Wampserver :
- Clic droit sur l'icône Wampserver → Redémarrer tous les services

---

## 🔧 ÉTAPE 2 : Configuration Apache (Optionnel mais recommandé)

### 2.1 Créer/modifier .htaccess
Dans le dossier racine de votre WordPress (`C:\wamp64\www\[votre-site]\`), créez ou modifiez `.htaccess` :

```apache
# Configuration pour uploads volumineux
php_value upload_max_filesize 200M
php_value post_max_size 250M
php_value max_execution_time 300
php_value max_input_time 300

# Protection supplémentaire
<IfModule mod_security.c>
    SecFilterEngine Off
    SecFilterScanPOST Off
</IfModule>
```

---

## 🔧 ÉTAPE 3 : Configuration WordPress

### 3.1 Augmenter la limite dans wp-config.php
Ajoutez ces lignes dans votre `wp-config.php` (avant `/* That's all, stop editing! */`) :

```php
// Augmenter la limite mémoire WordPress
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '256M');

// Augmenter le timeout
@ini_set('max_execution_time', 300);
```

### 3.2 Désactiver temporairement la limite de WordPress (si nécessaire)
Ajoutez dans `wp-config.php` :

```php
// Permettre les uploads non filtrés (pour admin seulement)
define('ALLOW_UNFILTERED_UPLOADS', true);
```

---

## 🚀 ÉTAPE 4 : Upload du Plugin

### 4.1 Préparer le fichier ZIP
1. Assurez-vous que votre plugin est dans un seul dossier `life-travel-excursion/`
2. Créez un fichier ZIP contenant ce dossier
3. Vérifiez la taille du ZIP (ex: 120MB)

### 4.2 Upload via WordPress Admin
1. Connectez-vous à WordPress : `http://localhost/[votre-site]/wp-admin`
2. Allez dans **Extensions → Ajouter**
3. Cliquez sur **Téléverser une extension**
4. Cliquez sur **Parcourir** et sélectionnez votre fichier ZIP
5. Cliquez sur **Installer maintenant**

### 4.3 En cas d'erreur
Si l'upload échoue malgré la configuration :

**Option A : Upload manuel**
1. Décompressez le plugin localement
2. Copiez le dossier `life-travel-excursion` dans `C:\wamp64\www\[votre-site]\wp-content\plugins\`
3. Activez le plugin depuis WordPress Admin

**Option B : Augmenter encore les limites**
```ini
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 600
```

---

## 🔍 ÉTAPE 5 : Vérification de la Configuration

### 5.1 Créer un fichier phpinfo.php
Créez `phpinfo.php` dans votre dossier WordPress :

```php
<?php
phpinfo();
?>
```

### 5.2 Vérifier les valeurs
1. Accédez à `http://localhost/[votre-site]/phpinfo.php`
2. Recherchez (Ctrl+F) :
   - `upload_max_filesize`
   - `post_max_size`
   - `max_execution_time`
   - `memory_limit`
3. Vérifiez que les valeurs correspondent à votre configuration
4. **IMPORTANT** : Supprimez `phpinfo.php` après vérification

---

## 🐛 ÉTAPE 6 : Dépannage

### Problème : "Le fichier envoyé dépasse la directive upload_max_filesize"
**Solution** : Vérifiez que php.ini a été modifié et Wampserver redémarré

### Problème : "Erreur HTTP" lors de l'upload
**Solutions** :
1. Vérifiez les logs Apache : `C:\wamp64\logs\apache_error.log`
2. Désactivez temporairement mod_security dans httpd.conf
3. Augmentez `LimitRequestBody` dans httpd.conf

### Problème : Page blanche ou timeout
**Solutions** :
1. Augmentez `max_execution_time` à 600
2. Vérifiez les logs PHP : `C:\wamp64\logs\php_error.log`
3. Activez le mode debug WordPress dans wp-config.php :
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

---

## ✅ ÉTAPE 7 : Activation du Plugin

### 7.1 Avant l'activation
1. Vérifiez que WooCommerce est activé
2. Vérifiez les prérequis PHP (≥ 8.0)
3. Consultez les logs d'erreur WordPress

### 7.2 Activer le plugin
1. Allez dans **Extensions → Extensions installées**
2. Trouvez "Life Travel Excursion"
3. Cliquez sur **Activer**

### 7.3 En cas d'erreur d'activation
1. Consultez `wp-content/debug.log`
2. Vérifiez les erreurs PHP dans les logs Wampserver
3. Désactivez temporairement les autres plugins pour tester

---

## 📌 Notes Importantes

1. **Sécurité** : Ces configurations sont pour un environnement LOCAL uniquement. Ne jamais utiliser ces valeurs élevées en production.

2. **Performance** : Des valeurs élevées peuvent ralentir votre serveur local.

3. **Alternatives** : Pour les très gros plugins (>200MB), préférez :
   - Upload FTP direct
   - Git clone
   - Composer install

4. **Sauvegarde** : Toujours sauvegarder votre base de données et fichiers avant l'installation d'un nouveau plugin.

---

## 🎉 Succès !
Une fois le plugin activé avec succès, vous pouvez :
- Restaurer les valeurs par défaut dans php.ini (optionnel)
- Configurer le plugin depuis WordPress Admin
- Tester les fonctionnalités du plugin

---

*Document créé le 8 août 2025 pour l'installation du plugin Life Travel Excursion*
