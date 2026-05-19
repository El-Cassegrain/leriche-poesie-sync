# Leriche Poesie Sync

Plugin WordPress + scripts pour synchroniser automatiquement **poesie.etienneleriche.com** vers **leriche-poesie.com** via FTP.

Chaque fois qu'un article ou une page est publié, modifié ou supprimé dans l'admin WordPress, le plugin :
1. Génère les fichiers HTML statiques du site (via le thème actif)
2. Les uploade automatiquement vers `leriche-poesie.com` par FTP/FTPS

---

## Structure du dépôt

```
wp-plugin/leriche-sync/
├── leriche-sync.php               # Plugin principal (hooks WP)
├── includes/
│   ├── class-ftp-uploader.php     # Connexion & upload FTP
│   └── class-static-generator.php # Génération HTML statique
└── admin/
    └── settings-page.php          # Interface admin (config FTP + logs)
```

---

## Installation

1. Copier le dossier `wp-plugin/leriche-sync/` dans `wp-content/plugins/` de votre WordPress (`poesie.etienneleriche.com`)
2. Activer le plugin dans **Extensions > Extensions installées**
3. Aller dans **Leriche Sync** (menu gauche de l'admin) et renseigner :
   - Hôte FTP (ex : `ftp.leriche-poesie.com`)
   - Port FTP (21 par défaut)
   - Identifiant et mot de passe FTP
   - Dossier distant (ex : `/public_html`)
   - Option FTPS si votre hébergeur le supporte
4. Cliquer sur **Enregistrer les paramètres FTP**

---

## Fonctionnement automatique

Le plugin se déclenche automatiquement sur :

| Événement WordPress | Hook utilisé |
|---|---|
| Sauvegarde / mise à jour d'un post/page publié | `save_post` |
| Passage d'un post en statut "publié" | `transition_post_status` |
| Suppression d'un post | `before_delete_post` |

---

## Synchronisation manuelle

- Via le **bouton dans la barre d'admin** (icône 🔄 en haut de page)
- Via le bouton **"Lancer une synchronisation manuelle"** dans la page Leriche Sync

---

## Journal des synchronisations

Les 100 dernières opérations sont visibles dans la page admin **Leriche Sync** avec la date, le niveau (info/warning/error) et le message.

---

## Prérequis serveur

- PHP >= 7.4
- Extension PHP `ftp` activée (pour FTP)
- Extension PHP `ftp_ssl_connect` activée (pour FTPS, optionnel)
- WordPress >= 5.0

---

## Personnalisation

### Ajouter d'autres types de contenu

Dans `class-static-generator.php`, modifier le paramètre `post_type` de la WP_Query :

```php
'post_type' => [ 'post', 'page', 'poeme', 'recueil' ],
```

### Réécriture d'URLs

La méthode `rewrite_urls()` remplace toutes les URLs absolues du site source par des chemins relatifs. Vous pouvez l'enrichir pour gérer d'autres cas (CDN, sous-domaines, etc.).

---

## Sécurité

- Le mot de passe FTP est stocké dans la table `wp_options` de WordPress (chiffré si vous utilisez un plugin de chiffrement des options).
- La synchronisation manuelle utilise un nonce WordPress pour prévenir les CSRF.

---

## License

GPL-2.0+
