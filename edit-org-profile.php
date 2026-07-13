<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

start_secure_session();
require_login('organization');

$pdo = get_pdo();
$user = $_SESSION['user'];
$errors = [];
$success = '';

function fetch_org_profile(PDO $pdo, int $orgId): array
{
    $stmt = $pdo->prepare(
        'SELECT
            o.id,
            o.organization_name,
            o.contact_person,
            o.email,
            o.phone,
            op.display_name,
            op.official_email,
            op.organization_type,
            op.tagline,
            op.about_description,
            op.year_established,
            op.website_url
        FROM organizations o
        LEFT JOIN organization_profiles op ON op.organization_id = o.id
        WHERE o.id = :org_id
        LIMIT 1'
    );
    $stmt->execute([':org_id' => $orgId]);

    $profile = $stmt->fetch();
    if (!$profile) {
        throw new RuntimeException('Organization account not found.');
    }

    return $profile;
}

try {
    $profile = fetch_org_profile($pdo, (int) $user['id']);
} catch (Throwable $exception) {
    $profile = [
        'id' => (int) $user['id'],
        'organization_name' => $user['organization_name'] ?? '',
        'contact_person' => $user['name'] ?? '',
        'email' => $user['email'] ?? '',
        'phone' => $user['phone'] ?? '',
        'display_name' => '',
        'official_email' => '',
        'organization_type' => '',
        'tagline' => '',
        'about_description' => '',
        'year_established' => '',
        'website_url' => '',
    ];
    $errors[] = 'Please create the organization_profiles table before editing profiles.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');

        $organizationName = trim((string) ($_POST['organization_name'] ?? ''));
        $contactPerson = trim((string) ($_POST['contact_person'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $organizationType = trim((string) ($_POST['organization_type'] ?? ''));
        $tagline = trim((string) ($_POST['tagline'] ?? ''));
        $aboutDescription = trim((string) ($_POST['about_description'] ?? ''));
        $yearEstablished = trim((string) ($_POST['year_established'] ?? ''));
        $websiteUrl = trim((string) ($_POST['website_url'] ?? ''));
        $officialEmail = trim((string) ($_POST['official_email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($organizationName === '' || strlen($organizationName) < 2) {
            throw new RuntimeException('Organization name must be at least 2 characters.');
        }

        if (strlen($organizationName) > 255) {
            throw new RuntimeException('Organization name must be 255 characters or fewer.');
        }

        if ($contactPerson === '' || strlen($contactPerson) < 2) {
            throw new RuntimeException('Contact person name must be at least 2 characters.');
        }

        if (strlen($contactPerson) > 150) {
            throw new RuntimeException('Contact person name must be 150 characters or fewer.');
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,30}$/', $phone)) {
            throw new RuntimeException('Please enter a valid phone number.');
        }

        foreach ([
            'Display name' => $displayName,
            'Tagline' => $tagline,
            'Website URL' => $websiteUrl,
        ] as $label => $value) {
            if (strlen($value) > 255) {
                throw new RuntimeException($label . ' must be 255 characters or fewer.');
            }
        }

        if (strlen($aboutDescription) > 2000) {
            throw new RuntimeException('About description must be 2000 characters or fewer.');
        }

        if ($yearEstablished !== '') {
            if (!preg_match('/^[0-9]{4}$/', $yearEstablished)) {
                throw new RuntimeException('Year established must be a 4-digit year (e.g., 2020).');
            }
            $currentYear = (int) date('Y');
            $year = (int) $yearEstablished;
            if ($year > $currentYear || $year < 1800) {
                throw new RuntimeException('Year established must be between 1800 and ' . $currentYear . '.');
            }
        }

        if ($websiteUrl !== '' && !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Please enter a valid website URL.');
        }

        if ($officialEmail !== '' && !is_valid_email($officialEmail)) {
            throw new RuntimeException('Please enter a valid official email address.');
        }

        if (strlen($officialEmail) > 255) {
            throw new RuntimeException('Official email must be 255 characters or fewer.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE organizations SET organization_name = :organization_name, contact_person = :contact_person, phone = :phone WHERE id = :org_id');
        $stmt->execute([
            ':organization_name' => $organizationName,
            ':contact_person' => $contactPerson,
            ':phone' => $phone !== '' ? $phone : null,
            ':org_id' => (int) $user['id'],
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO organization_profiles
                (organization_id, official_email, display_name, organization_type, tagline, about_description, year_established, website_url)
            VALUES
                (:organization_id, :official_email, :display_name, :organization_type, :tagline, :about_description, :year_established, :website_url)
            ON DUPLICATE KEY UPDATE
                official_email = VALUES(official_email),
                display_name = VALUES(display_name),
                organization_type = VALUES(organization_type),
                tagline = VALUES(tagline),
                about_description = VALUES(about_description),
                year_established = VALUES(year_established),
                website_url = VALUES(website_url)'
        );
        $stmt->execute([
            ':organization_id' => (int) $user['id'],
            ':official_email' => $officialEmail !== '' ? $officialEmail : null,
            ':display_name' => $displayName !== '' ? $displayName : null,
            ':organization_type' => $organizationType !== '' ? $organizationType : null,
            ':tagline' => $tagline !== '' ? $tagline : null,
            ':about_description' => $aboutDescription !== '' ? $aboutDescription : null,
            ':year_established' => $yearEstablished !== '' ? (int) $yearEstablished : null,
            ':website_url' => $websiteUrl !== '' ? $websiteUrl : null,
        ]);

        $pdo->commit();
        $success = 'Organization profile saved successfully.';
        $profile = fetch_org_profile($pdo, (int) $user['id']);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $exception->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Organization Profile - Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/lawable.css?v=3" />
  <style>
    body.profile-page { background: #FCF8F1 !important; }
    .profile-shell { width: min(980px, 100%) !important; margin: 0 auto !important; }
    .profile-form-wrap { display: flex !important; justify-content: center !important; width: 100% !important; }
    .profile-form { width: 100% !important; }
    .profile-card { width: 100%; max-width: 920px; margin: 0 auto; background: white; border: 1px solid #E5E0D8; border-radius: 28px; box-shadow: 0 4px 24px rgba(13,17,23,0.08); }
    .profile-card-header { display: flex; align-items: center; gap: 1rem; padding: 1.75rem 2rem 1.5rem; border-bottom: 1px solid rgba(229,224,216,0.9); }
    .profile-card-icon { width: 48px; height: 48px; display: inline-flex; align-items: center; justify-content: center; border-radius: 16px; background: rgba(201,147,58,0.14); color: #A8732A; }
    .profile-card-body { display: grid; gap: 1.75rem; padding: 1.75rem 2rem 2rem; }
    .profile-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; }
    .profile-field { display: grid; gap: 0.5rem; }
    .profile-field label { font-size: 0.95rem; font-weight: 600; color: #4B5563; }
    .profile-field input, .profile-field textarea { width: 100%; min-height: 44px; border: 1px solid #E5E0D8; border-radius: 16px; padding: 0.95rem 1rem; background: white; color: #0D1117; font-family: 'Inter', sans-serif; font-size: 0.95rem; transition: border-color .2s, box-shadow .2s; }
    .profile-field textarea { min-height: 140px; resize: vertical; }
    .profile-field input:focus, .profile-field textarea:focus { outline: none; border-color: #C9933A; box-shadow: 0 0 0 3px rgba(201,147,58,0.12); }
    .profile-field input[disabled], .profile-field input[readonly] { background: #F8F4EF; color: #6B7280; cursor: not-allowed; }
    .profile-field-full { grid-column: 1 / -1; }
    .profile-actions { display: flex; justify-content: flex-end; gap: 1rem; }
    .btn-ghost { border: 1px solid #E5E0D8; background: white; color: #0D1117; }
    .btn-primary { background: #A8732A; color: white; }
  </style>
</head>
<body class="profile-page">
<nav id="navbar">
  <a href="home.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="pages/offerings.php">Offerings</a></li>
    <li><a href="pages/courses.php">Courses</a></li>
    <li><a href="pages/about.php">About</a></li>
    <li><a href="pages/contact.php">Contact</a></li>
    <li class="nav-profile-item">
      <a href="edit-org-profile.php" class="nav-profile active" aria-label="Edit profile">
        <span aria-hidden="true">🏢</span>
      </a>
    </li>
    <li><a href="api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</nav>

<nav class="nav-drawer" id="drawer">
  <a href="pages/offerings.php" onclick="closeDrawer()">Offerings</a>
  <a href="pages/courses.php" onclick="closeDrawer()">Courses</a>
  <a href="pages/about.php" onclick="closeDrawer()">About</a>
  <a href="pages/contact.php" onclick="closeDrawer()">Contact</a>
  <a href="edit-org-profile.php" onclick="closeDrawer()">Edit profile</a>
  <a href="api/logout.php" class="drawer-cta">Log out</a>
</nav>

<main class="profile-shell">
  <section class="profile-form-wrap">
    <?php foreach ($errors as $error): ?>
      <div class="profile-alert profile-alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <?php if ($success !== ''): ?>
      <div class="profile-alert profile-alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form class="profile-form" method="post" action="edit-org-profile.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />

      <div class="profile-card">
        <div class="profile-card-header">
          <span class="profile-card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M3 9h18v10c0 1.1-.9 2-2 2H5c-1.1 0-2-.9-2-2V9z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M3 9l2-4c.4-.8 1.2-1 2-1h8c.8 0 1.6.2 2 1l2 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <div>
            <h1>Edit Organization Profile</h1>
          </div>
        </div>

        <div class="profile-card-body">
          <h2 class="profile-section-title">Basic Information</h2>

          <div class="profile-form-grid">
            <div class="profile-field profile-field-full">
              <label for="organization_name">Organization Name (Legal/Registered Name)</label>
              <input id="organization_name" name="organization_name" type="text" maxlength="255" required value="<?= e($profile['organization_name'] ?? '') ?>" />
            </div>

            <div class="profile-field profile-field-full">
              <label for="contact_person">Contact Person</label>
              <input id="contact_person" name="contact_person" type="text" maxlength="150" required value="<?= e($profile['contact_person'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="display_name">Display Name</label>
              <input id="display_name" name="display_name" type="text" maxlength="255" placeholder="e.g., Short brand name" value="<?= e($profile['display_name'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="organization_type">Organization Type</label>
              <select id="organization_type" name="organization_type">
                <option value="">Select organization type</option>
                <option value="Law Firm" <?= ($profile['organization_type'] ?? '') === 'Law Firm' ? 'selected' : '' ?>>Law Firm</option>
                <option value="Educational Institution" <?= ($profile['organization_type'] ?? '') === 'Educational Institution' ? 'selected' : '' ?>>Educational Institution</option>
                <option value="NGO" <?= ($profile['organization_type'] ?? '') === 'NGO' ? 'selected' : '' ?>>NGO</option>
                <option value="Corporate Legal Dept" <?= ($profile['organization_type'] ?? '') === 'Corporate Legal Dept' ? 'selected' : '' ?>>Corporate Legal Dept</option>
                <option value="Ed-tech" <?= ($profile['organization_type'] ?? '') === 'Ed-tech' ? 'selected' : '' ?>>Ed-tech</option>
                <option value="Government Body" <?= ($profile['organization_type'] ?? '') === 'Government Body' ? 'selected' : '' ?>>Government Body</option>
              </select>
            </div>

            <div class="profile-field">
              <label for="phone">Phone Number</label>
              <input id="phone" name="phone" type="tel" maxlength="30" value="<?= e($profile['phone'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="email">Email</label>
              <input id="email" type="email" disabled value="<?= e($profile['email'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="official_email">Official Email</label>
              <input id="official_email" name="official_email" type="email" maxlength="255" placeholder="Official company email" value="<?= e($profile['official_email'] ?? '') ?>" />
            </div>

            <div class="profile-field">
              <label for="year_established">Year Established</label>
              <input id="year_established" name="year_established" type="text" placeholder="e.g., 2020" maxlength="4" value="<?= e($profile['year_established'] ?? '') ?>" />
            </div>

            <div class="profile-field profile-field-full">
              <label for="tagline">Tagline/Short Description</label>
              <input id="tagline" name="tagline" type="text" maxlength="255" placeholder="One-liner describing your organization" value="<?= e($profile['tagline'] ?? '') ?>" />
            </div>

            <div class="profile-field profile-field-full">
              <label for="website_url">Website URL</label>
              <input id="website_url" name="website_url" type="url" maxlength="255" placeholder="https://example.com" value="<?= e($profile['website_url'] ?? '') ?>" />
            </div>

            <div class="profile-field profile-field-full">
              <label for="about_description">About / Detailed Description</label>
              <textarea id="about_description" name="about_description" maxlength="2000" rows="6" placeholder="Tell us about your organization, mission, focus areas, and what you do..."><?= e($profile['about_description'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="profile-actions">
            <a href="home.php" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-primary">Update</button>
          </div>
        </div>
      </div>
    </form>
  </section>
</main>

<script src="assets/js/script.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.profile-alert');
    alerts.forEach(function(alert) {
      if (alert.classList.contains('profile-alert-success')) {
        setTimeout(function() {
          alert.style.animation = 'slideUp 0.3s ease-out forwards';
          setTimeout(function() {
            alert.remove();
          }, 300);
        }, 3000);
      }
    });
  });
</script>
<style>
  @keyframes slideUp {
    from { opacity: 1; transform: translateX(-50%) translateY(0); }
    to { opacity: 0; transform: translateX(-50%) translateY(-20px); }
  }
</style>
</body>
</html>
