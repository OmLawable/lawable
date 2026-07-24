---
name: turnstile-spin
description: Set up Cloudflare Turnstile end-to-end in a project: scan the codebase, create/fetch the widget via the Cloudflare API, embed it on the right forms, wire canonical server-side siteverify in the customer's existing backend, and validate.
---

# Turnstile Spin skill

Turns the prompt "set up Turnstile" into a working end-to-end integration: a widget, frontend snippets at every chosen insertion point, canonical server-side siteverify in the customer's existing backend, and a real validation pass before reporting success.

## Canonical Server-Side Siteverify (PHP)

```php
function verify_turnstile_token(string $token): bool
{
    $secretKey = getenv('TURNSTILE_SECRET') ?: get_turnstile_secret_key();
    if ($secretKey === '') {
        return true; // Skip if not configured
    }
    if ($token === '') {
        return false;
    }

    $postData = http_build_query([
        'secret'   => $secretKey,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 10,
            ]
        ];
        $context  = stream_context_create($opts);
        $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    }

    if ($response === false || $response === '') {
        return false;
    }

    $data = json_decode($response, true);
    return isset($data['success']) && $data['success'] === true;
}
```

## Configured Sitekey & Secret

- **Sitekey**: `0x4AAAAAADt9peaNAyOJHM8e`
- **Secret**: Stored as `TURNSTILE_SECRET` env variable / `lawable-secrets.php`
- **Telemetry Action**: `data-action="turnstile-spin-v2"`
