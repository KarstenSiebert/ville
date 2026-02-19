---

# Cardano Transaction Processing System

This system provides automated transaction handling, and internal account-based transfers (external using Cardano infrastructure).

---

## 📋 Requirements

Ensure the following programs are installed and accessible in your system path:

* `openssl`
* `cardano-address`
* `cardano-cli`

The system has been tested with:

* **PostgreSQL 16.x**
* **PostgreSQL 18.x**

---

## 📂 Installation

Copy all provided scripts to a directory in your system path, for example:

```bash
/usr/local/bin
```

Make sure all scripts are executable:

```bash
chmod +x /usr/local/bin/*.sh
```

---

## 🔐 Root Key Setup

Generate the root key by running:

```bash
./genroot.sh
```

The generated key must be stored at:

```
storage/private/transactions/root.key
```

**Important:**

* The key must be stored in **Base64 format**.
* All other keys in the system are deterministically derived from `root.key`.
* Keep this file secure and never expose it publicly.

---

## 🗄 Database Configuration

1. Create a PostgreSQL database.
2. Configure your environment variables inside the `.env` file.

> ⚠️ The `.env` file is not included in this repository and must be created manually.

Example `.env` parameters may include:

```
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

Migrate the tables into your database: php artisan migrate

```

---

## 📧 Mail Configuration

The system sends email notifications when new users register.

Ensure that:

* Your SMTP server is correctly configured.
* Mail credentials are properly defined in your `.env` file.
* Outgoing mail is permitted by your hosting provider.

---

## ⏱ Cron Jobs

Add the required scripts to your `crontab`.

```bash
crontab -e
```

Then add entries similar to:

```bash

* * * * * /bin/bash -lc 'echo "$(date) START readwallets" >> /var/log/ville/readwallets.log; /usr/bin/flock -n /tmp/readwallets-ville.lock /usr/bin/curl --ipv4 --max-time 300 https://www.tokenville.fun/readwallets >> /var/log/ville/readwallets.log 2>&1; echo "$(date) END readwallets (rc=$?)" >> /var/log/ville/readwallets.log'

* * * * * /bin/bash -lc 'echo "$(date) START sendassets" >> /var/log/ville/sendassets.log; /usr/bin/flock -n /tmp/sendassets-ville.lock /usr/bin/curl --ipv4 --max-time 300 https://www.tokenville.fun/sendassets >> /var/log/ville/sendassets.log 2>&1; echo "$(date) END sendassets (rc=$?)" >> /var/log/ville/sendassets.log'

* * * * * /bin/bash -lc 'echo "$(date) START transactions" >> /var/log/ville/transactions.log; /usr/bin/flock -n /tmp/transactions-ville.lock /usr/bin/curl --ipv4 --max-time 300 https://www.tokenville.fun/transactions >> /var/log/ville/transactions.log 2>&1; echo "$(date) END transactions (rc=$?)" >> /var/log/ville/transactions.log'

* * * * * cd /var/www/ville && php artisan schedule:run >> /dev/null 2>&1

```

### Important:

* Adjust script paths according to your installation.
* Replace `localhost` or example domains with your production domain.
* Ensure `/var/log` directories exist or update log paths inside the scripts.

---

## 🔄 Transaction Processing

The cron scripts are responsible for:

### Incoming Transactions

* Detecting new blockchain transactions
* Assigning received funds (tokens) to user accounts

### Outgoing Transactions

* Sending assets to external payout addresses (if configured in the user profile)
* Merging outgoing transactions to optimize UTxO usage
* Reducing fragmentation and improving transaction efficiency

---

## 🌐 API Configuration

The system submits transactions through an API server.

If you operate your own API instance:

* Update the configuration inside `chimera.conf`
* Ensure connectivity and proper authentication

---

## ⚡ Internal Account System

The system uses **Chimera’s account-based transaction model** to enable:

* Fast local transfers
* Feeless internal transactions
* Efficient balance management between platform accounts

---

## 🔒 Security Notes

* Never expose `root.key`.
* Restrict access to `storage/private/transactions/`.
* Secure your `.env` file.
* Use proper file permissions (e.g., `600` for sensitive keys).
* Consider running services under a dedicated system user.

---

## 🚀 Production Recommendations

* Use a dedicated Cardano node or reliable API provider.
* Enable monitoring for cron jobs.
* Implement database backups.
* Secure SMTP with TLS.
* Use a firewall and fail2ban where applicable.

---

## 🚀 Getting Started

Once the setup is complete, you can start the system.

The first registered user will automatically be assigned the Administrator role.

All subsequent registrations will be created as standard users.

## 🔌 API Access

The system provides API access for remote interaction and integration.

The API enables extended functionality and external system communication.

Full API documentation is provided in a separate document.

