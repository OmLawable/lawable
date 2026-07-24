<?php

/**
 * Lawable — Cloudflare Turnstile Secrets
 *
 * This file sits outside the web root in production.
 * For local XAMPP development, placing it in the project root is fine.
 *
 * Get your keys at: https://dash.cloudflare.com/?to=/:account/turnstile
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Turnstile Site Key
    |--------------------------------------------------------------------------
    |
    | Used in the frontend Turnstile widget (data-sitekey attribute).
    |
    */
    'turnstile_site_key' => '0x4AAAAAADt9peaNAyOJHM8e',

    /*
    |--------------------------------------------------------------------------
    | Turnstile Secret Key
    |--------------------------------------------------------------------------
    |
    | Used server-side to verify the Turnstile token with Cloudflare.
    | Keep this value confidential.
    |
    */
    'turnstile_secret_key' => getenv('TURNSTILE_SECRET') ?: '0x4AAAAAADt9pQgqddw_SbYpBbJqkyKXCF0',

];
