# E-commerce Backend API

API REST pour application e-commerce développée avec Symfony 7.3.

## 🛠️ Stack technique

- **Framework**: Symfony 7.3
- **PHP**: 8.2+
- **Base de données**: PostgreSQL/MySQL
- **ORM**: Doctrine
- **Authentification**: JWT
- **Documentation**: OpenAPI/Swagger

## 🚀 Démarrage rapide
```bash
# Installation des dépendances
composer install

# Configuration base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Fixtures (données de test)
php bin/console doctrine:fixtures:load

# Serveur de développement
symfony server:start
```

## 📁 Structure du projet
```
src/
├── Controller/   # Contrôleurs API
├── Entity/       # Entités Doctrine
├── Repository/   # Repositories
├── Service/      # Services métier
├── DTO/          # Data Transfer Objects
└── Security/     # Authentification
```

## 🔧 Configuration

- Copier .env vers .env.local
- Configurer la base de données dans .env.local
- Installer les dépendances avec composer install
- Créer la base et exécuter les migrations
