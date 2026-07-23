# PFEP – Catálogo para Plataforma – Componentes

A PHP + MySQL web application for managing component data in a PFEP (Plan For Every Part) catalog.

## Features

| Field | Description |
|---|---|
| **Número de Parte** | Unique part number |
| **Foto del Producto** | Photo of the component/material |
| **Foto del Empaque** | Photo of the partial (bags) or full (sealed) packaging |
| **Estándar Pack** | Standard pack quantity (box or individual bag) |
| **Niveles por Pallet** | Pallet levels (3, 4, 5, etc.) |
| **Cajas por Nivel** | Boxes per level (6, 7, 10, etc.) |
| **Dimensiones** | Width / Depth / Height (inches) |
| **Peso** | Weight (lbs) |
| **Clasificación** | Size class: Chico / Mediano / Grande |

---

## Requirements

- PHP 8.0 or later (with `pdo_mysql` and `fileinfo` extensions)
- MySQL 5.7+ or MariaDB 10.3+
- Apache / Nginx (or PHP built-in server for local testing)

---

## Setup

### 1. Clone the repository

```bash
git clone https://github.com/Marg8/PFEP.git
cd PFEP
```

### 2. Create the database

```bash
mysql -u root -p < db/schema.sql
```

### 3. Configure the database connection

Open `config/db.php` and update the credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pfep');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
```

### 4. Make the `uploads/` folder writable

```bash
chmod 775 uploads/
```

### 5. Run locally (optional)

```bash
php -S localhost:8080
```

Then open http://localhost:8080 in your browser.

---

## Project Structure

```
PFEP/
├── config/
│   └── db.php          # DB connection (PDO)
├── css/
│   └── style.css       # Styles
├── db/
│   └── schema.sql      # MySQL schema
├── js/
│   └── app.js          # Photo preview & lightbox
├── uploads/            # Uploaded photos (writable)
├── index.php           # Catalog view
├── form.php            # Add / Edit form
├── process.php         # Form handler (insert / update)
└── delete.php          # Delete handler
```

---

## Security Notes

- All user inputs are validated and escaped with `htmlspecialchars`.
- All DB queries use prepared statements (PDO).
- Uploaded files are validated by MIME type (not just extension) and limited to 10 MB.
- Uploaded filenames are replaced with random `uniqid` names.