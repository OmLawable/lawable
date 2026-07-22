<?php
declare(strict_types=1);
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/firestore.php";
start_secure_session();

$user       = current_user();
$isLoggedIn = $user !== null;
$isStudent  = $isLoggedIn && ($user["role"] ?? "") === "user";
$isAdmin    = $isLoggedIn && ($user["role"] ?? "") === "admin";

$db       = get_firestore();
$courseId = trim($_GET["id"] ?? "");
if ($courseId === "") { header("Location: courses.php"); exit; }

$course = $db->get("courses", $courseId);
if (!$course || ($course["status"] ?? "") !== "published") {
    header("Location: courses.php"); exit;
}

$lessons = $course["lessons"] ?? [];
if (empty($lessons)) {
    $lessons = $db->query("lessons", [["courseId", "EQUAL", $courseId]], 50);
}
usort($lessons, fn($a,$b) => ((int)($a["sortOrder"]??$a["order"]??0)) <=> ((int)($b["sortOrder"]??$b["order"]??0)));

$enrolled = false;
if ($isStudent) {
    $enrollmentId = $user["id"] . "_" . $courseId;
    $enrollment   = $db->get("enrollments", $enrollmentId);
    $enrolled     = (bool)$enrollment;
}

$title       = $course["title"]       ?? "";
$description = $course["description"] ?? "";
$price       = (float)($course["price"] ?? 0);
$diff        = $course["difficulty"]  ?? "all-levels";
$cat         = $course["category"]    ?? "General";
$rating      = (float)($course["rating"] ?? 4.5);
$orgName     = $course["organizationName"] ?? $course["teacherName"] ?? "Lawable";
$totalMins   = array_sum(array_column($lessons, "durationMinutes"));
$hours       = (int)floor($totalMins / 60);
$mins        = $totalMins % 60;
$duration    = ($hours > 0 ? "{$hours}h " : "") . ($mins > 0 ? "{$mins}m" : "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?php echo htmlspecialchars($title); ?> &mdash; Lawable</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../assets/css/lawable.css?v=1.4"/>
  <style>
    :root{--gold:#C9933A;--gold-dk:#A8732A;--gold-lt:#F4E4C3;--cream:#FCF8F1;--page-bg:#F6EEF6;--white:#FFFFFF;--ink:#0D1117;--ink-mid:#374151;--ink-soft:#6B7280;--border:#E5E0D8;--green:#16a34a;--green-bg:#DCFCE7;--nav-h:68px;--radius:12px;--radius-lg:16px;--shadow:0 4px 24px rgba(13,17,23,.08);--shadow-lg:0 12px 40px rgba(13,17,23,.12);}
    body.dark-theme{--gold:#D8A84F;--gold-dk:#F0C56D;--gold-lt:#3A3022;--cream:#111827;--page-bg:#0F172A;--white:#1E293B;--ink:#F8FAFC;--ink-mid:#CBD5E1;--ink-soft:#94A3B8;--border:#334155;--green:#22C55E;--green-bg:#064E3B;--shadow:0 4px 24px rgba(0,0,0,.40);--shadow-lg:0 12px 40px rgba(0,0,0,.50);}
    body{background:var(--page-bg);font-family:Inter,sans-serif;color:var(--ink);min-height:100vh;}
    .course-detail-page{padding-top:calc(var(--nav-h) + 16px);}
    /* Hero */
    .cd-hero{background:linear-gradient(135deg,#1a0e05 0%,#2d1a08 50%,#1a1005 100%);padding:3rem 2rem 2.5rem;position:relative;overflow:hidden;}
    .cd-hero::before{content:"";position:absolute;inset:0;background:url("data:image/svg+xml,%3Csvg width=60 height=60 viewBox=0 0 60 60 xmlns=http://www.w3.org/2000/svg%3E%3Cg fill=none fill-rule=evenodd%3E%3Cg fill=%23C9933A fill-opacity=0.05%3E%3Cpath d=M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");opacity:0.4;}
    .cd-hero-inner{max-width:900px;margin:0 auto;position:relative;z-index:1;}
    .cd-breadcrumb{font-size:.78rem;color:rgba(255,255,255,.55);margin-bottom:1rem;}
    .cd-breadcrumb a{color:rgba(255,255,255,.55);text-decoration:none;}
    .cd-breadcrumb a:hover{color:var(--gold);}
    .cd-breadcrumb span{margin:0 .4rem;}
    .cd-cat-badge{display:inline-block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;padding:.25rem .85rem;border-radius:20px;background:rgba(201,147,58,.2);color:var(--gold);border:1px solid rgba(201,147,58,.4);margin-bottom:.9rem;}
    .cd-title{font-family:"Playfair Display",serif;font-size:clamp(1.65rem,3.5vw,2.6rem);font-weight:700;color:#FFFFFF;line-height:1.2;margin-bottom:.75rem;}
    .cd-desc{font-size:.95rem;color:rgba(255,255,255,.7);line-height:1.7;max-width:700px;margin-bottom:1.5rem;}
    .cd-meta-row{display:flex;flex-wrap:wrap;gap:1.25rem;align-items:center;margin-bottom:1.75rem;}
    .cd-meta-item{display:flex;align-items:center;gap:.4rem;font-size:.82rem;color:rgba(255,255,255,.65);}
    .cd-meta-item strong{color:#FFFFFF;}
    .cd-stars{color:#F59E0B;letter-spacing:.05em;}
    /* CTA Box */
    .cd-cta-box{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:var(--radius-lg);padding:1.5rem;display:flex;align-items:center;flex-wrap:wrap;gap:1rem;justify-content:space-between;backdrop-filter:blur(8px);}
    .cd-price{font-family:"Playfair Display",serif;font-size:2rem;font-weight:700;color:#FFFFFF;}
    .cd-price-free{font-size:1.3rem;font-weight:700;color:#4ade80;}
    .cd-enroll-btn{display:inline-flex;align-items:center;gap:.5rem;padding:.85rem 2rem;border-radius:9999px;font-size:.95rem;font-weight:700;font-family:Inter,sans-serif;text-decoration:none;transition:all .25s;border:none;cursor:pointer;}
    .cd-enroll-btn.primary{background:var(--gold);color:#1a0e05;}
    .cd-enroll-btn.primary:hover{background:#D8A84F;transform:translateY(-1px);box-shadow:0 8px 24px rgba(201,147,58,.4);}
    .cd-enroll-btn.enrolled{background:#4ade80;color:#052e16;}
    .cd-enroll-btn.enrolled:hover{background:#22c55e;transform:translateY(-1px);}
    /* Layout below hero */
    .cd-body{max-width:900px;margin:2rem auto;padding:0 1.5rem;}
    /* Tabs */
    .cd-tabs{display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:2rem;}
    .cd-tab{font-size:.9rem;font-weight:600;padding:.75rem 1.5rem;border:none;background:transparent;color:var(--ink-soft);cursor:pointer;font-family:Inter,sans-serif;border-bottom:3px solid transparent;margin-bottom:-2px;transition:color .2s,border-color .2s;}
    .cd-tab:hover{color:var(--ink);}
    .cd-tab.active{color:var(--gold);border-bottom-color:var(--gold);}
    .cd-tab-panel{display:none;}
    .cd-tab-panel.active{display:block;}
    /* Modules list */
    .modules-header{font-family:"Playfair Display",serif;font-size:1.3rem;font-weight:700;color:var(--ink);margin:0 0 1.25rem;}
    .module-list{display:flex;flex-direction:column;gap:.75rem;}
    .module-item{background:var(--white);border-radius:var(--radius);border:1px solid var(--border);padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;box-shadow:var(--shadow);transition:transform .2s,box-shadow .2s;}
    .module-item:hover{transform:translateY(-1px);box-shadow:var(--shadow-lg);}
    .module-num{width:34px;height:34px;border-radius:50%;background:var(--gold-lt);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:var(--gold-dk);flex-shrink:0;}
    .module-info{flex:1;min-width:0;}
    .module-title{font-weight:600;color:var(--ink);font-size:.9rem;margin-bottom:.2rem;}
    .module-dur{font-size:.75rem;color:var(--ink-soft);}
    .module-lock{font-size:1rem;color:var(--ink-soft);}
    .module-lock.unlocked{color:var(--gold);}
    /* About tab */
    .about-section{margin-bottom:2rem;}
    .about-section h3{font-family:"Playfair Display",serif;font-size:1.1rem;font-weight:700;color:var(--ink);margin-bottom:.85rem;}
    .about-section p{font-size:.9rem;color:var(--ink-mid);line-height:1.8;}
    .what-learn-list{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.6rem;}
    .what-learn-list li{font-size:.875rem;color:var(--ink-mid);display:flex;align-items:flex-start;gap:.6rem;line-height:1.5;}
    .what-learn-list li::before{content:"\\2713";color:var(--green);font-weight:700;flex-shrink:0;margin-top:.05rem;}
    .instructor-card{display:flex;align-items:center;gap:1rem;background:var(--white);border-radius:var(--radius);border:1px solid var(--border);padding:1.25rem;box-shadow:var(--shadow);}
    .instructor-avatar{width:52px;height:52px;border-radius:50%;background:var(--gold-lt);display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:700;color:var(--gold-dk);flex-shrink:0;}
    .instructor-name{font-weight:700;color:var(--ink);font-size:.95rem;}
    .instructor-role{font-size:.8rem;color:var(--ink-soft);margin-top:.15rem;}
    /* Toast */
    #toast{position:fixed;top:1.5rem;right:1.5rem;max-width:360px;padding:1rem 1.25rem;border-radius:14px;background:var(--white);box-shadow:var(--shadow-lg);border-left:4px solid var(--gold);font-size:.88rem;z-index:9999;display:none;font-family:Inter,sans-serif;}
    #toast.success{border-left-color:#4A7C59;}#toast.error{border-left-color:#C0604A;}
    .enroll-loading{opacity:.6;pointer-events:none;}
    @media(max-width:600px){.cd-hero{padding:2rem 1.25rem 2rem;}.cd-cta-box{flex-direction:column;align-items:flex-start;}.cd-body{padding:0 1rem;}}
  </style>
</head>
<body>
<div id="toast"></div>
<nav id="navbar" class="scrolled">
  <a href="../index.php" class="nav-logo">Law<span>able</span></a>
  <ul class="nav-links">
    <li><a href="offerings.php">Offerings</a></li>
    <li class="nav-dropdown">
      <a href="courses.php" class="nav-dropdown-toggle active">
        Courses <span class="nav-dropdown-chevron">▼</span>
      </a>
      <div class="nav-dropdown-menu">
        <a href="courses.php">Explore Courses</a>
        <a href="my-learnings.php">My Learnings</a>
      </div>
    </li>
    <li><a href="about.php">About</a></li>
    <li><a href="contact.php">Contact</a></li>
    <?php if ($isLoggedIn): ?>
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="../api/logout.php" class="nav-cta">Log out</a></li>
    <?php else: ?>
    <li><a href="login.php" class="nav-cta">Log in &rarr;</a></li>
    <?php endif; ?>
    <li><button class="theme-toggle" type="button" data-theme-toggle aria-label="Toggle dark theme" aria-pressed="false"><span class="theme-toggle-icon" aria-hidden="true">D</span><span class="theme-toggle-text">Dark</span></button></li>
  </ul>
  <button class="nav-hamburger" id="hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
</nav>
<nav class="nav-drawer" id="drawer">
  <a href="offerings.php">Offerings</a>
  <a href="courses.php">Explore Courses</a>
  <a href="my-learnings.php">My Learnings</a>
  <a href="about.php">About</a>
  <a href="contact.php">Contact</a>
  <?php if ($isLoggedIn): ?>
  <a href="dashboard.php">Dashboard</a>
  <a href="../api/logout.php" class="drawer-cta">Log out</a>
  <?php else: ?>
  <a href="login.php" class="drawer-cta">Log in &rarr;</a>
  <?php endif; ?>
  <button class="theme-toggle drawer-theme-toggle" type="button" data-theme-toggle aria-pressed="false"><span class="theme-toggle-icon" aria-hidden="true">D</span><span class="theme-toggle-text">Dark theme</span></button>
</nav>

<div class="course-detail-page">

  <!-- HERO -->
  <div class="cd-hero">
    <div class="cd-hero-inner">
      <div class="cd-breadcrumb">
        <a href="courses.php">Courses</a><span>&rsaquo;</span>
        <a href="courses.php"><?php echo htmlspecialchars($cat); ?></a><span>&rsaquo;</span>
        <?php echo htmlspecialchars(mb_substr($title,0,40)); ?>
      </div>
      <div class="cd-cat-badge"><?php echo htmlspecialchars($cat); ?></div>
      <h1 class="cd-title"><?php echo htmlspecialchars($title); ?></h1>
      <p class="cd-desc"><?php echo htmlspecialchars($description); ?></p>
      <div class="cd-meta-row">
        <div class="cd-meta-item">
          <span class="cd-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
          <strong><?php echo number_format($rating,1); ?></strong>
        </div>
        <div class="cd-meta-item">
          <strong><?php echo count($lessons); ?></strong>&nbsp;Modules
        </div>
        <?php if ($duration): ?>
        <div class="cd-meta-item">
          <strong><?php echo htmlspecialchars($duration); ?></strong>&nbsp;Total Duration
        </div>
        <?php endif; ?>
        <div class="cd-meta-item">
          By <strong>&nbsp;<?php echo htmlspecialchars($orgName); ?></strong>
        </div>
      </div>
      <div class="cd-cta-box">
        <div>
          <?php if ($price <= 0): ?>
            <div class="cd-price-free">Free Enrollment</div>
          <?php else: ?>
            <div class="cd-price">&₹<?php echo number_format($price); ?></div>
          <?php endif; ?>
        </div>
        <div>
          <?php if ($enrolled): ?>
            <a href="student/course-workspace.php?course_id=<?php echo urlencode($courseId); ?>" class="cd-enroll-btn enrolled">
              &#9654; Continue Learning
            </a>
          <?php elseif ($isStudent): ?>
            <button class="cd-enroll-btn primary" id="enrollBtn" onclick="enrollCourse()">
              Enroll Now &rarr;
            </button>
          <?php elseif ($isLoggedIn): ?>
            <a href="courses.php" class="cd-enroll-btn primary">Browse Courses</a>
          <?php else: ?>
            <a href="login.php?redirect=<?php echo urlencode("pages/course-detail.php?id={$courseId}"); ?>" class="cd-enroll-btn primary">
              Log in to Enroll &rarr;
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- BODY -->
  <div class="cd-body">
    <div class="cd-tabs">
      <button class="cd-tab active" onclick="switchTab(this,&apos;modules&apos;)" id="tab-modules">Course Modules</button>
      <button class="cd-tab" onclick="switchTab(this,&apos;about&apos;)" id="tab-about">About</button>
    </div>

    <!-- Modules Panel -->
    <div class="cd-tab-panel active" id="panel-modules">
      <p class="modules-header"><?php echo count($lessons); ?> Module<?php echo count($lessons)!==1?"s":""; ?></p>
      <?php if (empty($lessons)): ?>
        <div style="text-align:center;padding:3rem 1rem;color:var(--ink-soft);">
          <div style="font-size:2.5rem;margin-bottom:.75rem;">&#128218;</div>
          <div>No modules available yet. Check back soon!</div>
        </div>
      <?php else: ?>
      <div class="module-list">
        <?php foreach ($lessons as $i => $lesson): ?>
        <div class="module-item">
          <div class="module-num"><?php echo $i+1; ?></div>
          <div class="module-info">
            <div class="module-title"><?php echo htmlspecialchars($lesson["title"] ?? "Module " . ($i+1)); ?></div>
            <div class="module-dur"><?php echo (int)($lesson["durationMinutes"] ?? 20); ?> min &middot; Text lesson</div>
          </div>
          <?php if ($enrolled): ?>
            <a href="student/course-workspace.php?course_id=<?php echo urlencode($courseId); ?>&lesson_id=<?php echo urlencode($lesson["id"]??""); ?>" title="Open lesson" style="text-decoration:none;font-size:1.1rem;color:var(--gold);" class="module-lock unlocked">&#9654;</a>
          <?php else: ?>
            <span class="module-lock" title="Enroll to access">&#128274;</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!$enrolled && !empty($lessons)): ?>
      <div style="margin-top:2rem;padding:1.25rem 1.5rem;background:var(--gold-lt);border-radius:var(--radius);border-left:4px solid var(--gold);font-size:.88rem;color:var(--ink-mid);">
        &#128274; Enroll in this course to access all <?php echo count($lessons); ?> modules and track your progress.
      </div>
      <?php endif; ?>
    </div>

    <!-- About Panel -->
    <div class="cd-tab-panel" id="panel-about">
      <div class="about-section">
        <h3>About this Course</h3>
        <p><?php echo htmlspecialchars($description ?: "This course provides comprehensive knowledge in " . $title . ", covering both foundational theory and practical applications."); ?></p>
      </div>
      <?php if (!empty($lessons)): ?>
      <div class="about-section">
        <h3>What You Will Learn</h3>
        <ul class="what-learn-list">
          <?php foreach (array_slice($lessons,0,8) as $l): ?>
          <li><?php echo htmlspecialchars($l["title"] ?? "Module"); ?></li>
          <?php endforeach; ?>
          <?php if (count($lessons) > 8): ?>
          <li>And <?php echo count($lessons)-8; ?> more modules...</li>
          <?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
      <div class="about-section">
        <h3>Instructor</h3>
        <div class="instructor-card">
          <div class="instructor-avatar"><?php echo strtoupper(substr($orgName,0,2)); ?></div>
          <div>
            <div class="instructor-name"><?php echo htmlspecialchars($orgName); ?></div>
            <div class="instructor-role">Lawable Certified Educator</div>
          </div>
        </div>
      </div>
      <div class="about-section">
        <h3>Course Details</h3>
        <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
          <tr><td style="padding:.5rem 0;color:var(--ink-soft);width:140px;">Level</td><td style="font-weight:600;color:var(--ink);"><?php echo ucfirst($diff); ?></td></tr>
          <tr><td style="padding:.5rem 0;color:var(--ink-soft);">Modules</td><td style="font-weight:600;color:var(--ink);"><?php echo count($lessons); ?></td></tr>
          <?php if ($duration): ?><tr><td style="padding:.5rem 0;color:var(--ink-soft);">Duration</td><td style="font-weight:600;color:var(--ink);"><?php echo htmlspecialchars($duration); ?></td></tr><?php endif; ?>
          <tr><td style="padding:.5rem 0;color:var(--ink-soft);">Category</td><td style="font-weight:600;color:var(--ink);"><?php echo htmlspecialchars($cat); ?></td></tr>
          <tr><td style="padding:.5rem 0;color:var(--ink-soft);">Price</td><td style="font-weight:600;color:var(--ink);"><?php echo $price <= 0 ? "Free" : "&#8377;" . number_format($price); ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/script.js"></script>
<script>
var COURSE_ID = <?php echo json_encode($courseId); ?>;
var IS_STUDENT = <?php echo $isStudent ? "true" : "false"; ?>;

function switchTab(btn, panel) {
  document.querySelectorAll(".cd-tab").forEach(function(t){ t.classList.remove("active"); });
  document.querySelectorAll(".cd-tab-panel").forEach(function(p){ p.classList.remove("active"); });
  btn.classList.add("active");
  document.getElementById("panel-" + panel).classList.add("active");
}

var toast = document.getElementById("toast");
function showToast(msg, type) {
  toast.textContent = msg; toast.className = type || "success"; toast.style.display = "block";
  clearTimeout(toast._t); toast._t = setTimeout(function(){ toast.style.display="none"; }, 4000);
}

function enrollCourse() {
  var btn = document.getElementById("enrollBtn");
  if (!btn || !IS_STUDENT) return;
  btn.classList.add("enroll-loading");
  btn.textContent = "Enrolling...";
  fetch("../api/enroll.php", {
    method: "POST",
    headers: {"Content-Type":"application/json"},
    body: JSON.stringify({courseId: COURSE_ID})
  }).then(function(r){ return r.json(); }).then(function(d){
    if (d.success) {
      showToast("Enrolled successfully! Redirecting...", "success");
      setTimeout(function(){ location.href = "student/course-workspace.php?course_id=" + encodeURIComponent(COURSE_ID); }, 1200);
    } else {
      showToast(d.message || "Enrollment failed.", "error");
      btn.classList.remove("enroll-loading");
      btn.textContent = "Enroll Now \u2192";
    }
  }).catch(function(){
    showToast("Network error. Please try again.", "error");
    btn.classList.remove("enroll-loading");
    btn.textContent = "Enroll Now \u2192";
  });
}
</script>
</body>
</html>