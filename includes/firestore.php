<?php

declare(strict_types=1);

/**
 * FirestoreClient — Firestore REST API wrapper for Lawable.
 *
 * Why REST instead of the google/cloud-firestore SDK?
 *   The SDK requires ext-grpc, which has no pre-built Windows PHP 8.3 DLL.
 *   The Firestore REST API is fully featured and production-ready.
 *
 * Dependencies (already installed via Composer):
 *   - google/auth       → service account OAuth2 token generation
 *   - guzzlehttp/guzzle → HTTP requests to Firestore REST API
 *
 * Usage:
 *   $db = get_firestore();
 *   $doc  = $db->get('students', $uid);
 *   $id   = $db->set('courses', $data);
 *   $db->update('students', $uid, ['status' => 'inactive']);
 *   $db->delete('enrollments', $uid . '_' . $courseId);
 *   $docs = $db->query('courses', [['status', 'EQUAL', 'published']], 20);
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;

// ── Constants (shared with firebase_auth.php) ─────────────────────────────
if (!defined('FIREBASE_PROJECT_ID')) {
    define('FIREBASE_PROJECT_ID', 'lawable-9c1e0');
}
if (!defined('FIREBASE_SERVICE_ACCOUNT_PATH')) {
    define('FIREBASE_SERVICE_ACCOUNT_PATH', 'C:\\xampp\\firebase\\lawable-service-account.json');
}

// ═══════════════════════════════════════════════════════════════════════════
//  FirestoreClient
// ═══════════════════════════════════════════════════════════════════════════

final class FirestoreClient
{
    private const SCOPE       = 'https://www.googleapis.com/auth/datastore';
    private const FIRESTORE   = 'https://firestore.googleapis.com/v1';

    private string     $projectId;
    private string     $baseUrl;
    private HttpClient $http;
    private array      $serviceAccount;

    /** @var string|null cached access token */
    private ?string $token      = null;
    private int     $tokenExpiry = 0;

    // ── Constructor ───────────────────────────────────────────────────────

    public function __construct(string $projectId, string $serviceAccountPath)
    {
        if (!file_exists($serviceAccountPath)) {
            throw new \RuntimeException("Firebase service account not found: {$serviceAccountPath}");
        }

        $this->projectId      = $projectId;
        $this->baseUrl        = self::FIRESTORE . "/projects/{$projectId}/databases/(default)/documents";
        $this->http           = new HttpClient(['timeout' => 30.0]);
        $this->serviceAccount = json_decode(file_get_contents($serviceAccountPath), true, 512, JSON_THROW_ON_ERROR);
    }

    // ── Auth token ────────────────────────────────────────────────────────

    private function getToken(): string
    {
        if ($this->token !== null && time() < $this->tokenExpiry - 60) {
            return $this->token;
        }

        $credentials = new ServiceAccountCredentials(self::SCOPE, $this->serviceAccount);
        $tokenData   = $credentials->fetchAuthToken();

        if (empty($tokenData['access_token'])) {
            throw new \RuntimeException('Failed to obtain Firestore access token.');
        }

        $this->token       = $tokenData['access_token'];
        $this->tokenExpiry = time() + (int) ($tokenData['expires_in'] ?? 3600);

        return $this->token;
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getToken(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    // ── Value encoding (PHP → Firestore typed value) ──────────────────────

    public function encode(mixed $value): array
    {
        if ($value === null) {
            return ['nullValue' => null];
        }
        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }
        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        if ($value instanceof \DateTimeInterface) {
            // Always store as UTC ISO 8601
            $utc = (clone $value)->setTimezone(new \DateTimeZone('UTC'));
            return ['timestampValue' => $utc->format('Y-m-d\TH:i:s\Z')];
        }
        if (is_string($value)) {
            return ['stringValue' => $value];
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return ['arrayValue' => ['values' => array_map([$this, 'encode'], $value)]];
            }
            return ['mapValue' => ['fields' => $this->encodeFields($value)]];
        }

        return ['stringValue' => (string) $value];
    }

    private function encodeFields(array $data): array
    {
        $out = [];
        foreach ($data as $key => $val) {
            $out[$key] = $this->encode($val);
        }
        return $out;
    }

    // ── Value decoding (Firestore typed value → PHP) ──────────────────────

    public function decode(array $typed): mixed
    {
        foreach ($typed as $type => $val) {
            return match ($type) {
                'nullValue'      => null,
                'booleanValue'   => (bool) $val,
                'integerValue'   => (int) $val,
                'doubleValue'    => (float) $val,
                'stringValue'    => (string) $val,
                'timestampValue' => (string) $val,          // ISO string
                'arrayValue'     => array_map(
                                       [$this, 'decode'],
                                       $val['values'] ?? []
                                   ),
                'mapValue'       => $this->decodeFields($val['fields'] ?? []),
                default          => $val,
            };
        }
        return null;
    }

    private function decodeFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $val) {
            $out[$key] = $this->decode($val);
        }
        return $out;
    }

    private function docToArray(array $doc): array
    {
        $data         = $this->decodeFields($doc['fields'] ?? []);
        $data['__id'] = basename($doc['name'] ?? '');   // e.g. 'abc123'
        return $data;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Public CRUD API
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Get a single document. Returns null if not found.
     */
    public function get(string $collection, string $docId): ?array
    {
        try {
            $res = $this->http->get("{$this->baseUrl}/{$collection}/{$docId}", [
                'headers' => $this->headers(),
            ]);
            return $this->docToArray(json_decode($res->getBody()->getContents(), true));
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw new \RuntimeException("Firestore get({$collection}/{$docId}) failed: " . $e->getMessage());
        }
    }

    /**
     * Create or fully overwrite a document.
     * If $docId is null, Firestore auto-generates one.
     * Returns the document ID.
     */
    public function set(string $collection, array $data, ?string $docId = null): string
    {
        $body = json_encode(['fields' => $this->encodeFields($data)], JSON_THROW_ON_ERROR);

        if ($docId !== null) {
            // PATCH without updateMask = full overwrite
            $res = $this->http->patch(
                "{$this->baseUrl}/{$collection}/{$docId}",
                ['headers' => $this->headers(), 'body' => $body]
            );
        } else {
            // POST → Firestore auto-generates the document ID
            $res = $this->http->post(
                "{$this->baseUrl}/{$collection}",
                ['headers' => $this->headers(), 'body' => $body]
            );
        }

        $doc = json_decode($res->getBody()->getContents(), true);
        return basename($doc['name'] ?? '');
    }

    /**
     * Update specific fields in an existing document (merge — other fields preserved).
     */
    public function update(string $collection, string $docId, array $data): void
    {
        if (empty($data)) {
            return;
        }

        // Build updateMask query string
        $mask = implode('&', array_map(
            static fn($k) => 'updateMask.fieldPaths=' . rawurlencode($k),
            array_keys($data)
        ));

        $this->http->patch(
            "{$this->baseUrl}/{$collection}/{$docId}?{$mask}",
            [
                'headers' => $this->headers(),
                'body'    => json_encode(['fields' => $this->encodeFields($data)], JSON_THROW_ON_ERROR),
            ]
        );
    }

    /**
     * Delete a document. Silently succeeds if the document does not exist.
     */
    public function delete(string $collection, string $docId): void
    {
        try {
            $this->http->delete("{$this->baseUrl}/{$collection}/{$docId}", [
                'headers' => $this->headers(),
            ]);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw new \RuntimeException("Firestore delete({$collection}/{$docId}) failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Query a collection with optional filters, ordering, and limit.
     *
     * $filters: array of [fieldPath, operator, value]
     *
     * Supported operators:
     *   EQUAL, NOT_EQUAL, LESS_THAN, LESS_THAN_OR_EQUAL,
     *   GREATER_THAN, GREATER_THAN_OR_EQUAL,
     *   ARRAY_CONTAINS, IN, ARRAY_CONTAINS_ANY, NOT_IN
     *
     * $orderBy: array of [fieldPath, 'ASCENDING'|'DESCENDING']
     *
     * Example:
     *   $db->query('courses',
     *     [['status', 'EQUAL', 'published'], ['category', 'EQUAL', 'Criminal Law']],
     *     20,
     *     [['createdAt', 'DESCENDING']]
     *   );
     */
    public function query(
        string $collection,
        array  $filters  = [],
        ?int   $limit    = null,
        array  $orderBy  = []
    ): array {
        $query = ['from' => [['collectionId' => $collection]]];

        if (!empty($filters)) {
            $filterNodes = array_map(function (array $f): array {
                [$field, $op, $value] = $f;
                return ['fieldFilter' => [
                    'field' => ['fieldPath' => $field],
                    'op'    => $op,
                    'value' => $this->encode($value),
                ]];
            }, $filters);

            $query['where'] = count($filterNodes) === 1
                ? $filterNodes[0]
                : ['compositeFilter' => ['op' => 'AND', 'filters' => $filterNodes]];
        }

        if (!empty($orderBy)) {
            $query['orderBy'] = array_map(static fn(array $o) => [
                'field'     => ['fieldPath' => $o[0]],
                'direction' => strtoupper($o[1] ?? 'ASCENDING'),
            ], $orderBy);
        }

        if ($limit !== null) {
            $query['limit'] = $limit;
        }

        $res     = $this->http->post(
            self::FIRESTORE . "/projects/{$this->projectId}/databases/(default)/documents:runQuery",
            [
                'headers' => $this->headers(),
                'body'    => json_encode(['structuredQuery' => $query], JSON_THROW_ON_ERROR),
            ]
        );

        $rows = json_decode($res->getBody()->getContents(), true);
        $docs = [];
        foreach ($rows as $row) {
            if (isset($row['document'])) {
                $docs[] = $this->docToArray($row['document']);
            }
        }
        return $docs;
    }

    /**
     * Delete documents older than $days days (by $dateField).
     * Used for capping activityLogs at 30 days.
     */
    public function pruneOlderThan(string $collection, int $days, string $dateField = 'createdAt'): int
    {
        $cutoff = (new \DateTime('now', new \DateTimeZone('UTC')))->modify("-{$days} days");
        $docs   = $this->query(
            $collection,
            [[$dateField, 'LESS_THAN', $cutoff]],
            500
        );

        foreach ($docs as $doc) {
            if (!empty($doc['__id'])) {
                $this->delete($collection, $doc['__id']);
            }
        }

        return count($docs);
    }

    /**
     * Batch-write up to 500 documents at once.
     * $writes = [['collection', 'docId', $data], ...]
     *
     * Much faster than individual set() calls during migration.
     */
    public function batchSet(array $writes): void
    {
        $firestoreWrites = [];
        foreach ($writes as [$collection, $docId, $data]) {
            $name              = "projects/{$this->projectId}/databases/(default)/documents/{$collection}/{$docId}";
            $firestoreWrites[] = [
                'update' => [
                    'name'   => $name,
                    'fields' => $this->encodeFields($data),
                ],
            ];
        }

        // Firestore batch limit = 500 writes per request
        foreach (array_chunk($firestoreWrites, 500) as $chunk) {
            $this->http->post(
                self::FIRESTORE . "/projects/{$this->projectId}/databases/(default)/documents:batchWrite",
                [
                    'headers' => $this->headers(),
                    'body'    => json_encode(['writes' => $chunk], JSON_THROW_ON_ERROR),
                ]
            );
        }
    }

    /**
     * Convenience: return a server timestamp string (UTC ISO 8601).
     * Use this when writing createdAt / updatedAt fields.
     */
    public static function now(): string
    {
        return (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  Singleton helper
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Returns the shared FirestoreClient instance.
 *
 * Usage:
 *   $db = get_firestore();
 */
function get_firestore(): FirestoreClient
{
    static $client = null;

    if ($client === null) {
        $client = new FirestoreClient(
            FIREBASE_PROJECT_ID,
            FIREBASE_SERVICE_ACCOUNT_PATH
        );
    }

    return $client;
}
