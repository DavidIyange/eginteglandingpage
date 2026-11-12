/* EG Integrated Services - Cybersecurity landing page scripts */
(function () {
  'use strict';

  /* ---------- SETTINGS (edit these) ---------- */
  var GTM_ID = 'GTM-XXXXXXX';          // your Google Tag Manager container ID
  var CONSENT_KEY = 'eg_cookie_consent';
  var TRACK_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];

  window.dataLayer = window.dataLayer || [];

  /* ---------- Cookie consent (tracking loads only after "Accept") ---------- */
  function getConsent() { try { return localStorage.getItem(CONSENT_KEY); } catch (e) { return null; } }
  function setConsent(v) { try { localStorage.setItem(CONSENT_KEY, v); } catch (e) {} }

  function loadGTM() {
    if (!GTM_ID || GTM_ID.indexOf('XXXX') > -1 || window.__gtmLoaded) { return; }
    window.__gtmLoaded = true;
    window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(GTM_ID);
    document.head.appendChild(s);
  }

  function showBanner() {
    var box = document.createElement('div');
    box.className = 'cookie';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', 'Cookie preferences');
    box.innerHTML =
      '<p>We use cookies to measure how visitors use this page and to improve our advertising. ' +
      'You can accept or decline. See our <a href="privacy.html">privacy notice</a>.</p>' +
      '<div class="row"><button type="button" class="btn" data-c="accepted">Accept</button>' +
      '<button type="button" class="btn ghost" data-c="declined">Decline</button></div>';
    box.addEventListener('click', function (e) {
      var v = e.target.getAttribute && e.target.getAttribute('data-c');
      if (!v) { return; }
      setConsent(v);
      box.parentNode.removeChild(box);
      if (v === 'accepted') { loadGTM(); }
    });
    document.body.appendChild(box);
  }

  var consent = getConsent();
  if (consent === 'accepted') { loadGTM(); }
  else if (!consent) { showBanner(); }

  /* ---------- Click tracking (calls, WhatsApp, email) ---------- */
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('[data-track]') : null;
    if (el) { window.dataLayer.push({ event: el.getAttribute('data-track') }); }
  });

  /* ---------- Footer year ---------- */
  var yr = document.getElementById('year');
  if (yr) { yr.textContent = new Date().getFullYear(); }

  /* ---------- Thank-you page: send the conversion event once ---------- */
  if (document.body.getAttribute('data-page') === 'thank-you') {
    var sent = false;
    try { sent = sessionStorage.getItem('eg_lead_sent') === '1'; } catch (e) {}
    if (!sent) {
      window.dataLayer.push({ event: 'lead_form_submit', lead_type: 'cybersecurity' });
      try { sessionStorage.setItem('eg_lead_sent', '1'); } catch (e) {}
    }
  }

  /* ---------- Enquiry form ---------- */
  var form = document.getElementById('lead-form');
  if (!form) { return; }

  var started = Date.now();

  // Remember campaign parameters (first visit wins) and put them in the hidden fields
  (function captureCampaign() {
    var params = new URLSearchParams(window.location.search);
    TRACK_KEYS.forEach(function (k) {
      var v = params.get(k);
      try {
        if (v) { sessionStorage.setItem('eg_' + k, v); }
        else { v = sessionStorage.getItem('eg_' + k) || ''; }
      } catch (e) { v = v || ''; }
      var input = form.elements[k];
      if (input) { input.value = v; }
    });
    document.getElementById('page_url').value = window.location.origin + window.location.pathname;
  })();

  var status = document.getElementById('form-status');
  var btn = document.getElementById('submit-btn');

  function setInvalid(id, bad) {
    var input = document.getElementById(id);
    var wrap = input.closest('.field');
    if (wrap) { wrap.classList.toggle('invalid', bad); }
    input.setAttribute('aria-invalid', bad ? 'true' : 'false');
  }

  function validate() {
    var ok = true;
    var name = form.elements.name.value.trim();
    var org = form.elements.organisation.value.trim();
    var email = form.elements.email.value.trim();
    var phone = form.elements.phone.value.trim();
    var digits = phone.replace(/\D/g, '');

    var checks = [
      ['name', name.length < 2],
      ['organisation', org.length < 2],
      ['email', !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)],
      ['phone', digits.length < 7 || digits.length > 15 || !/^[0-9+()\-\s]+$/.test(phone)],
      ['interest', !form.elements.interest.value]
    ];
    var firstBad = null;
    checks.forEach(function (c) {
      setInvalid(c[0], c[1]);
      if (c[1]) { ok = false; if (!firstBad) { firstBad = c[0]; } }
    });

    var consentBox = document.getElementById('consent');
    var consentBad = !consentBox.checked;
    document.getElementById('consent-wrap').classList.toggle('invalid', consentBad);
    document.getElementById('err-consent').style.display = consentBad ? 'block' : 'none';
    if (consentBad) { ok = false; if (!firstBad) { firstBad = 'consent'; } }

    if (firstBad) { document.getElementById(firstBad).focus(); }
    return ok;
  }

  function say(msg, bad) {
    status.textContent = msg;
    status.className = 'form-status' + (bad ? ' bad' : '');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    say('', false);
    if (!validate()) { say('Please fix the highlighted fields and try again.', true); return; }

    document.getElementById('elapsed').value = Math.round((Date.now() - started) / 1000);
    btn.disabled = true;
    btn.textContent = 'Sending...';

    fetch(form.getAttribute('action'), {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (r) {
        return r.json().then(function (d) { return d; }, function () { return { ok: false }; });
      })
      .then(function (data) {
        if (data && data.ok) {
          window.location.href = data.redirect || 'thank-you.html';
          return;
        }
        if (data && data.errors) {
          Object.keys(data.errors).forEach(function (k) {
            if (document.getElementById(k)) { setInvalid(k, true); }
          });
        }
        say((data && data.message) || 'We could not send your request. Please call +234 703 222 2399 or email info@eginteg.com.', true);
        btn.disabled = false;
        btn.textContent = 'Request my consultation';
      })
      .catch(function () {
        say('Network problem. Check your connection and try again, or call +234 703 222 2399.', true);
        btn.disabled = false;
        btn.textContent = 'Request my consultation';
      });
  });

  // Clear an error as soon as the person edits the field
  form.addEventListener('input', function (e) {
    var wrap = e.target.closest ? e.target.closest('.field') : null;
    if (wrap) { wrap.classList.remove('invalid'); }
  });
})();
