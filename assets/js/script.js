const navbar = document.getElementById('navbar');
const hamburger = document.getElementById('hamburger');
const drawer = document.getElementById('drawer');
const backTop = document.getElementById('backTop');
const cursorGlow = document.getElementById('cursorGlow');
const progressBar = document.getElementById('progressBar');

window.addEventListener('scroll', () => {
  if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 10);
  if (backTop) backTop.style.opacity = window.scrollY > 500 ? '1' : '0';
  if (progressBar) {
    const scrollTop = window.scrollY;
    const height = document.documentElement.scrollHeight - window.innerHeight;
    const progress = height > 0 ? (scrollTop / height) * 100 : 0;
    progressBar.style.width = `${progress}%`;
  }
});

if (hamburger && drawer) {
  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('open');
    drawer.classList.toggle('open');
  });
}

function closeDrawer() {
  if (hamburger) hamburger.classList.remove('open');
  if (drawer) drawer.classList.remove('open');
}

// FIX: use left/top (not transform) so it matches the CSS's
// `transform: translate(-50%, -50%)` centering on .cursor-glow,
// instead of overwriting it and offsetting the glow from the cursor.
window.addEventListener('mousemove', (e) => {
  if (cursorGlow) {
    cursorGlow.style.left = `${e.clientX}px`;
    cursorGlow.style.top = `${e.clientY}px`;
  }
});

const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) entry.target.classList.add('visible');
  });
}, { threshold: 0.10 });

document.querySelectorAll('.fade-up, .fade-left, .fade-right').forEach(el => observer.observe(el));

let countersStarted = false;
const counterObs = new IntersectionObserver((entries) => {
  if (countersStarted) return;
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      countersStarted = true;
      document.querySelectorAll('.counter').forEach(el => {
        const target = +el.dataset.target;
        const dur = 1400;
        const step = 16;
        const inc = target / (dur / step);
        let cur = 0;
        const timer = setInterval(() => {
          cur += inc;
          if (cur >= target) { cur = target; clearInterval(timer); }
          el.textContent = Math.floor(cur);
        }, step);
      });
    }
  });
}, { threshold: 0.3 });

const heroStats = document.querySelector('.hero-stats');
if (heroStats) counterObs.observe(heroStats);

const offeringsViewport = document.querySelector('.offerings-viewport');
const offeringsTrack = document.querySelector('.offerings-track');
const offeringCards = Array.from(document.querySelectorAll('.offering-card'));
const offeringPrev = document.querySelector('.offerings-prev');
const offeringNext = document.querySelector('.offerings-next');

function scrollOfferings(direction) {
  if (!offeringsViewport || !offeringCards.length) return;
  const cardWidth = offeringCards[0].getBoundingClientRect().width + 20;
  offeringsViewport.scrollBy({ left: direction * cardWidth, behavior: 'smooth' });
}

if (offeringPrev) offeringPrev.addEventListener('click', () => scrollOfferings(-1));
if (offeringNext) offeringNext.addEventListener('click', () => scrollOfferings(1));

let dragStartX = 0;
let isDragging = false;

if (offeringsViewport) {
  offeringsViewport.addEventListener('pointerdown', (e) => {
    dragStartX = e.pageX;
    isDragging = true;
    offeringsViewport.setPointerCapture(e.pointerId);
  });

  offeringsViewport.addEventListener('pointermove', (e) => {
    if (!isDragging) return;
    const deltaX = e.pageX - dragStartX;
    offeringsViewport.scrollLeft -= deltaX / 2;
    dragStartX = e.pageX;
  });

  offeringsViewport.addEventListener('pointerup', () => {
    isDragging = false;
  });

  offeringsViewport.addEventListener('pointerleave', () => {
    isDragging = false;
  });
}