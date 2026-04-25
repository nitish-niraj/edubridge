# EduBridge Platform - Zero-Cost Manual Setup Guide

This document outlines how to manually configure your `.env` file to run the entire EduBridge platform **100% free of cost**. By utilizing Laravel's built-in tools and open-source alternatives, you can completely avoid third-party APIs.

You should copy `.env.example` to a new file called `.env` before beginning.

## 1. Local Development (Zero Cost)
*   **`APP_KEY`**: Run `php artisan key:generate`.
*   **`APP_URL`**: Set to `http://localhost:8000`.
*   **`DB_CONNECTION`**: `sqlite` (Creates a free local db file) or `mysql` (XAMPP).
*   **`FILESYSTEM_DISK`**: `local` (Saves files to your hard drive, run `php artisan storage:link`).
*   **`MAIL_MAILER`**: `log` (Prints password resets to a local `laravel.log` file so you can read them without paying).
*   **`BROADCAST_DRIVER`**: `reverb` (Self-hosted WebSockets) or `log`.

---

## 2. Deploying LIVE to Production for $0

If you are ready to put EduBridge on the internet for real users, you can still maintain a massive infrastructure for exactly **$0.00/month**. 

Here is the exact stack you should use for a live, full-stack Laravel Vue app:

### The Server (Hosting)
*   **Oracle Cloud (Always Free Tier)**: This is currently the most generous free server on the internet. They give you an ARM VPS with **4 CPU cores and 24GB of RAM** forever, completely free. You can host your Laravel app, database, and WebSocket server perfectly on this single machine.
*   **Alternative**: Render.com (has a free web service tier, but it "sleeps" if no one visits it for 15 minutes, causing a slow 30-second load for the next visitor).

### The Database
*   **SQLite (Easiest)**: Laravel 11 natively supports SQLite in production. The database is just a file on your Oracle server. No need to pay for a managed DB.
*   **Supabase Database (Free Tier)**: If you strongly prefer PostgreSQL, Supabase offers a completely free, live PostgreSQL database that easily handles 500MB of data.

### Image & Document Storage
*   **Server Disk Storage**: Since Oracle Cloud gives you 200GB of free block volume storage, you do not need AWS S3. Just point your `FILESYSTEM_DISK=public` and save user avatars and teacher ID documents straight to your server.

### Real Emails (Like Password Resets)
You can't use `log` in production because real users need the emails sent to their actual inboxes.
*   **Brevo (formerly Sendinblue)**: Their 100% free tier allows you to send **300 real emails per day**.
*   **SendGrid**: Their free tier allows **100 emails per day**.
*   **Setup**: Sign up for Brevo, get the SMTP credentials, and update your `.env`:
    *   `MAIL_MAILER=smtp`
    *   `MAIL_HOST=smtp-relay.brevo.com`
    *   `MAIL_PORT=587`
    *   `MAIL_USERNAME=your_brevo_username`
    *   `MAIL_PASSWORD=your_brevo_password`

### Live Chat & WebSockets
*   **Laravel Reverb**: When hosting on a VPS like Oracle Cloud, you can run Laravel Reverb directly on the server for free.
*   **Pusher Sandbox**: If you don't want to manage Websocket ports, just use the free Pusher Sandbox plan. It supports **100 simultaneous users chatting at the exact same time**, and 200,000 messages a day for exactly $0.

### The Domain Name
This is the **only** thing you usually have to buy (~$10/year for a `.com`). 
However, you can use the free URL provided by your server (like an IP address or a Render.com subdomain), but for a platform like EduBridge, investing $10 a year in `edubridge.com` is highly recommended for trust.
