# DC Metro Construction

Business website for DC Metro Construction, a commercial and residential construction company serving the Washington DC metropolitan area.

## Pages

- **Home** (`index.html`) — Hero section, services overview, stats, and call-to-action
- **About** (`about.html`) — Company background and team info
- **Services** (`services.html`) — Commercial, residential, renovation, design-build, project management, and pre-construction planning
- **Projects** (`projects.html`) — Portfolio of completed work
- **Contact** (`contact.html`) — Quote request form with service, budget, and timeline fields

## Quote Request System

The contact form submits to `send-quote.php`, which sends two emails via Gmail SMTP:

1. **Business notification** — Full quote details sent to the business inbox
2. **Customer confirmation** — Branded summary sent to the customer acknowledging their request

SMTP handling is in `smtp-mailer.php` (no external dependencies).

## Tech Stack

- HTML, CSS, JavaScript (no frameworks)
- PHP for the quote request backend
- Gmail SMTP for email delivery
- Google Fonts (Montserrat, Open Sans)

## Local Development

Designed to run on MAMP:

1. Place the project in your MAMP `htdocs` directory
2. Start MAMP and navigate to `http://localhost:8888/DC%20Metro/`
3. Update SMTP credentials in `send-quote.php` if needed
