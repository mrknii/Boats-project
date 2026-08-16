# Detailed setup guide

Step-by-step installation on XAMPP, written so it can be followed without prior
experience. If you just want the short version, see the Quick start in the main
`README.md`.

---

## 1. Install XAMPP

Download XAMPP with **PHP 8.0 or newer** from
<https://www.apachefriends.org> and install it with the default options. You
need the **Apache** and **MySQL** components; the others are optional.

---

## 2. Put the project in the web root

Copy the whole project folder into XAMPP's `htdocs` directory:

| Operating system | Destination |
|---|---|
| Windows | `C:\xampp\htdocs\farm-management-system` |
| macOS | `/Applications/XAMPP/htdocs/farm-management-system` |
| Linux | `/opt/lampp/htdocs/farm-management-system` |

You can name the folder whatever you like — the application detects its own base
URL, so nothing needs editing.

Check that the folder contains `index.php`, `install.php`, `config/`, `pages/`
and `database/`. A common mistake is copying in a nested folder, so the path
becomes `htdocs/farm-management-system/farm-management-system/`.

---

## 3. Start the servers

Open the **XAMPP Control Panel** and press **Start** next to **Apache** and
**MySQL**. Both should turn green.

**If Apache refuses to start**, another program is using port 80 — usually Skype
or IIS on Windows. Either close it, or click *Config → httpd.conf* in XAMPP and
change `Listen 80` to `Listen 8080`. If you do, every URL below gains `:8080`,
e.g. `http://localhost:8080/farm-management-system/`.

---

## 4. Create the database

### Option A — the installer (recommended)

Browse to:

```
http://localhost/farm-management-system/install.php
```

The page runs five environment checks, then offers an **Install now** button.
It creates the `farm_db` database, all 17 tables, and a full year of
demonstration data.

When it finishes, **delete `install.php`**. Leaving it in place would let anyone
rebuild — and therefore wipe — the database.

### Option B — phpMyAdmin

1. Go to <http://localhost/phpmyadmin>.
2. Click the **Import** tab (you do **not** need to create the database first —
   the SQL file creates it).
3. Choose `database/farm_db.sql` and press **Go**.
4. You should see `farm_db` appear with 17 tables.

---

## 5. Sign in

Open:

```
http://localhost/farm-management-system/
```

| Role | Username | Password |
|---|---|---|
| Administrator | `admin` | `password123` |
| Manager | `manager` | `password123` |
| Worker | `worker` | `password123` |

The sign-in screen has one-click buttons that fill each account in for you.

Sign in as each of the three in turn — the sidebar visibly changes, which is the
quickest way to show role-based access control to an examiner.

---

## 6. If your MySQL root has a password

XAMPP ships with an empty root password. If you have set one, open
`config/config.php` and edit:

```php
define('DB_PASS', 'your_password_here');
```

---

## 7. Before you present

1. Open `config/config.php` and set:

   ```php
   define('DEBUG_MODE', false);
   ```

   This hides PHP notices, so nothing unexpected can appear on screen mid-demo.

2. Confirm `install.php` has been deleted.

3. Change the demonstration passwords under **User Accounts → reset password**
   if the system will hold anything real.

4. Take a backup: in phpMyAdmin select `farm_db` → **Export** → **Go**. Keep the
   file somewhere separate from your laptop.

---

## Troubleshooting

| Message | Cause and fix |
|---|---|
| *Database connection failed* | MySQL is not running. Start it in XAMPP. |
| *Unknown database 'farm_db'* | The database was never created — run `install.php` or import the SQL file. |
| *Access denied for user 'root'@'localhost'* | Your MySQL root has a password. Set `DB_PASS` in `config/config.php`. |
| Page shows raw PHP code | You opened the file directly from disk. It must be served through Apache via a `http://localhost/...` URL. |
| Object not found / 404 | The folder is nested one level too deep inside `htdocs`, or the folder name in the URL does not match. |
| Styles missing, page looks like plain text | The `assets/` folder was not copied across. |
| Charts do not appear | Check the browser console (F12). All chart code is local, so this is normally a missing `assets/js/charts.js`. |
| Blank white page | A PHP fatal error with `DEBUG_MODE` off. Set it to `true` temporarily to read the message. |

---

## Verifying the installation

A quick checklist to confirm everything works:

- [ ] The dashboard shows non-zero figures and four charts render.
- [ ] The notification bell shows a red dot with low-stock items listed.
- [ ] **Livestock → Add Animal** saves a record, and it appears in the table.
- [ ] Editing that record pre-fills the form with its current values.
- [ ] Deleting it asks for confirmation naming the animal.
- [ ] **Inventory → Stock Movement** changes the balance by exactly the amount.
- [ ] Signing in as `worker` hides Finance and User Accounts from the sidebar.
- [ ] The theme toggle in the top bar switches between light and dark.
- [ ] **Reports → Print Report** produces a clean document with no navigation.
