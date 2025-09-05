# Expose Server

A beautiful, fully open-source tunneling service written in pure PHP.

## Requirements

- Docker & Docker Compose (recommended)
- PHP 8.2+ and Composer (for running locally)

## Quick Start

1. Copy or create an `.env` file at the project root. The repository uses the following environment variables.

   - `PORT` — port to expose on the host (default: 8080)
   - `DOMAIN` — domain used by the server (e.g. `localhost`)
   - `ADMIN_USERNAME` / `ADMIN_PASSWORD` — credentials for admin dashboard
   - `ADMIN_SUBDOMAIN` — subdomain to access the admin dashboard
   - `RESERVED_SUBDOMAINS` — comma-separated list of reserved subdomains (e.g. `www,admin,api`)

2. Install the dependencies.

   ```bash
   composer install
   ```

2. Start the server.

   ```bash
   ./expose-server serve <domain> --port <port> --validateAuthTokens
   ```

## Using Docker

1. Build the image.

   ```bash
   docker build -t expose-server:latest .
   ```

2. Run the image.

   ```bash
    docker run --rm -p 8080:8080 \
        -e DOMAIN=example.com -e PORT=8080 \
        -e ADMIN_USERNAME=admin -e ADMIN_PASSWORD=secret \
        expose-server:latest
   ```
