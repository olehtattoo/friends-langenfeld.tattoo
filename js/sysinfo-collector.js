/*!
 * sysinfo-collector.js (Unified, v4.2-noCookies)
 * Один скрипт, без cookie-хранилища.
 * Авто: логгер визитов.
 * Ручное: window.initFormSendHandler(formEl, sendBtn, options?)
 */

; (() => {
  // ==========================
  //   Г Л О Б А Л Ь Н Ы Е
  // ==========================
  const SCRIPT_ID = 'sysinfo-collector.js';
  const GAS_ENDPOINT =
    'https://script.google.com/macros/s/AKfycbyFGq-odJckBUQBTMjr2tpHXAqMgC3zpWIB34EIMf_n3NmDPdh78ymWXia0D92kbmTY/exec';

  const DEFAULT_STORAGE_KEY = 'tattooFormData_v1';
  const DEBUG = true;

  // простая замена cookie — volatile память
  const memStore = {};

  // ===== DEBUG STARTUP =====
  try {
    window.SYSDEBUG = true;
    if (!window.__SYSINFO_BANNER__) {
      window.__SYSINFO_BANNER__ = true;
      console.log('%c[SYS] Debug ENABLED', 'background:#111;color:#0f0;padding:2px 8px;border-radius:3px');
      console.log('[SYS] Версия скрипта:', 'v4.2-noCookies');
    }
  } catch (e) { console.warn('[SYS] Debug init error:', e); }

  // ==========================
  //   D E B U G  У Т И Л И Т Ы
  // ==========================
  const DBG = (() => {
    const ns = '[SYS]';
    const enabled = () => DEBUG || window.SYSDEBUG === true;
    const fmt = (label, extra) => (extra === undefined ? [`${ns} ${label}`] : [`${ns} ${label}`, extra]);
    return {
      group(label, obj) { if (enabled()) console.groupCollapsed(...fmt(label, obj)); },
      groupEnd() { if (enabled()) console.groupEnd(); },
      log(label, obj) { if (enabled()) console.log(...fmt(label, obj)); },
      info(label, obj) { if (enabled()) console.info(...fmt(label, obj)); },
      warn(label, obj) { console.warn(...fmt(label, obj)); },
      error(label, obj) { console.error(...fmt(label, obj)); },
      time(label) {
        if (!enabled()) return () => { };
        const start = performance.now();
        return () => console.log(`${ns} ${label} [${(performance.now() - start).toFixed(1)} ms]`);
      },
      sizeOf(title, any) {
        if (!enabled()) return;
        try {
          const text = typeof any === 'string' ? any : JSON.stringify(any);
          const bytes = new TextEncoder().encode(text).length;
          console.log(`${ns} ${title}: ~${bytes} bytes`);
        } catch { }
      }
    };
  })();

  const nowIso = () => new Date().toISOString();
  const secondsNow = () => Math.floor(Date.now() / 1000);

  async function sha256Base64(obj) {
    const t = DBG.time('sha256Base64');
    try {
      const txt = typeof obj === 'string' ? obj : JSON.stringify(obj);
      const enc = new TextEncoder().encode(txt);
      const buf = await crypto.subtle.digest('SHA-256', enc);
      const b64 = btoa(String.fromCharCode(...new Uint8Array(buf)))
        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
      DBG.sizeOf('sha256 input', txt);
      return b64;
    } catch (e) { DBG.error('sha256Base64 failed', e); return ''; }
    finally { t(); }
  }

  async function sendUnified(type, payload = {}, subject = '') {
    DBG.group(`sendUnified → ${type}`, { subject });
    const t = DBG.time(`sendUnified ${type}`);
    try {
      const fd = new FormData();
      fd.append('timestamp_iso', nowIso());
      fd.append('message_type', type);
      fd.append('data_json', JSON.stringify(payload));
      if (subject) fd.append('subject', subject);

      let sent = false;
      if (navigator.sendBeacon) {
        try { sent = !!navigator.sendBeacon(GAS_ENDPOINT, fd); } catch { }
      }
      if (!sent) await fetch(GAS_ENDPOINT, { method: 'POST', body: fd, mode: 'no-cors', keepalive: true });
      DBG.info('sent', { type });
    } catch (e) { DBG.error('sendUnified failed', e); }
    finally { t(); DBG.groupEnd(); }
  }

  // ==========================
  //   SYSTEM INFO для формы
  // ==========================
  const HIDDEN_WRAP_ID = 'sysinfo-hidden-wrap';
  const HIDDEN_JSON_ID = 'sysinfo_json';
  const $ = (sel, root = document) => root.querySelector(sel);

  function ensureHiddenWrap(formEl) {
    let wrap = document.getElementById(HIDDEN_WRAP_ID);
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = HIDDEN_WRAP_ID;
      wrap.style.display = 'none';
      formEl.appendChild(wrap);
    }
    if (!$('#' + HIDDEN_JSON_ID, wrap)) {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.id = HIDDEN_JSON_ID;
      inp.name = 'system_info_json';
      inp.value = '{}';
      wrap.appendChild(inp);
    }
    return wrap;
  }

  function setHiddenField(wrap, key, val) {
    let i = wrap.querySelector(`input[name="${key}"]`);
    if (!i) {
      i = document.createElement('input');
      i.type = 'hidden';
      i.name = key;
      wrap.appendChild(i);
    }
    i.value = String(val ?? '');
  }

  async function collectSystemInfo(formEl) {
    if (!formEl) return;
    const wrap = ensureHiddenWrap(formEl);
    const set = (k, v) => setHiddenField(wrap, k, v);
    set('1: client_ts_iso', nowIso());
    set('2: page_url', location.href);
    set('3: page_title', document.title);
    set('4: referrer', document.referrer);
    set('5: language', navigator.language);
    set('6: tz', Intl.DateTimeFormat().resolvedOptions().timeZone);
    if (navigator.getBattery) {
      try {
        const b = await navigator.getBattery();
        set('10: battery', `${b.level},${b.charging}`);
      } catch { }
    }
  }

  function appendSystemInfoToFormData(fd, formEl) {
    try {
      const wrap = ensureHiddenWrap(formEl);
      wrap.querySelectorAll('input[type="hidden"]').forEach(i => fd.append(i.name, i.value));
    } catch (e) { DBG.warn('appendSystemInfoToFormData', e); }
  }

  // ==========================
  //   АВТО-ЛОГГЕР ВИЗИТОВ
  // ==========================
  let __ipInfoPromise;
  function fetchIpInfo() {
    if (__ipInfoPromise) return __ipInfoPromise;
    __ipInfoPromise = (async () => {
      try {
        const ip = await fetch('https://api.ipify.org?format=json').then(r => r.json()).then(j => j.ip);
        const geo = await fetch('https://ipapi.co/json/').then(r => r.json()).catch(() => ({}));
        return { public_ip: ip, ...geo };
      } catch { return {}; }
    })();
    return __ipInfoPromise;
  }

  async function initHeaderTrafficLogger(opt = {}) {
    DBG.group('initHeaderTrafficLogger', opt);
    const cfg = Object.assign({ subjectPrefix: 'Visitor', hourlyLimitSec: 3600 }, opt);
    try {
      // вместо cookie — volatile profile
      let profile = memStore.profile || {};
      if (!profile.id) profile.id = crypto.randomUUID?.() || Date.now().toString(36);
      if (!profile.first) profile.first = secondsNow();
      if (!Array.isArray(profile.visits)) profile.visits = [];

      const now = secondsNow();
      const cur = { ts: now, path: location.pathname, ref: document.referrer || '' };
      const last = profile.visits[profile.visits.length - 1];
      if (!last || last.path !== cur.path || now - last.ts > 60) {
        profile.visits.push(cur);
        if (profile.visits.length > 50) profile.visits = profile.visits.slice(-50);
      }

      const ip = await fetchIpInfo();
      const subject = `${cfg.subjectPrefix} ${profile.id}`;
      const payload = { subject, profile, ip, client: navigator.userAgent };

      const lastSent = memStore.lastSent || 0;
      const hash = await sha256Base64(payload);
      const lastHash = memStore.lastHash || '';
      if (now - lastSent > cfg.hourlyLimitSec || hash !== lastHash) {
        await sendUnified('visit_log', payload, subject);
        memStore.lastSent = now;
        memStore.lastHash = hash;
      }

      memStore.profile = profile;
    } catch (e) { DBG.error('initHeaderTrafficLogger error', e); }
    DBG.groupEnd();
  }
  window.initHeaderTrafficLogger = initHeaderTrafficLogger;

  // ==========================
  //   ТРЕКИНГ ФОРМЫ (ручной)
  // ==========================
  function hasFilledFields(form) {
    return [...form.querySelectorAll('input,textarea,select')].some(f => {
      if (f.type === 'hidden' || f.disabled) return false;
      if (['text', 'email', 'tel'].includes(f.type) && f.value.trim()) return true;
      if (['checkbox', 'radio'].includes(f.type) && f.checked) return true;
      if (f.tagName === 'TEXTAREA' && f.value.trim()) return true;
      if (f.tagName === 'SELECT' && f.value) return true;
      return false;
    });
  }

  function buildFormSnapshot(form, opt, subtype) {
    const fd = new FormData(form);
    appendSystemInfoToFormData(fd, form);
    const plain = {};
    for (const [k, v] of fd.entries()) plain[k] = typeof v === 'string' ? v : '[file]';
    plain._subtype = subtype;
    return plain;
  }

  function initFormSendHandler(form, btn, opt = {}) {
    DBG.group('initFormSendHandler', opt);
    if (!form || !btn) return;
    const cfg = Object.assign({ emptyClickThrottleSec: 15, submitThrottleSec: 5 }, opt);
    collectSystemInfo(form);

    btn.addEventListener('click', () => {
      if (!hasFilledFields(form)) {
        const last = memStore.lastEmptyClick || 0;
        const now = secondsNow();
        if (now - last >= cfg.emptyClickThrottleSec) {
          memStore.lastEmptyClick = now;
          sendUnified('form_empty_click', { url: location.href });
        }
      }
    });

    form.addEventListener('submit', async () => {
      if (!hasFilledFields(form)) return;
      const snap = buildFormSnapshot(form, cfg, 'submit');
      const hash = await sha256Base64(snap);
      const lastHash = memStore.lastFormHash || '';
      const lastTs = memStore.lastFormTs || 0;
      const now = secondsNow();
      if (hash !== lastHash || now - lastTs >= cfg.submitThrottleSec) {
        await sendUnified('form_submit', snap);
        memStore.lastFormHash = hash;
        memStore.lastFormTs = now;
      }
    });

    window.addEventListener('pagehide', async () => {
      if (hasFilledFields(form)) {
        const snap = buildFormSnapshot(form, cfg, 'pagehide');
        await sendUnified('form_pagehide', snap);
      }
    });
    DBG.groupEnd();
  }
  window.initFormSendHandler = initFormSendHandler;

  // ==========================
  //   AUTO: LOG VISIT
  // ==========================
  document.addEventListener('DOMContentLoaded', () => {
    initHeaderTrafficLogger({ debug: DEBUG });
  });
})();
