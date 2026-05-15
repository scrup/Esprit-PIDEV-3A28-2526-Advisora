# Business Consulting Platform — Symfony Backend

**Bridging Investors & Business Owners | Strategy & Project Guidance**

[Features](#features) • [Tech Stack](#tech-stack) • [Installation](#installation) • [API Endpoints](#api-endpoints) • [Team](#team)

---

## Overview

This is the **Symfony backend** powering our Business Consulting Platform — a comprehensive ecosystem connecting investors with business owners, facilitating resource exchange, and delivering AI-driven project guidance and strategy solutions.

&gt; **Project developed under the guidance of [ESPRIT](https://esprit.tn/)**

---

## Features

### Core Functionality
- **Project Guidance** — End-to-end project structuring, planning, and scaling tools
- **Strategy Solutions** — Custom strategy generation based on market position and growth goals
- **Investor-Business Matching** — Smart pairing algorithm connecting the right investors with the right opportunities
- **Resource Exchange** — Platform for sharing tools, knowledge, and assets

### Smart Features
- **Event Recommendation Engine** — AI-powered event suggestions with integrated calendar sync
- **Profit Optimization** — Machine learning algorithms maximizing business outcomes
- **Accessibility First** — Full compatibility with assistive technologies

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Framework | Symfony 6.4|
| Language |PHP 8.2+ (backend), JavaScript (frontend behavior/AJAX), Twig templates (SSR views), HTML/CSS |
| Database | MySQL/MariaDB |
| ORM | Doctrine |
| Authentication |Symfony Security |
| API | RESTful JSON |
| AI/ML Integration | Python microservices (TensorFlow / Scikit-learn) |
| Frontend Communication | server-rendered Twig pages + client-side fetch/AJAX to Symfony JSON endpoints |

---

## Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL or MariaDB
- Symfony CLI (optional but recommended)
- Docker (optional, for containerized setup)

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/your-org/your-repo.git
cd your-repo/symfony-backend
```

### 2. Install dependencies
```bash
composer install
```

### 3. Configure environment
```bash
cp .env.example .env

# Edit .env with your database credentials and JWT secrets
```
### 4. Set up the database

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load  # Optional: load demo data
```

### 5. Generate JWT keys
```bash
php bin/console lexik:jwt:generate-keypair
```

### 6. Start the server
```bash
symfony server:start
# OR
php -S localhost:8000 -t public/
```

The API will be available at http://localhost:8000/api



## Project Structure

```text
symfony-backend/
├── bin/                  # Console commands
├── config/               # Configuration files
├── migrations/           # Database migrations
├── public/               # Web server entry point
├── src/
│   ├── Controller/       # API controllers
│   ├── Entity/           # Doctrine entities
│   ├── Repository/       # Data access layer
│   ├── Service/          # Business logic
│   ├── Security/         # Authentication & authorization
│   └── EventListener/    # Custom event listeners
├── templates/            # Twig templates (if applicable)
├── tests/                # PHPUnit tests
└── var/                  # Cache and logs
```




### Accessibility

This backend supports frontend accessibility requirements:
-Semantic JSON responses compatible with screen readers
-WCAG 2.1 AA compliant API design
-Text to speech 

### Testing
```bash
# Run unit tests
php bin/phpunit
```
### Team
Built with passion by:

| Name                | Module               |
| ------------------- | ------------------- |
| **Nidhal Zneiti**   | Project management |
| **Lynda Jlassi**    | User management          |
| **Karama Hmidi**    | Event maangement         |
| **Chedi Ben Slima** | Ressource management    |
| **Moez Kefi**       | Investment management   |
| **Azza Chouikh**    | Strategy management     |

## Project developed under the guidance of ESPRIT


### Related Projects

| Component    | Technology     | Repository |
| ------------ | -------------- | ---------- |
| Desktop App | JavaFX | [Link](https://github.com/lyynda1/ConsultingCenter.git) |


