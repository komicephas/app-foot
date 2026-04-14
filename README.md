# Gestion Foot

Application web de gestion d'un club de football en PHP natif + MySQL.

## Acces local

- URL : `http://localhost/gestion_foot/`
- Login : `admin`
- Mot de passe : `admin123`

## Prerequis

- XAMPP avec Apache, PHP 8 et MySQL/MariaDB
- Extensions PHP : `pdo_mysql`, `gd`, `intl`, `mbstring`, `xml`, `zip`

## Installation

1. Placer le projet dans `htdocs/gestion_foot`
2. Installer les dependances Composer
3. Ouvrir `http://localhost/gestion_foot/install.php`
4. Se connecter avec le compte admin par defaut

## Fonctionnalites

- Gestion des membres et sympathisants
- Sessions de cotisation et paiements
- Matchs et participations
- Tableau financier et solde en temps reel
- Exports Excel avec PhpSpreadsheet
- Carte membre PDF avec mPDF
