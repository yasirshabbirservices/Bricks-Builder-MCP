---
name: javascript
title: JavaScript Best Practices for Bricks Pages
description: Modern JS patterns, Bricks interactions vs custom JS, cross-browser compatibility, performance, event handling, DOM manipulation, and when to write custom code.
when_to_use: Any custom JavaScript, Bricks interactions setup, animation scripting, or third-party JS integration on a page.
---

## Bricks-First Rule — Use Native Interactions Before Writing JS

Before writing any custom JavaScript, check whether Bricks Builder's native tools can achieve the same result:

| What you need | Use this instead of JS |
|---|---|
| Show/hide on scroll | Bricks Interaction → "Scroll" trigger |
| Animate element on viewport entry | Bricks Interaction → "Enter viewport" |
| Hover effect / state change | CSS `:hover` or Bricks Interaction |
| Toggle a class on click | Bricks Interaction → click → toggle class |
| Sticky element | CSS `position: sticky` |
| Smooth scroll to anchor | CSS `scroll-behavior: smooth` |
| Accordion expand/collapse | Native Bricks `accordion` element |
| Tabs | Native Bricks `tabs` element |
| Slider/carousel | Native Bricks `slider` or `slider-nested` element |
| Modal / popup | Native Bricks `popup` element |
| Form submission | Bricks `form` element with built-in actions |
| Countdown timer | Native Bricks `countdown` element |

**Only write custom JavaScript when Bricks has no native equivalent.**

---

## Modern JavaScript — Always Use ES2020+

Write modern JavaScript. All major browsers (Chrome, Firefox, Safari, Edge) have supported ES2020+ for years.

### Variables — Always `const` and `let`

```js
// Always use const for values that don't reassign
const button = document.querySelector('.js-toggle');
const CONFIG = { speed: 300, easing: 'ease-out' };

// Use let only when the value will be reassigned
let count = 0;
let isOpen = false;

// Never use var — it has function scope and hoisting issues
```

### Destructuring

```js
// Object destructuring
const { speed, easing, delay = 0 } = CONFIG;

// Array destructuring
const [first, second, ...rest] = items;

// In function parameters
function animate({ element, duration = 300, easing = 'ease' }) {
  element.style.transition = `all ${duration}ms ${easing}`;
}
```

### Template Literals

```js
// Always use template literals for string composition
const message = `Hello, ${name}! You have ${count} messages.`;
const html = `
  <div class="card">
    <h3>${title}</h3>
    <p>${description}</p>
  </div>
`;
```

### Arrow Functions

```js
// Short, pure functions
const double = (n) => n * 2;
const sum = (a, b) => a + b;

// Callbacks
items.forEach((item) => item.classList.add('active'));
const filtered = items.filter((item) => item.dataset.category === category);
const mapped   = items.map((item) => item.textContent.trim());
```

### Optional Chaining (`?.`) and Nullish Coalescing (`??`)

```js
// Safe property access — no TypeError if element is null
const text   = document.querySelector('.hero-title')?.textContent;
const height = element?.getBoundingClientRect()?.height ?? 0;

// Default values
const label = config.label ?? 'Default Label';
const limit = options.limit ?? 10;
```

### Async/Await for All Asynchronous Code

```js
// Always use async/await — never raw .then() chains in application code
async function fetchPosts(url) {
  try {
    const response = await fetch(url, {
      method: 'GET',
      headers: { 'Content-Type': 'application/json' },
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return await response.json();
  } catch (error) {
    console.error('Fetch failed:', error);
    return null;
  }
}
```

---

## DOM Manipulation — Modern Patterns

### Element Selection

```js
// Prefer querySelector/querySelectorAll — CSS-selector based, flexible
const hero    = document.querySelector('.hero-section');
const buttons = document.querySelectorAll('[data-action]');
const form    = document.getElementById('contact-form'); // fine for IDs

// Convert NodeList to Array for array methods
const buttonArray = Array.from(buttons);
// or: [...buttons].forEach(...)
```

### Class Manipulation

```js
// classList API — always use instead of className string manipulation
element.classList.add('is-active');
element.classList.remove('is-hidden');
element.classList.toggle('is-open');
element.classList.replace('old-class', 'new-class');
const isActive = element.classList.contains('is-active');
```

### Attribute Access

```js
// data-* attributes via dataset
const postId   = element.dataset.postId;   // reads data-post-id
const category = element.dataset.category; // reads data-category

// Set with setCustomValidity for typed attributes; dataset for data-*
element.dataset.state = 'loading';
```

### DOM Insertion — Use insertAdjacentHTML for Performance

```js
// Faster than innerHTML = '' + innerHTML for inserting HTML strings
container.insertAdjacentHTML('beforeend', '<div class="item">New</div>');

// Create elements with createElement for complex structures
const card = document.createElement('div');
card.className = 'card';
card.textContent = title; // safe — no XSS risk unlike innerHTML
container.appendChild(card);

// Never build HTML from user-supplied data via innerHTML — XSS risk
// ❌ element.innerHTML = `<div>${userInput}</div>`
// ✅ element.textContent = userInput  (auto-escapes)
```

---

## Event Handling — Best Practices

### Event Delegation (Preferred)

Attach one listener to a stable parent instead of many listeners on children:

```js
// One listener handles all current AND future .card-link elements
document.querySelector('.card-grid')?.addEventListener('click', (event) => {
  const card = event.target.closest('.card-link');
  if (!card) return; // click was outside a card

  event.preventDefault();
  openDetail(card.dataset.id);
});
```

### Passive Listeners for Scroll/Touch

Always mark scroll and touch listeners as `{ passive: true }` — prevents jank:

```js
window.addEventListener('scroll', onScroll, { passive: true });
document.addEventListener('touchstart', onTouch, { passive: true });
```

### Debounce / Throttle for High-Frequency Events

```js
// Debounce — run after N ms of silence (use for: resize, input, search)
function debounce(fn, delay = 250) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

// Throttle — run at most once per N ms (use for: scroll, mousemove)
function throttle(fn, limit = 100) {
  let lastCall = 0;
  return (...args) => {
    const now = Date.now();
    if (now - lastCall >= limit) { lastCall = now; fn(...args); }
  };
}

window.addEventListener('resize', debounce(onResize, 200), { passive: true });
window.addEventListener('scroll', throttle(onScroll, 50),  { passive: true });
```

### Cleanup Listeners

Always clean up event listeners added to window/document when no longer needed:

```js
const controller = new AbortController();

window.addEventListener('keydown', handleKeyDown, { signal: controller.signal });
document.addEventListener('click', handleClick, { signal: controller.signal });

// Remove all at once
function cleanup() {
  controller.abort();
}
```

---

## Intersection Observer (Preferred Over Scroll Events)

For "run when element is visible" behaviour, use `IntersectionObserver` — it's performant and cross-browser safe:

```js
const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target); // stop watching after first trigger
      }
    });
  },
  { threshold: 0.1, rootMargin: '0px 0px -50px 0px' }
);

document.querySelectorAll('.animate-on-scroll').forEach((el) => observer.observe(el));
```

---

## Cross-Browser Compatibility

### Features Safe to Use Without Polyfills (2024)

- `const`, `let`, arrow functions, template literals, destructuring, spread, rest
- `async`/`await`, `Promise`
- `fetch` API
- `classList`, `dataset`, `closest()`
- `querySelector`, `querySelectorAll`
- `IntersectionObserver`, `ResizeObserver`, `MutationObserver`
- `requestAnimationFrame`
- Optional chaining (`?.`), nullish coalescing (`??`)
- `Array.from`, `Array.prototype.{map,filter,reduce,find,includes}`
- `Object.entries`, `Object.keys`, `Object.values`, `Object.assign`
- `CustomEvent`, `dispatchEvent`

### Feature Detection (Not Browser Detection)

```js
// Check for a feature, not a browser
if ('IntersectionObserver' in window) {
  // use IntersectionObserver
} else {
  // fallback: add class immediately
  document.querySelectorAll('.animate-on-scroll').forEach((el) =>
    el.classList.add('is-visible')
  );
}
```

---

## Performance Rules

### Batch DOM Reads Before Writes

```js
// Read all measurements first (triggers layout once)
const heights = Array.from(cards).map((c) => c.offsetHeight);

// Then write (another single layout pass)
cards.forEach((card, i) => card.style.minHeight = `${heights[i]}px`);
```

### Use `requestAnimationFrame` for Animations

```js
function animateSlide(element, targetX) {
  let currentX = 0;
  function step() {
    currentX += (targetX - currentX) * 0.1;
    element.style.transform = `translateX(${currentX}px)`;
    if (Math.abs(targetX - currentX) > 0.5) {
      requestAnimationFrame(step);
    }
  }
  requestAnimationFrame(step);
}
```

Prefer CSS transitions/animations over JS-driven animations for simple effects.

### Defer Non-Critical JavaScript

For custom scripts added to a Bricks `code` element, wrap in DOMContentLoaded:

```html
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Your code here — runs after HTML is parsed, before images load
});
</script>
```

For scripts that interact with images or third-party embeds, use `window.load`:
```js
window.addEventListener('load', () => { /* ... */ });
```

---

## Security

- **Never use `innerHTML` with user-supplied data** — use `textContent` or `innerText`
- **Sanitize URLs** before using in `href`, `src`, or `window.location`
- **Avoid `eval()`** and `new Function()` entirely
- **Use Content-Security-Policy** headers where possible (server config)
- **Validate data on the server** — client-side validation is UX only, never security

```js
// Safe text insertion
element.textContent = userInput;  // ✅ auto-escaped

// Dangerous — never do this with user input
element.innerHTML = userInput;    // ❌ XSS risk
```

---

## WordPress / Bricks Context

### Add Scripts to a Page via Bricks Code Element

```html
<script>
// Wrap in IIFE to avoid polluting global scope
(function () {
  'use strict';
  const slider = document.querySelector('.js-custom-slider');
  if (!slider) return;
  // ... init code
})();
</script>
```

### Access WordPress Data

```js
// nonce, ajaxurl, and custom data passed via wp_localize_script:
const { ajaxurl, nonce, postId } = window.bmcp_data ?? {};
```

### AJAX Calls to WordPress

```js
async function wpAjax(action, data = {}) {
  const body = new URLSearchParams({ action, nonce, ...data });
  const res  = await fetch(ajaxurl, { method: 'POST', body });
  return res.json();
}
```

---

## Quick Checklist Before Writing JS

- [ ] Can this be done with Bricks interactions, native elements, or CSS alone?
- [ ] Using `const`/`let` — never `var`
- [ ] Async operations use `async`/`await` with try/catch
- [ ] Event delegation used instead of per-element listeners
- [ ] Scroll/resize listeners are passive and debounced/throttled
- [ ] `IntersectionObserver` used for scroll-triggered effects
- [ ] No `innerHTML` with user/dynamic data — using `textContent`
- [ ] Cross-browser feature checked (caniuse.com) if using newer APIs
- [ ] Code wrapped in IIFE or module to avoid global scope pollution
