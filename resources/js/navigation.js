/**
 * SuperBie — Progressive GET Navigation + Intelligent In-Memory Page Cache.
 *
 * Prompt 26. This is a PROGRESSIVE ENHANCEMENT layer, not an SPA engine:
 *   - If this module fails to load or throws, every link is a normal browser
 *     navigation and the application is unchanged (Laravel is the source of
 *     truth; Blade still renders complete HTML; authorization stays server-side).
 *
 * Security model (see PROMPT_25_SPA_LIKE_NAVIGATION_DESIGN.md + Prompt 26 report):
 *   - Only same-origin GET <a> clicks are intercepted. Mutations
 *     (POST/PUT/PATCH/DELETE), logout, downloads and auth pages are NEVER
 *     intercepted -> Laravel's PRG handling performs a real browser navigation,
 *     which also discards this in-memory cache.
 *   - The cache is a per-tab `Map`. It never uses localStorage, sessionStorage,
 *     CacheStorage, IndexedDB or a Service Worker.
 *   - Every entry is namespaced by a SERVER-DERIVED identity hash
 *     (meta[name="navigation-identity"] = HMAC(user id | role | dinas_unit_id)).
 *     If no identity is present, caching is disabled entirely.
 *   - Pages containing mutation forms are never cached.
 *   - Auth redirects, non-HTML and non-2xx responses are never cached.
 *   - No stale-while-revalidate: a page is either served fresh from the network
 *     or from a non-expired cache entry.
 */

const CONFIG = {
    cacheTTL: 60_000, // 60 seconds
    maxCacheEntries: 10,
    maxPrefetchConcurrency: 2,
    requestTimeout: 15_000,
};

const AUTH_PATHS = ['/login', '/register', '/forgot-password', '/reset-password'];
const NON_HTML_EXT = /\.(pdf|xlsx?|csv|zip|rar|docx?|pptx?|png|jpe?g|gif|webp|svg|mp4|mp3|txt)$/i;

const DEBUG = Boolean(import.meta && import.meta.env && import.meta.env.DEV);
const log = (...args) => { if (DEBUG) console.debug('[nav]', ...args); };

const pageCache = new Map();
let currentIdentity = null;
let inFlight = null;
let initialized = false;

const prefetchQueue = [];
const prefetchInFlight = new Set();
let prefetchActive = 0;
let progressHideTimer = null;

/* ─────────────────────────────── identity ─────────────────────────────── */

function readIdentity(root) {
    const meta = (root || document).querySelector('meta[name="navigation-identity"]');
    return meta ? (meta.getAttribute('content') || null) : null;
}

/* ───────────────────────────────── URLs ───────────────────────────────── */

function toUrl(href) {
    try {
        return new URL(href, window.location.href);
    } catch {
        return null;
    }
}

function isAuthPath(pathname) {
    return AUTH_PATHS.some((p) => pathname === p || pathname.startsWith(p + '/'));
}

function isExcluded(url) {
    if (url.origin !== window.location.origin) return true;
    const p = url.pathname;
    if (isAuthPath(p)) return true;
    if (p === '/logout') return true;
    if (p.includes('/lampiran/')) return true; // attachment downloads (binary)
    if (NON_HTML_EXT.test(p)) return true;
    return false;
}

function isCacheablePath(url) {
    if (isExcluded(url)) return false;
    const p = url.pathname;
    if (/\/(create|edit|buat)$/.test(p)) return false; // form pages (CSRF)
    if (p.includes('/profil')) return false;
    if (p.includes('/config')) return false;
    return true;
}

/* ──────────────────────────────── cache ──────────────────────────────── */

function cacheKey(url) {
    if (!currentIdentity) return null;
    return currentIdentity + '::' + url.pathname + url.search;
}

function getCached(url) {
    const key = cacheKey(url);
    if (!key) return null;
    const entry = pageCache.get(key);
    if (!entry) return null;
    if (entry.identityKey !== currentIdentity || Date.now() - entry.timestamp > CONFIG.cacheTTL) {
        pageCache.delete(key);
        return null;
    }
    // LRU touch.
    pageCache.delete(key);
    pageCache.set(key, entry);
    return entry;
}

function setCached(url, snapshot) {
    const key = cacheKey(url);
    if (!key || !currentIdentity) return;
    pageCache.delete(key);
    pageCache.set(key, snapshot);
    while (pageCache.size > CONFIG.maxCacheEntries) {
        pageCache.delete(pageCache.keys().next().value);
    }
}

export function clearNavigationCache() {
    pageCache.clear();
    prefetchQueue.length = 0;
    log('cache cleared');
}

/* ──────────────────────────── DOM extraction ──────────────────────────── */

function hasMutationForm(root) {
    for (const form of root.querySelectorAll('form')) {
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'get') return true;
    }
    return false;
}

function extractSnapshot(doc, requestedUrl) {
    const main = doc.querySelector('#main-content');
    if (!main) return null;

    const heading = doc.querySelector('#page-heading');
    const actions = doc.querySelector('#page-actions');
    const nav = doc.querySelector('#primary-navigation');

    return {
        url: requestedUrl,
        title: doc.title || '',
        mainHTML: main.innerHTML,
        headingHTML: heading ? heading.innerHTML : null,
        actionsHTML: actions ? actions.innerHTML : null,
        navHTML: nav ? nav.innerHTML : null,
        hasShell: !!nav,
        hasMutationForm: hasMutationForm(main),
        identity: readIdentity(doc),
        identityKey: currentIdentity,
        timestamp: Date.now(),
        scrollY: 0,
    };
}

/* ─────────────────────────────── rendering ─────────────────────────────── */

function renderSnapshot(snapshot) {
    const main = document.querySelector('#main-content');
    if (main) main.innerHTML = snapshot.mainHTML;

    if (snapshot.headingHTML !== null) {
        const el = document.querySelector('#page-heading');
        if (el) el.innerHTML = snapshot.headingHTML;
    }
    if (snapshot.actionsHTML !== null) {
        const el = document.querySelector('#page-actions');
        if (el) el.innerHTML = snapshot.actionsHTML;
    }
    if (snapshot.navHTML !== null) {
        const el = document.querySelector('#primary-navigation');
        if (el) el.innerHTML = snapshot.navHTML;
    }
    if (snapshot.title) document.title = snapshot.title;

    const focusTarget = document.querySelector('#main-content');
    if (focusTarget && typeof focusTarget.focus === 'function') {
        focusTarget.focus({ preventScroll: true });
    }
}

function retriggerEnter() {
    const main = document.querySelector('#main-content');
    if (!main || !main.classList.contains('page-enter')) return;
    main.classList.remove('page-enter');
    void main.offsetWidth; // force reflow so the animation replays
    main.classList.add('page-enter');
}

function prefersReducedMotion() {
    return typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function applySnapshot(snapshot) {
    const swap = () => renderSnapshot(snapshot);

    // Same-document View Transition when supported; the existing CSS
    // (`::view-transition-*` in app.css) drives the animation. Never required.
    if (typeof document.startViewTransition === 'function' && !prefersReducedMotion()) {
        try {
            document.startViewTransition(swap);
            return;
        } catch {
            /* fall through to a plain swap */
        }
    }

    swap();
    retriggerEnter();
}

/* ────────────────────────────── progress bar ────────────────────────────── */

function progressBar() {
    let bar = document.getElementById('sb-nav-progress');
    if (!bar) {
        bar = document.createElement('div');
        bar.id = 'sb-nav-progress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);
    }
    return bar;
}

function showProgress() {
    const bar = progressBar();
    clearTimeout(progressHideTimer);
    bar.classList.remove('is-done');
    bar.classList.add('is-active');
}

function hideProgress() {
    const bar = document.getElementById('sb-nav-progress');
    if (!bar) return;
    bar.classList.remove('is-active');
    bar.classList.add('is-done');
    progressHideTimer = setTimeout(() => bar.classList.remove('is-done'), 450);
}

/* ─────────────────────────────── navigation ─────────────────────────────── */

function commitHistory(url, mode, scrollY) {
    const state = { sbNav: true, url, scrollY: scrollY ?? 0 };
    if (mode === 'push') history.pushState(state, '', url);
    else if (mode === 'replace') history.replaceState(state, '', url);
}

function saveCurrentScroll() {
    const state = history.state;
    if (state && state.sbNav) {
        history.replaceState({ ...state, scrollY: window.scrollY }, '');
    }
}

async function navigate(url, options) {
    const opts = options || {};
    const historyMode = opts.historyMode || 'push';
    const restoreScrollY = opts.restoreScrollY;

    if (inFlight) {
        try { inFlight.controller.abort(); } catch { /* ignore */ }
        inFlight = null;
    }

    const cached = getCached(url);
    if (cached) {
        log('cache hit', url.pathname);
        if (historyMode !== 'none') saveCurrentScroll();
        applySnapshot(cached);
        commitHistory(cached.url || url.href, historyMode, restoreScrollY);
        if (typeof restoreScrollY === 'number') {
            window.scrollTo({ top: restoreScrollY, behavior: 'auto' });
        } else {
            window.scrollTo({ top: 0, behavior: 'auto' });
        }
        return;
    }

    const controller = new AbortController();
    let timedOut = false;
    const timer = setTimeout(() => { timedOut = true; controller.abort(); }, CONFIG.requestTimeout);
    inFlight = { controller, url: url.href };
    showProgress();

    try {
        const response = await fetch(url.href, {
            method: 'GET',
            credentials: 'same-origin',
            redirect: 'follow',
            headers: { Accept: 'text/html,application/xhtml+xml' },
            signal: controller.signal,
        });

        const finalUrl = response.url || url.href;
        const finalPath = (toUrl(finalUrl) || url).pathname;

        // Session expired / auth redirect: server authority wins.
        if (isAuthPath(finalPath) && !isAuthPath(url.pathname)) {
            clearNavigationCache();
            window.location.assign(finalUrl);
            return;
        }

        const contentType = response.headers.get('content-type') || '';
        if (!response.ok || !contentType.includes('text/html')) {
            // 4xx/5xx or non-HTML: hand off to the browser so the server renders
            // the correct error/asset response. Never cache these.
            if (response.status === 401 || response.status === 419) clearNavigationCache();
            window.location.assign(url.href);
            return;
        }

        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');

        const currentHasShell = !!document.querySelector('#primary-navigation');
        const snapshot = extractSnapshot(doc, url.href);
        if (!snapshot) {
            window.location.assign(finalUrl);
            return;
        }

        // Never mix layouts (dashboard shell <-> non-shell pages).
        if (snapshot.hasShell !== currentHasShell) {
            window.location.assign(finalUrl);
            return;
        }

        // Identity changed mid-session (role/unit change, re-login): purge + adopt.
        if (snapshot.identity && snapshot.identity !== currentIdentity) {
            log('identity change detected');
            clearNavigationCache();
            currentIdentity = snapshot.identity;
            snapshot.identityKey = currentIdentity;
        }

        if (historyMode !== 'none') saveCurrentScroll();
        applySnapshot(snapshot);
        commitHistory(finalUrl, historyMode, restoreScrollY);

        if (typeof restoreScrollY === 'number') {
            window.scrollTo({ top: restoreScrollY, behavior: 'auto' });
        } else {
            window.scrollTo({ top: 0, behavior: 'auto' });
        }

        // Cache only safe, read-only pages (no mutation forms).
        if (isCacheablePath(url) && !snapshot.hasMutationForm) {
            setCached(url, snapshot);
        }
    } catch (error) {
        if (error && error.name === 'AbortError' && !timedOut) {
            return; // superseded by a newer navigation
        }
        // Network/timeout/parse failure: safe fallback to a real navigation.
        window.location.assign(url.href);
    } finally {
        clearTimeout(timer);
        if (inFlight && inFlight.controller === controller) inFlight = null;
        hideProgress();
    }
}

/* ───────────────────────────── click interception ───────────────────────────── */

function onDocumentClick(event) {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!anchor) return;
    if (anchor.hasAttribute('data-no-navigation') || anchor.hasAttribute('download')) return;
    if (anchor.target && anchor.target !== '' && anchor.target !== '_self') return;

    const href = anchor.getAttribute('href') || '';
    if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

    const url = toUrl(href);
    if (!url || isExcluded(url)) return;

    // Same document, only a hash differs -> let the browser scroll.
    if (url.pathname === window.location.pathname && url.search === window.location.search) {
        if (url.hash) return;
        event.preventDefault(); // exact same URL -> no-op (no reload)
        return;
    }

    event.preventDefault();
    navigate(url, { historyMode: 'push' });
}

/* ───────────────────────────────── history ───────────────────────────────── */

function onPopState(event) {
    const url = toUrl(window.location.href);
    if (!url || isExcluded(url)) {
        window.location.reload();
        return;
    }
    const restoreScrollY = event.state && typeof event.state.scrollY === 'number'
        ? event.state.scrollY
        : undefined;
    navigate(url, { historyMode: 'none', restoreScrollY });
}

/* ───────────────────────────────── prefetch ───────────────────────────────── */

async function prefetchOne(url) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), CONFIG.requestTimeout);
    try {
        const response = await fetch(url.href, {
            method: 'GET',
            credentials: 'same-origin',
            redirect: 'follow',
            headers: { Accept: 'text/html,application/xhtml+xml' },
            signal: controller.signal,
        });
        const finalPath = (toUrl(response.url || url.href) || url).pathname;
        if (!response.ok || isAuthPath(finalPath)) return;
        if (!(response.headers.get('content-type') || '').includes('text/html')) return;
        if (!isCacheablePath(url)) return;

        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const snapshot = extractSnapshot(doc, url.href);
        if (!snapshot || snapshot.hasMutationForm) return;

        setCached(url, snapshot);
        log('prefetched', url.pathname);
    } catch {
        /* prefetch is best-effort and never blocks navigation */
    } finally {
        clearTimeout(timer);
    }
}

function drainPrefetch() {
    while (prefetchActive < CONFIG.maxPrefetchConcurrency && prefetchQueue.length) {
        const url = prefetchQueue.shift();
        prefetchActive += 1;
        prefetchInFlight.add(url.href);
        prefetchOne(url).finally(() => {
            prefetchActive -= 1;
            prefetchInFlight.delete(url.href);
            drainPrefetch();
        });
    }
}

function schedulePrefetch(url) {
    if (!currentIdentity) return;
    if (getCached(url) || prefetchInFlight.has(url.href)) return;
    if (prefetchQueue.some((u) => u.href === url.href)) return;
    prefetchQueue.push(url);
    drainPrefetch();
}

function onPrefetchIntent(event) {
    const anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!anchor || !anchor.closest('#primary-navigation')) return; // primary nav only
    if (anchor.hasAttribute('data-no-navigation')) return;
    const url = toUrl(anchor.getAttribute('href'));
    if (!url || isExcluded(url)) return;
    schedulePrefetch(url);
}

/* ─────────────────────────────────── boot ─────────────────────────────────── */

export function initNavigation() {
    if (initialized) return;
    initialized = true;

    currentIdentity = readIdentity(document);

    // We manage scroll restoration ourselves (push -> top, popstate -> saved).
    if ('scrollRestoration' in history) {
        history.scrollRestoration = 'manual';
    }
    // Track the entry that loaded this document so Back to it can restore scroll.
    if (!history.state || !history.state.sbNav) {
        history.replaceState(
            { sbNav: true, url: window.location.href, scrollY: window.scrollY },
            '',
        );
    }

    document.addEventListener('click', onDocumentClick, false);
    window.addEventListener('popstate', onPopState, false);

    document.addEventListener('mouseover', onPrefetchIntent, { passive: true });
    document.addEventListener('focusin', onPrefetchIntent, { passive: true });

    // bfcache restore can resurrect this JS heap (and its Map). Never trust a
    // pre-restore cache: drop it so the next request re-validates auth.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) clearNavigationCache();
    });

    progressBar();
    log('navigation ready; identity', currentIdentity ? 'present' : 'absent (cache disabled)');
}
