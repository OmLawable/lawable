<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/firestore.php';

start_secure_session();
if (!is_logged_in()) {
    redirect('pages/login.php');
}

$user = current_user();
$is_org = ($user['role'] ?? '') === 'organization';
$is_teacher = ($user['role'] ?? '') === 'teacher';

if (!$is_org && !$is_teacher) {
    redirect('pages/dashboard.php');
}

$courseId = trim((string) ($_GET['id'] ?? ''));
if ($courseId === '') {
    redirect('pages/organization/manage-courses.php');
}

$db = get_firestore();
$course = $db->get('courses', $courseId);

if (!$course) {
    redirect('pages/organization/manage-courses.php');
}

// Verify ownership
$ownerId = $is_teacher ? ($course['teacherId'] ?? '') : ($course['organizationId'] ?? '');
if ($ownerId !== $user['id']) {
    redirect('pages/organization/manage-courses.php');
}

$errors = [];
$success = '';

// Get current lessons
$lessons = $course['lessons'] ?? [];
// Sort by sortOrder
usort($lessons, function($a, $b) {
    return ((int) ($a['sortOrder'] ?? 0)) <=> ((int) ($b['sortOrder'] ?? 0));
});

// Check if we are editing an existing lesson
$editingLessonId = trim((string) ($_GET['edit_lesson'] ?? ''));
$editingLesson = null;
if ($editingLessonId !== '') {
    foreach ($lessons as $l) {
        if (($l['id'] ?? '') === $editingLessonId) {
            $editingLesson = $l;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'add' || $action === 'edit') {
            $title       = trim((string) ($_POST['title'] ?? ''));
            $videoUrl    = trim((string) ($_POST['videoUrl'] ?? ''));
            $documentUrl = trim((string) ($_POST['documentUrl'] ?? ''));
            $duration    = (int) ($_POST['durationMinutes'] ?? 15);
            $sortOrder   = (int) ($_POST['sortOrder'] ?? 1);
            $content     = trim((string) ($_POST['content'] ?? ''));

            if ($title === '') {
                throw new RuntimeException('Lesson title is required.');
            }

            // Handle file upload
            $noteFile = '';
            if ($action === 'edit' && $editingLesson) {
                $noteFile = $editingLesson['noteFile'] ?? '';
            }

            // Remove notes file if checkbox is ticked
            if ($action === 'edit' && isset($_POST['removeNoteFile']) && $_POST['removeNoteFile'] === '1') {
                if ($noteFile !== '') {
                    $oldFilePath = __DIR__ . '/../../uploads/notes/' . $noteFile;
                    if (is_file($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                    $noteFile = '';
                }
            }

            if (isset($_FILES['noteFile']) && $_FILES['noteFile']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['noteFile']['tmp_name'];
                $fileName = $_FILES['noteFile']['name'];
                $fileSize = $_FILES['noteFile']['size'];
                
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                $allowedExtensions = ['pdf', 'doc', 'docx'];
                if (!in_array($fileExtension, $allowedExtensions, true)) {
                    throw new RuntimeException('Invalid file type. Only PDF, DOC, and DOCX files are allowed.');
                }

                if ($fileSize > 5 * 1024 * 1024) {
                    throw new RuntimeException('File size exceeds the 5MB limit.');
                }

                // Delete old notes file if replacing it
                if ($action === 'edit' && !empty($editingLesson['noteFile'])) {
                    $oldFilePath = __DIR__ . '/../../uploads/notes/' . $editingLesson['noteFile'];
                    if (is_file($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $uploadDir = __DIR__ . '/../../uploads/notes/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $newFileName = 'notes_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;

                if (!move_uploaded_file($fileTmpPath, $dest_path)) {
                    throw new RuntimeException('Failed to save the uploaded note file.');
                }

                $noteFile = $newFileName;
            }

            if ($action === 'add') {
                $newLessonId = 'lesson_' . bin2hex(random_bytes(6));
                $lessons[] = [
                    'id'              => $newLessonId,
                    'title'           => $title,
                    'videoUrl'        => $videoUrl,
                    'documentUrl'     => $documentUrl,
                    'noteFile'        => $noteFile,
                    'durationMinutes' => $duration,
                    'sortOrder'       => $sortOrder,
                    'content'         => $content
                ];
                $success = 'Lesson added successfully!';
            } else {
                $lessonFound = false;
                foreach ($lessons as &$l) {
                    if (($l['id'] ?? '') === $editingLessonId) {
                        $l['title']           = $title;
                        $l['videoUrl']        = $videoUrl;
                        $l['documentUrl']     = $documentUrl;
                        $l['noteFile']        = $noteFile;
                        $l['durationMinutes'] = $duration;
                        $l['sortOrder']       = $sortOrder;
                        $l['content']         = $content;
                        $lessonFound = true;
                        break;
                    }
                }
                unset($l);
                if (!$lessonFound) {
                    throw new RuntimeException('Lesson not found.');
                }
                $success = 'Lesson updated successfully!';
            }
        } elseif ($action === 'delete') {
            $deleteLessonId = trim((string) ($_POST['delete_lesson_id'] ?? ''));
            $initialCount = count($lessons);
            $lessons = array_filter($lessons, function($l) use ($deleteLessonId) {
                return ($l['id'] ?? '') !== $deleteLessonId;
            });
            if (count($lessons) === $initialCount) {
                throw new RuntimeException('Lesson to delete was not found.');
            }
            $success = 'Lesson deleted successfully!';
        }

        // Sort lessons again
        usort($lessons, function($a, $b) {
            return ((int) ($a['sortOrder'] ?? 0)) <=> ((int) ($b['sortOrder'] ?? 0));
        });

        // Save back to Firestore
        $course['lessons'] = array_values($lessons);
        $course['totalLessons'] = count($lessons);
        $course['updatedAt'] = date('c');

        $db->set('courses', $course, $courseId);

        // Redirect to clear post data
        $redirectUrl = 'manage-lessons.php?id=' . urlencode($courseId);
        if ($success !== '') {
            $redirectUrl .= '&success=' . urlencode($success);
        }
        header('Location: ' . $redirectUrl);
        exit();

    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

// Load success message from redirect query
$successRedirect = trim((string) ($_GET['success'] ?? ''));
if ($successRedirect !== '') {
    $success = $successRedirect;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Lessons — Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../assets/css/lawable.css" />
  <style>
    :root {
      --gold: #C9933A;
      --gold-dk: #A8732A;
      --gold-lt: #F4E4C3;
      --cream: #FCF8F1;
      --page-bg: #FCF8F1;
      --white: #FFFFFF;
      --ink: #0D1117;
      --ink-mid: #374151;
      --ink-soft: #6B7280;
      --border: #E5E0D8;
    }
    body.dark-theme {
      --gold: #D8A84F;
      --gold-dk: #F0C56D;
      --gold-lt: #3A3022;
      --cream: #111827;
      --page-bg: #0F172A;
      --white: #1E293B;
      --ink: #F8FAFC;
      --ink-mid: #CBD5E1;
      --ink-soft: #94A3B8;
      --border: #334155;
    }

    /* Premium layout design */
    .lessons-layout {
      display: grid;
      grid-template-columns: 1.2fr 1fr;
      gap: 2.5rem;
      align-items: start;
    }
    @media (max-width: 992px) {
      .lessons-layout {
        grid-template-columns: 1fr;
      }
    }
    .lesson-item-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 1.25rem;
      margin-bottom: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.2s;
    }
    .lesson-item-card:hover {
      box-shadow: 0 4px 15px rgba(13,17,23,0.05);
      border-color: var(--gold);
    }
    .alert {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      font-size: 0.88rem;
    }
    .alert-error {
      background: #FEE2E2;
      color: #991B1B;
    }
    .alert-success {
      background: #DCFCE7;
      color: #166534;
    }
    .btn-pill { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.75rem; font-size: 0.9rem; font-weight: 600; border-radius: 9999px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease-in-out; text-decoration: none; min-width: 110px; }
    .btn-pill-primary { background: #A8732A; color: white; }
    .btn-pill-primary:hover { background: #8E5E1E; transform: translateY(-1px); }
    .btn-pill-outline { background: transparent; border-color: #E5E0D8; color: #4B5563; }
    .btn-pill-outline:hover { background: #F9F8F6; border-color: #C9933A; color: #A8732A; }
  </style>
</head>
<body class="profile-page">
<div class="cursor-glow" id="cursorGlow"></div>
<div class="progress-bar" id="progressBar"></div>

<nav id="navbar">
  <a href="../dashboard.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="../dashboard.php">Dashboard</a></li>
    <li><a href="manage-courses.php" class="active">Manage Courses</a></li>
    <li><a href="edit-profile.php">Profile</a></li>
    <li><a href="../../api/logout.php" class="nav-cta">Log out</a></li>
  </ul>
</nav>

<main class="profile-shell" style="margin-top: 5rem; padding: 2rem 1.25rem;">
  <h1 class="profile-header-title">Lessons: <?= e($course['title']) ?></h1>
  
  <?php if ($success !== ''): ?>
    <div class="alert alert-success">✓ <?= e($success) ?></div>
  <?php endif; ?>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error">✕ <?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="lessons-layout">
    
    <!-- Left Panel: Lessons List -->
    <div class="lessons-list-panel">
      <h2 style="font-family:'Playfair Display', serif; font-size:1.3rem; margin-bottom:1.5rem; color:var(--ink);">📖 Current Lessons (<?= count($lessons) ?>)</h2>
      
      <?php if (empty($lessons)): ?>
        <div style="background:white; border:1px dashed #E5E0D8; padding:3rem; border-radius:16px; text-align:center; color:var(--ink-soft);">
          <div style="font-size:2rem; margin-bottom:0.5rem;">📁</div>
          <h3>No lessons added yet</h3>
          <p style="font-size:0.85rem; margin-top:0.25rem;">Use the form to add your first lecture module.</p>
        </div>
      <?php else: ?>
        <?php foreach ($lessons as $l): ?>
          <div class="lesson-item-card">
            <div>
              <div style="font-size:0.8rem; font-weight:700; color:var(--gold); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem;">
                Order #<?= (int) ($l['sortOrder'] ?? 1) ?> • <?= (int) ($l['durationMinutes'] ?? 15) ?> mins
              </div>
              <h4 style="font-size:1rem; font-weight:600; color:var(--ink);"><?= e($l['title']) ?></h4>
              <div style="display:flex; flex-direction:column; gap:0.15rem; margin-top:0.25rem;">
                <?php if (!empty($l['videoUrl'])): ?>
                  <div style="font-size:0.75rem; color:#2563EB; display:flex; align-items:center; gap:0.25rem;">
                    🎥 Video lecture linked
                  </div>
                <?php endif; ?>
                <?php if (!empty($l['noteFile'])): ?>
                  <div style="font-size:0.75rem; color:#16A34A; display:flex; align-items:center; gap:0.25rem;">
                    📄 PDF/Word notes attached
                  </div>
                <?php endif; ?>
                <?php if (!empty($l['documentUrl'])): ?>
                  <div style="font-size:0.75rem; color:#A8732A; display:flex; align-items:center; gap:0.25rem;">
                    🔗 External document linked
                  </div>
                <?php endif; ?>
              </div>
            </div>
            
            <div style="display:flex; gap:0.5rem; align-items:center;">
              <a href="manage-lessons.php?id=<?= urlencode($courseId) ?>&edit_lesson=<?= urlencode($l['id']) ?>" class="btn-pill btn-pill-outline" style="padding:0.35rem 0.75rem; font-size:0.75rem; min-width:auto;">Edit</a>
              <form method="POST" action="manage-lessons.php?id=<?= urlencode($courseId) ?>" onsubmit="return confirm('Are you sure you want to delete this lesson?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="delete_lesson_id" value="<?= e($l['id']) ?>" />
                <button type="submit" class="btn-pill btn-pill-outline" style="padding:0.35rem 0.75rem; font-size:0.75rem; min-width:auto; color:#DC2626; border-color:#FCA5A5;">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Right Panel: Add/Edit Form -->
    <div style="background:var(--white); border:1px solid var(--border); padding:2rem; border-radius:24px; box-shadow:0 4px 24px rgba(13,17,23,0.04);">
      <h2 style="font-family:'Playfair Display', serif; font-size:1.3rem; margin-bottom:1.5rem; color:var(--ink);">
        <?= $editingLesson ? '✏️ Edit Lesson' : '➕ Add New Lesson' ?>
      </h2>
      
      <form method="POST" action="manage-lessons.php?id=<?= urlencode($courseId) ?><?= $editingLesson ? '&edit_lesson=' . urlencode($editingLesson['id']) : '' ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="<?= $editingLesson ? 'edit' : 'add' ?>" />

        <div style="display:flex; flex-direction:column; gap:1.5rem;">
          <div class="profile-field">
            <label for="title">Lesson Title *</label>
            <input type="text" id="title" name="title" value="<?= e($editingLesson['title'] ?? '') ?>" required placeholder="e.g. Introduction to Contract Law" />
          </div>

          <div class="profile-field">
            <label for="videoUrl">Video Lecture URL (Optional)</label>
            <input type="url" id="videoUrl" name="videoUrl" value="<?= e($editingLesson['videoUrl'] ?? '') ?>" placeholder="https://youtube.com/... or direct MP4 link" />
          </div>

          <div class="profile-field">
            <label for="noteFile">Upload Study Notes / PDF (Optional)</label>
            <input type="file" id="noteFile" name="noteFile" accept=".pdf,.doc,.docx" />
            <p style="color:var(--ink-soft);font-size:0.75rem;margin-top:0.15rem;">Max size: 5MB (PDF, DOC, DOCX).</p>
            <?php if (!empty($editingLesson['noteFile'])): ?>
              <div style="font-size:0.8rem; color:var(--ink-soft); margin-top:0.25rem; display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                <span>📎 Attached notes file: <a href="../../uploads/notes/<?= e($editingLesson['noteFile']) ?>" target="_blank" style="color:var(--gold); font-weight:600; text-decoration:none;"><?= e($editingLesson['noteFile']) ?></a></span>
                <label style="display:inline-flex; align-items:center; gap:0.25rem; font-size:0.75rem; color:#DC2626; cursor:pointer; margin-left:0.5rem; font-weight:500;">
                  <input type="checkbox" name="removeNoteFile" value="1" /> Remove attached file
                </label>
              </div>
            <?php endif; ?>
          </div>

          <div class="profile-field">
            <label for="documentUrl">Or External Document / slides URL (Optional)</label>
            <input type="url" id="documentUrl" name="documentUrl" value="<?= e($editingLesson['documentUrl'] ?? '') ?>" placeholder="https://drive.google.com/... or slides link" />
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="profile-field">
              <label for="durationMinutes">Duration (Minutes)</label>
              <input type="number" id="durationMinutes" name="durationMinutes" min="1" value="<?= (int) ($editingLesson['durationMinutes'] ?? 15) ?>" required />
            </div>

            <div class="profile-field">
              <label for="sortOrder">Sort Order</label>
              <input type="number" id="sortOrder" name="sortOrder" min="1" value="<?= (int) ($editingLesson['sortOrder'] ?? (count($lessons) + 1)) ?>" required />
            </div>
          </div>

          <div class="profile-field">
            <label for="content">Lesson Body / Study Material *</label>
            <textarea id="content" name="content" rows="10" placeholder="Type or paste the lecture details, study text, legal cases, and reading materials here..." required style="min-height: 250px;"><?= e($editingLesson['content'] ?? '') ?></textarea>
          </div>

          <div style="display:flex; gap:1rem; margin-top:1rem;">
            <?php if ($editingLesson): ?>
              <a href="manage-lessons.php?id=<?= urlencode($courseId) ?>" class="btn-pill btn-pill-outline" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; flex:1;">Cancel Edit</a>
            <?php endif; ?>
            <button type="submit" class="btn-pill btn-pill-primary" style="flex:1;">
              <?= $editingLesson ? 'Save Lesson' : 'Add Lesson' ?>
            </button>
          </div>
        </div>
      </form>
    </div>

  </div>
</main>

<script src="../../assets/js/script.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    flatpickr('#date_of_birth', {
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'F j, Y',
      maxDate: 'today',
      disableMobile: true
    });
  });
</script>
</body>
</html>
