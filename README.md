<div align="center">

# Lulus

**A graduation photography website with online booking and an admin dashboard.**

<img src="assets/crowd.webp" width="720" alt="Lulus graduation photography portfolio" />

<p>
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.0+" />
  <img src="https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/Python-3-3776AB?style=for-the-badge&logo=python&logoColor=white" alt="Python 3" />
  <img src="https://img.shields.io/badge/cPanel-Ready-FF6C2C?style=for-the-badge&logo=cpanel&logoColor=white" alt="cPanel ready" />
</p>

</div>

## Overview

Lulus showcases graduation photography packages and availability. Clients can request a session and track its status; staff can manage bookings, time slots, discounts, and photo delivery from the admin dashboard.

## Features

| Area | What it does |
|---|---|
| Public website | Portfolio, Single/Couple/Group/Arak-arakan packages, add-ons, and booking form |
| Booking | Server-side price and duration validation, booking codes, and status tracking by code and email |
| Admin dashboard | Booking management, manual bookings, slot calendar, discounts, and ready-to-send WhatsApp messages |
| Notifications | Optional email updates; booking still works when email is unavailable |

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, and vanilla JavaScript |
| Backend | PHP 8.0+ |
| Database | MySQL |
| Build and preview | Python 3; no frontend package manager or build framework |
| Hosting | Shared hosting with cPanel |

## Getting Started

Run the generator from the project root:

```bash
python3 build.py
```

Open `dist/preview/lulus-landing.html` to explore the design. The preview simulates the booking interface and does not save requests. To test real bookings, configure PHP and MySQL using `php/app/config.sample.php` as the template.

Package prices and rules live in `build.py`. One build supplies the price cards, booking form, and server-side catalog.

## Project Structure

```text
.
├── template.html          # Main page template
├── build.py               # Preview and deployment generator
├── php/                   # API, booking logic, installer, and admin pages
├── assets/                # Photography and site images
├── fonts/                 # Local fonts
├── dist/
│   ├── preview/           # Self-contained HTML preview
│   └── deploy/            # Files for cPanel public_html
└── CARA-DEPLOY.md         # Detailed deployment guide
```

## Deployment

Upload the **contents** of `dist/deploy/` to `public_html`. Create a MySQL database, copy `app/config.sample.php` to `app/config.php` on the server, and fill in the database and contact settings. Run `install.php` once to create the tables and set up admin access, then remove it from the server.

See the [cPanel deployment guide](CARA-DEPLOY.md) for the full setup. The guide is currently in Indonesian.

## Security and Licensing

Keep `app/config.php` and all credentials out of Git. Configure an admin password and access secret before opening the site to the public. The repository does not currently specify a code license or grant reuse rights for its photographs.
