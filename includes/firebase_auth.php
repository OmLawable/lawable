<?php

declare(strict_types=1);

/**
 * Firebase Authentication helper for Lawable.
 *
 * Uses kreait/firebase-tokens (v3.x — PHP 8.0 compatible) to verify
 * Firebase ID tokens sent from the browser after a successful sign-in.
 *
 * Service account key must be at: C:\xampp\firebase\lawable-service-account.json
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\JWT\IdTokenVerifier;
use Kreait\Firebase\JWT\Error\IdTokenVerificationFailed;

/**
 * Path to the Firebase service account JSON key (outside web root).
 */
const FIREBASE_SERVICE_ACCOUNT_PATH = 'C:\\xampp\\firebase\\lawable-service-account.json';

/**
 * Firebase project ID.
 */
const FIREBASE_PROJECT_ID = 'lawable-9c1e0';

/**
 * Verify a Firebase ID token sent from the browser.
 *
 * Returns the decoded token payload array on success.
 * Throws RuntimeException on failure.
 *
 * @param  string $idToken  The raw Firebase ID token from the client.
 * @return array            Decoded token claims (uid, email, etc.)
 * @throws RuntimeException If the token is invalid or expired.
 */
function verify_firebase_token(string $idToken): array
{
    if ($idToken === '') {
        throw new RuntimeException('No Firebase ID token provided.');
    }

    try {
        $verifier = IdTokenVerifier::createWithProjectId(FIREBASE_PROJECT_ID);
        $token    = $verifier->verifyIdToken($idToken);

        // kreait/firebase-tokens v3.x: payload() returns a plain array of claims
        $payload = $token->payload();

        return [
            'uid'   => (string) ($payload['sub']   ?? ''),
            'email' => (string) ($payload['email'] ?? ''),
            'name'  => (string) ($payload['name']  ?? ''),
        ];
    } catch (IdTokenVerificationFailed $e) {
        throw new RuntimeException('Firebase token verification failed: ' . $e->getMessage());
    } catch (Throwable $e) {
        throw new RuntimeException('Could not verify Firebase token: ' . $e->getMessage());
    }
}
