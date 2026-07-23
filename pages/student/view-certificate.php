<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';
require_once __DIR__ . '/../../includes/certificates.php';

start_secure_session();

$user = require_login(); // Logged-in user can view
$db   = get_firestore();

$certId   = trim((string) ($_GET['id'] ?? ''));
$courseId = trim((string) ($_GET['course_id'] ?? ''));

$certificate = null;

if ($certId !== '') {
    $certificate = $db->get('certificates', $certId);
}

if (!$certificate && $courseId !== '') {
    $studentId   = (string) $user['id'];
    $certificate = check_and_generate_certificate($studentId, $courseId);
}

// Fallback: If certId contains courseId format
if (!$certificate && str_contains($certId, '_')) {
    $parts = explode('_', $certId);
    if (count($parts) >= 3) {
        $studentId = $parts[1];
        $cId       = $parts[2];
        $certificate = check_and_generate_certificate($studentId, $cId);
    }
}

if (!$certificate) {
    die('<div style="text-align:center; padding:4rem; font-family:sans-serif; color:#4B5563;"><h2>Certificate Not Found</h2><p>Please complete 100% of the course lessons to generate your certificate.</p><a href="../dashboard.php">← Back to Dashboard</a></div>');
}

// Dynamically resolve Organization Name and Contact Person
$courseId = (string) ($certificate['courseId'] ?? '');
$orgName = 'Lawable Academy of Law & Compliance';
$contactPerson = 'Lawable Academic Board';

if ($courseId !== '') {
    $courseDoc = $db->get('courses', $courseId);
    if ($courseDoc) {
        $orgId = (string) ($courseDoc['organizationId'] ?? '');
        if ($orgId !== '') {
            $orgDoc = $db->get('organizations', $orgId);
            if ($orgDoc) {
                $orgName = !empty($orgDoc['organizationName']) ? $orgDoc['organizationName'] : (!empty($orgDoc['name']) ? $orgDoc['name'] : $orgName);
                $contactPerson = !empty($orgDoc['contactPerson']) ? $orgDoc['contactPerson'] : (!empty($orgDoc['name']) ? $orgDoc['name'] : $contactPerson);
            }
        }
        
        if (($orgName === 'Lawable Academy of Law & Compliance' || $orgName === 'Lawable Academy') && !empty($courseDoc['organizationName'])) {
            $orgName = $courseDoc['organizationName'];
        }
        if ($contactPerson === 'Lawable Academic Board') {
            if (!empty($courseDoc['contactPerson'])) {
                $contactPerson = $courseDoc['contactPerson'];
            } elseif (!empty($courseDoc['teacherName'])) {
                $contactPerson = $courseDoc['teacherName'];
            }
        }
    }
}

if (($orgName === 'Lawable Academy of Law & Compliance' || $orgName === 'Lawable Academy') && !empty($certificate['organizationName']) && $certificate['organizationName'] !== 'Lawable Academy') {
    $orgName = $certificate['organizationName'];
}
if ($contactPerson === 'Lawable Academic Board' && !empty($certificate['contactPerson'])) {
    $contactPerson = $certificate['contactPerson'];
}

$title = 'Certificate - ' . ($certificate['courseTitle'] ?? 'Lawable');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($title) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,400&family=Inter:wght@400;500;600;700&family=Cinzel:wght@600;700&family=Great+Vibes&display=swap" rel="stylesheet" />
  <style>
    :root {
      --gold: #C9933A;
      --gold-dk: #A8732A;
      --gold-lt: #F4E4C3;
      --ink: #1C1B17;
      --ink-mid: #5B5A52;
      --cream: #FBF7EF;
      --paper: #FFFDF9;
      --border: #E6E0D2;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      padding: 2rem 1rem;
      background: #0D1117;
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--ink);
    }

    /* Print / Action Bar */
    .cert-actions {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .btn-action {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.7rem 1.4rem;
      border-radius: 9999px;
      font-size: 0.9rem;
      font-weight: 600;
      text-decoration: none;
      border: none;
      cursor: pointer;
      transition: background 0.2s, transform 0.15s;
    }
    .btn-print { background: var(--gold); color: #FFFFFF; }
    .btn-print:hover { background: var(--gold-dk); transform: translateY(-1px); }
    .btn-back { background: rgba(255,255,255,0.12); color: #FFFFFF; }
    .btn-back:hover { background: rgba(255,255,255,0.2); }

    /* Certificate Outer Frame */
    .cert-frame {
      width: min(100%, 940px);
      background: var(--paper);
      border-radius: 20px;
      padding: 2.2rem 2.8rem;
      box-shadow: 0 25px 60px rgba(0,0,0,0.45);
      position: relative;
      overflow: hidden;
      border: 1px solid var(--border);
    }

    /* Ornamental Inner Double Border */
    .cert-inner-border {
      border: 6px double var(--gold);
      border-radius: 12px;
      padding: 2.2rem 2.2rem;
      text-align: center;
      position: relative;
      background: radial-gradient(circle at center, #FFFFFF 0%, #FAF6ED 100%);
    }

    /* Watermark Seal */
    .cert-watermark {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-size: 18rem;
      opacity: 0.03;
      pointer-events: none;
      font-family: 'Cinzel', serif;
      user-select: none;
    }

    .cert-header-mark {
      font-family: 'Cinzel', serif;
      font-size: 0.88rem;
      font-weight: 700;
      letter-spacing: 0.2em;
      color: var(--gold-dk);
      text-transform: uppercase;
      margin-bottom: 0.5rem;
    }

    .cert-title {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      font-weight: 800;
      color: var(--ink);
      letter-spacing: 0.02em;
      margin: 0 0 1rem 0;
      text-transform: uppercase;
    }

    .cert-subtitle {
      font-size: 0.9rem;
      font-style: italic;
      color: var(--ink-mid);
      margin-bottom: 0.6rem;
    }

    .cert-student-name {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--ink);
      margin: 0.3rem 0 0.4rem 0;
      display: inline-block;
    }

    .cert-name-line {
      width: 320px;
      max-width: 65%;
      height: 0;
      border-bottom: 2px solid #C9933A;
      margin: 0 auto 1.1rem auto;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .cert-body-text {
      font-size: 0.92rem;
      color: var(--ink-mid);
      line-height: 1.5;
      max-width: 660px;
      margin: 0 auto 1rem auto;
    }

    .cert-course-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.55rem;
      font-weight: 700;
      color: var(--gold-dk);
      margin: 0.3rem 0 1.2rem 0;
    }

    /* Footer Signatures & Credentials */
    .cert-footer {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-top: 1.5rem;
      padding-top: 1rem;
      border-top: 1px solid var(--border);
    }

    .cert-sig-box {
      text-align: center;
      flex: 1;
    }
    .cert-sig-line {
      width: 150px;
      height: 1px;
      background: var(--ink-mid);
      border-bottom: 1px solid var(--ink-mid);
      margin: 0.2rem auto 0.3rem auto;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .cert-sig-title {
      font-size: 0.74rem;
      font-weight: 600;
      color: var(--ink-mid);
    }

    .cert-seal-wrap {
      flex: 0 0 100px;
      text-align: center;
    }

    .cert-seal {
      width: 82px;
      height: 82px;
      border-radius: 50%;
      background: linear-gradient(135deg, #E6B35C 0%, #A8732A 100%);
      box-shadow: 0 4px 14px rgba(168,115,42,0.3);
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #FFFFFF;
      text-align: center;
      border: 3px solid #FFFDF9;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .cert-seal-icon { font-size: 1.4rem; }
    .cert-seal-text { font-family: 'Cinzel', serif; font-size: 0.5rem; font-weight: 700; letter-spacing: 0.08em; margin-top: 0.15rem; }

    .cert-meta-box {
      text-align: right;
      font-size: 0.8rem;
      color: var(--ink-mid);
      line-height: 1.5;
      flex: 1;
    }
    .cert-meta-number {
      font-weight: 700;
      color: var(--ink);
    }

    @media print {
      @page {
        size: landscape;
        margin: 0;
      }
      html, body {
        width: 100vw !important;
        height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        background: #FFFFFF !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      .cert-actions {
        display: none !important;
      }
      .cert-frame {
        box-shadow: none !important;
        border: none !important;
        width: 100vw !important;
        height: 100vh !important;
        max-width: 100vw !important;
        padding: 0.8cm 1.2cm !important;
        border-radius: 0 !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
      }
      .cert-inner-border {
        height: 100% !important;
        padding: 1.2rem 1.5rem !important;
        box-sizing: border-box !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
      }
      .cert-title { font-size: 1.75rem !important; margin-bottom: 0.5rem !important; }
      .cert-student-name { font-size: 2.1rem !important; margin: 0.2rem 0 0.3rem 0 !important; }
      .cert-name-line {
        display: block !important;
        width: 300px !important;
        max-width: 60% !important;
        height: 0 !important;
        border-bottom: 2px solid #C9933A !important;
        margin: 0.2rem auto 0.8rem auto !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      .cert-course-title { font-size: 1.4rem !important; margin: 0.2rem 0 0.8rem 0 !important; }
      .cert-body-text { font-size: 0.85rem !important; margin-bottom: 0.6rem !important; }
      .cert-footer {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: flex-end !important;
        width: 100% !important;
        margin-top: 1rem !important;
        padding-top: 0.6rem !important;
      }
      .cert-sig-box {
        text-align: center !important;
        flex: 1 !important;
      }
      .cert-seal-wrap {
        flex: 0 0 100px !important;
        text-align: center !important;
      }
      .cert-meta-box {
        text-align: right !important;
        flex: 1 !important;
      }
      *, *:before, *:after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
      }
    }

    @media screen and (max-width: 768px) {
      .cert-frame { padding: 1.5rem; }
      .cert-inner-border { padding: 2rem 1rem; }
      .cert-student-name { font-size: 1.9rem; }
      .cert-course-title { font-size: 1.3rem; }
      .cert-footer { flex-direction: column; align-items: center; gap: 1.5rem; text-align: center; }
      .cert-meta-box { text-align: center; }
    }
  </style>
</head>
<body>

  <div class="cert-actions">
    <a href="../dashboard.php" class="btn-action btn-back">← Back to Dashboard</a>
    <button type="button" onclick="window.print()" class="btn-action btn-print">🖨️ Print / Save as PDF</button>
  </div>

  <div class="cert-frame">
    <div class="cert-inner-border">
      <div class="cert-watermark">⚖️</div>

      <div class="cert-header-mark"><?= e($orgName) ?></div>
      <h1 class="cert-title">Certificate of Completion</h1>

      <div class="cert-subtitle">This is to certify that</div>

      <div class="cert-student-name"><?= e($certificate['studentName'] ?? 'Student') ?></div>
      <div class="cert-name-line"></div>

      <div class="cert-body-text">
        has successfully fulfilled all curriculum requirements, assessments, and practical case evaluations for the specialized professional course
      </div>

      <div class="cert-course-title"><?= e($certificate['courseTitle'] ?? 'Legal Course') ?></div>

      <div style="font-size: 0.85rem; font-weight: 600; color: var(--gold-dk); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 2rem;">
        <?= e($certificate['category'] ?? 'General Law') ?> • <?= e($certificate['difficulty'] ?? 'Advanced') ?>
      </div>

      <div class="cert-footer">
        <div class="cert-sig-box">
          <div style="font-family: 'Great Vibes', cursive; font-size: 2.2rem; color: #1C1B17; transform: rotate(-3deg); margin-bottom: -0.2rem; display: inline-block; white-space: nowrap;">
            <?= e($contactPerson) ?>
          </div>
          <div class="cert-sig-line"></div>
          <div style="font-size: 0.85rem; font-weight: 700; color: var(--ink); margin-top: 0.35rem;">
            <?= e($contactPerson) ?>
          </div>
          <div class="cert-sig-title">Authorized Signatory</div>
        </div>

        <div class="cert-seal-wrap">
          <div class="cert-seal">
            <span class="cert-seal-icon">⚖️</span>
            <span class="cert-seal-text">VERIFIED CREDENTIAL</span>
          </div>
        </div>

        <div class="cert-meta-box">
          <div>Issued On: <strong><?= e($certificate['issuedDateFormatted'] ?? date('F j, Y')) ?></strong></div>
          <div>Certificate ID: <span class="cert-meta-number"><?= e($certificate['certNumber'] ?? '') ?></span></div>
        </div>
      </div>

    </div>
  </div>

</body>
</html>
