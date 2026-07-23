<?php
declare(strict_types=1);

require_once __DIR__ . '/firestore.php';

/**
 * Check if a certificate exists for a student & course, or generate one if completed.
 */
function check_and_generate_certificate(string $studentId, string $courseId): ?array
{
    if (empty($studentId) || empty($courseId)) {
        return null;
    }

    $db = get_firestore();
    $certId = 'cert_' . $studentId . '_' . $courseId;

    // Check if certificate document already exists
    $existing = $db->get('certificates', $certId);
    if ($existing !== null) {
        return $existing;
    }

    // Load student and course info
    $student = $db->get('students', $studentId);
    $course  = $db->get('courses', $courseId);

    if (!$student || !$course) {
        return null;
    }

    // Determine student display name
    $studentName = trim((string) ($student['name'] ?? ''));
    if ($studentName === '') {
        $studentName = trim((string) ($student['username'] ?? ''));
    }
    if ($studentName === '') {
        $studentName = trim((string) ($student['email'] ?? 'Student'));
    }

    // Determine organization name and contact person
    $orgName = 'Lawable Academy of Law & Compliance';
    $contactPerson = 'Lawable Academic Board';

    $orgId = (string) ($course['organizationId'] ?? '');
    if ($orgId !== '') {
        $orgDoc = $db->get('organizations', $orgId);
        if ($orgDoc) {
            $orgName = !empty($orgDoc['organizationName']) ? $orgDoc['organizationName'] : (!empty($orgDoc['name']) ? $orgDoc['name'] : $orgName);
            $contactPerson = !empty($orgDoc['contactPerson']) ? $orgDoc['contactPerson'] : (!empty($orgDoc['name']) ? $orgDoc['name'] : $contactPerson);
        }
    }

    if (($orgName === 'Lawable Academy of Law & Compliance' || $orgName === 'Lawable Academy') && !empty($course['organizationName'])) {
        $orgName = $course['organizationName'];
    }
    if ($contactPerson === 'Lawable Academic Board') {
        if (!empty($course['contactPerson'])) {
            $contactPerson = $course['contactPerson'];
        } elseif (!empty($course['teacherName'])) {
            $contactPerson = $course['teacherName'];
        }
    }

    // Generate unique Certificate Number
    $uniqueCode = strtoupper(substr(md5($certId . '_lawable_cert'), 0, 6));
    $certNumber = 'LAW-' . date('Y') . '-' . $uniqueCode;
    $verifyHash = strtoupper(substr(hash('sha256', $certNumber . $studentId), 0, 10));

    $certDoc = [
        'id'                  => $certId,
        'certNumber'          => $certNumber,
        'verifyHash'          => $verifyHash,
        'studentId'           => $studentId,
        'studentName'         => $studentName,
        'courseId'            => $courseId,
        'courseTitle'         => $course['title'] ?? 'Legal Course',
        'category'            => $course['category'] ?? 'General Law',
        'difficulty'          => ucfirst((string)($course['difficulty'] ?? 'All Levels')),
        'organizationName'    => $orgName,
        'contactPerson'       => $contactPerson,
        'issuedAt'            => date('c'),
        'issuedDateFormatted' => date('F j, Y'),
    ];

    try {
        $db->set('certificates', $certDoc, $certId);
        return $certDoc;
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * Get all earned certificates for a student.
 */
function get_student_certificates(string $studentId): array
{
    if (empty($studentId)) return [];
    $db = get_firestore();
    $certs = $db->query('certificates', [['studentId', 'EQUAL', $studentId]], 100);
    usort($certs, function($a, $b) {
        return strcmp($b['issuedAt'] ?? '', $a['issuedAt'] ?? '');
    });
    return $certs;
}
