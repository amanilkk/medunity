# 🔐 Security Policy

## 📌 Introduction

La sécurité des données est une priorité pour ce projet, notamment en raison de la sensibilité des informations médicales traitées.

---

## 🛡️ Mesures de sécurité

### 🔑 Authentification

* Accès sécurisé via **email et mot de passe**
* Implémentation de l’authentification multi-facteurs (MFA / 2FA)
* Vérification supplémentaire lors des connexions sensibles
* Validation du format de l’email lors de la saisie
* Unicité de l’email pour chaque utilisateur

---

### 🔒 Politique des mots de passe

* Minimum 8 caractères
* Doit contenir :

  * Lettres majuscules
  * Lettres minuscules
  * Chiffres
  * Caractères spéciaux
* Vérification de la complexité lors de la création ou modification

---

### 🔐 Hachage des mots de passe

* Les mots de passe ne sont **jamais stockés en clair**
* Utilisation d’un algorithme de hachage sécurisé (ex : bcrypt)
* Vérification des mots de passe via comparaison de hash (`password_verify`)

---

### 🚫 Protection contre les attaques (Brute Force)

* Blocage temporaire du compte après plusieurs tentatives de connexion échouées
* Suivi et journalisation des tentatives de connexion
* Réinitialisation possible par l’administrateur

---

### 🧠 Sécurisation de la base de données

* Utilisation de requêtes préparées (Prepared Statements) pour éviter les **SQL Injection**
* Validation et filtrage des entrées utilisateur
* Gestion stricte des accès à la base de données
* Sauvegardes régulières et sécurisées

---

### 🌐 Protection des applications web

* Protection contre les attaques XSS (Cross-Site Scripting)
* Protection contre les attaques CSRF
* Validation côté serveur et côté client (JavaScript)

---

### 👥 Contrôle d’accès

* Gestion des rôles (Admin, Médecin, Secrétaire, etc.)
* Accès limité selon les permissions (RBAC)

---

### 📊 Journalisation (Audit)

* Enregistrement des actions utilisateurs
* Suivi des connexions (succès / échec)
* Traçabilité complète des opérations sensibles

---

### 💾 Protection des données

* Stockage sécurisé des informations médicales
* Sauvegardes régulières de la base de données
* Confidentialité et intégrité des données garanties

---

## ⚠️ Signalement de vulnérabilités

Si vous découvrez une faille de sécurité, merci de :

1. Ne pas l’exploiter
2. La signaler immédiatement aux développeurs
3. Fournir une description détaillée du problème

---

## 🚫 Bonnes pratiques

* Ne pas partager les identifiants
* Utiliser des mots de passe forts
* Mettre à jour régulièrement le système
* Limiter l’accès aux utilisateurs autorisés

---

## 🔄 Mises à jour

Les correctifs de sécurité seront appliqués dès qu’une vulnérabilité est identifiée.

---

## 📩 Contact


yalaoui.djamila@univ-constantine2.dz

lakehal.amani-ala@univ-constantine2.dz

sahour.lina-djihane@univ-constantine2.dz

zaatout.douaa@univ-constantine2.dz
