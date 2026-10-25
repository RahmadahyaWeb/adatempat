<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
## Identifiers in URLs and authorization

- Never place numeric database primary keys in application URLs. Use an opaque public identifier (`public_id`) backed by a ULID for owner-management routes; use a stable slug only where a human-readable public URL is a product requirement.
- Do not treat a ULID, UUID, or slug as authorization. Every owner-managed record lookup and mutation must be scoped through the authenticated owner's relationship and must not rely on a client-supplied identifier.
- Avoid storing sequential primary keys in public Livewire state when an opaque public identifier is available.
- Keep email-verification URLs free of sequential user IDs; use the user's opaque public identifier and validate the signed URL, authenticated account, and email hash.
- For cross-owner access, prefer a not-found response to avoid disclosing the existence of another owner's records.
