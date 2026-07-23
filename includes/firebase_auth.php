<?php

declare(strict_types=1);

/**
 * Firebase Authentication helper for Lawable.
 *
 * Uses kreait/firebase-php v8.x (full Admin SDK, PHP 8.3 compatible).
 * Replaces the old kreait/firebase-tokens v3 standalone verifier.
 *
 * Service account key: C:\xampp\firebase\lawable-service-account.json
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

if (!defined('FIREBASE_PROJECT_ID')) {
    define('FIREBASE_PROJECT_ID', 'lawable-9c1e0');
}
if (!defined('FIREBASE_SERVICE_ACCOUNT_PATH')) {
    $saPath = __DIR__ . '/../firebase-service-account.json';
    if (!file_exists($saPath)) {
        $saPathAbove = __DIR__ . '/../../firebase-service-account.json';
        if (file_exists($saPathAbove)) {
            $saPath = $saPathAbove;
        } elseif (file_exists('C:\\xampp\\firebase\\lawable-service-account.json')) {
            $saPath = 'C:\\xampp\\firebase\\lawable-service-account.json';
        }
    }
    define('FIREBASE_SERVICE_ACCOUNT_PATH', $saPath);
}

/**
 * Returns a configured Firebase Factory instance (singleton-style).
 */
function get_firebase_factory(): Factory
{
    static $factory = null;

    if ($factory === null) {
        $factory = (new Factory)->withServiceAccount(FIREBASE_SERVICE_ACCOUNT_PATH);
    }

    return $factory;
}

/**
 * Verify a Firebase ID token sent from the browser.
 *
 * Returns decoded claims array: ['uid', 'email', 'name']
 * Throws RuntimeException on failure.
 *
 * @param  string $idToken  Raw Firebase ID token from the client.
 * @return array            Decoded token claims.
 * @throws RuntimeException If token is invalid or expired.
 */
function verify_firebase_token(string $idToken): array
{
    if ($idToken === '') {
        throw new RuntimeException('No Firebase ID token provided.');
    }

    try {
        $auth         = get_firebase_factory()->createAuth();
        $verifiedToken = $auth->verifyIdToken($idToken);
        $claims        = $verifiedToken->claims();

        return [
            'uid'   => (string) ($claims->get('sub')   ?? ''),
            'email' => (string) ($claims->get('email') ?? ''),
            'name'  => (string) ($claims->get('name')  ?? ''),
        ];
    } catch (FailedToVerifyToken $e) {
        throw new RuntimeException('Firebase token verification failed: ' . $e->getMessage());
    } catch (Throwable $e) {
        throw new RuntimeException('Could not verify Firebase token: ' . $e->getMessage());
    }
}
