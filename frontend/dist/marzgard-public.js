/* ============================================================
   مرزگارد | MarzGard — Headless Vue 3 authentication client
   No build step required: uses the local Vue global runtime.
   ============================================================ */
(function () {
  'use strict';

  if (!window.Vue || !window.MARZGARD) { return; }

  var createApp = Vue.createApp;
  var ref = Vue.ref;
  var reactive = Vue.reactive;
  var computed = Vue.computed;
  var watch = Vue.watch;
  var onMounted = Vue.onMounted;
  var onBeforeUnmount = Vue.onBeforeUnmount;
  var nextTick = Vue.nextTick;

  var CFG = window.MARZGARD;
  var API = CFG.restUrl.replace(/\/$/, '');

  /* ---------------- Icons (inline SVG) ---------------- */
  var ICONS = {
    shield: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3Zm0 2.2 6 2.25V11c0 3.9-2.5 7.4-6 8.8-3.5-1.4-6-4.9-6-8.8V6.45L12 4.2Zm0 3.3a1 1 0 0 1 1 1v3.2l2 1.2a1 1 0 1 1-1 1.7l-2.5-1.5a1 1 0 0 1-.5-.87V8.5a1 1 0 0 1 1-1Z"/>',
    phone: '<path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm1 2v16h8V4H8Zm3 13h2v2h-2v-2Z"/>',
    check: '<path fill-rule="evenodd" clip-rule="evenodd" d="m9.55 17.6-5-5a1 1 0 0 1 1.42-1.42l3.58 3.59 7.9-7.9a1 1 0 0 1 1.42 1.42l-9.32 9.31Z"/>',
    chevron: '<path fill-rule="evenodd" clip-rule="evenodd" d="M15.7 8.3a1 1 0 0 1 0 1.4l-4.3 4.3-4.3-4.3a1 1 0 1 1 1.42-1.42L11 11.58l2.28-2.3a1 1 0 0 1 1.42 0Z"/>',
    search: '<path fill-rule="evenodd" clip-rule="evenodd" d="M10.5 3a7.5 7.5 0 1 0 4.55 13.46l4.24 4.25a1 1 0 0 0 1.42-1.42l-4.25-4.24A7.5 7.5 0 0 0 10.5 3Zm-5.5 7.5a5.5 5.5 0 1 1 11 0 5.5 5.5 0 0 1-11 0Z"/>',
    arrow: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 4a1 1 0 0 1 1 1v11.59l4.3-4.3a1 1 0 0 1 1.4 1.42l-6 6a1 1 0 0 1-1.4 0l-6-6a1 1 0 1 1 1.4-1.42l4.3 4.3V5a1 1 0 0 1 1-1Z"/>',
    alert: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 2a7 7 0 1 1 0 14 7 7 0 0 1 0-14Zm-1 3h2v5h-2V8Zm0 6.5h2v2h-2v-2Z"/>',
    info: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 2a7 7 0 1 1 0 14 7 7 0 0 1 0-14Zm-1 3h2v2h-2V8Zm0 3.5h2v5h-2v-5Z"/>',
    success: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm4.7 7.7-5.9 5.9a1 1 0 0 1-1.42 0l-2.1-2.12a1 1 0 1 1 1.42-1.42l1.39 1.4 5.19-5.2a1 1 0 0 1 1.42 1.42Z"/>',
    lock: '<path fill-rule="evenodd" clip-rule="evenodd" d="M7 10V7a5 5 0 0 1 10 0v3h1a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h1Zm2 0h6V7a3 3 0 1 0-6 0v3Z"/>',
    user: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm-6 9a6 6 0 0 0-6 6v2h24v-2a6 6 0 0 0-6-6H6Z"/>',
    spark: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2 9.6 8.4 3 11l6.6 2.6L12 20l2.4-6.4L21 11l-6.6-2.6L12 2Z"/>'
  };

  var MgIcon = {
    name: 'mg-icon',
    props: { name: { type: String, required: true } },
    template: '<svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><g v-html="path"></g></svg>',
    computed: {
      path: function () { return ICONS[this.name] || ''; }
    }
  };

  /* ---------------- API client ---------------- */
  function request(path, body, method) {
    var opts = {
      method: method || 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': CFG.nonce || ''
      }
    };
    if (opts.method !== 'GET') { opts.body = JSON.stringify(body || {}); }

    return fetch(API + path, opts).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok || data.success === false) {
          var err = new Error(data.message || 'request_failed');
          err.code = data.code || '';
          throw err;
        }
        return data;
      });
    });
  }

  /* ---------------- App factory ---------------- */
  function createInstance(mountEl) {
    var initialMode = mountEl.getAttribute('data-mode') || '';
    var requestedRedirect = mountEl.getAttribute('data-redirect') || '';

    var App = {
      components: { MgIcon: MgIcon },
      setup: function () {
        var config = reactive({
          brand: 'مرزگارد',
          tagline: 'ورود امن به حساب کاربری',
          mode: 'login_register',
          register: true,
          countries: [],
          strings: {},
          otp_length: 6,
          appearance: { primary: '#009595', radius: 12, darkMode: 'auto', fontSize: 14 }
        });

        var ready = ref(false);
        var step = ref('phone');
        var tab = ref(initialMode === 'register' ? 'register' : 'login');
        var purpose = ref(tab.value);
        var phone = ref('');
        var country = ref({ code: '+98', name: 'ایران', flag: '🇮🇷' });
        var challenge = ref('');
        var masked = ref('');
        var otp = ref([]);
        var otpCells = [];
        var remember = ref(false);
        var loading = ref(false);
        var error = ref('');
        var toasts = ref([]);
        var countdown = ref(0);
        var countryOpen = ref(false);
        var countryQuery = ref('');
        var successMessage = ref('');
        var redirectUrl = ref(requestedRedirect || '');
        var countryRoot = ref(null);
        var captchaId = ref('');
        var captchaQuestion = ref('');
        var captchaAnswer = ref('');
        var captchaToken = ref('');
        var captchaEl = ref(null);
        var timer = null;
        var toastId = 0;

        var s = function (key, fallback) {
          return config.strings[key] || fallback || key;
        };

        var showTabs = computed(function () {
          return config.mode === 'login_register' && config.register;
        });

        var operator = computed(function () {
          if (country.value.code !== '+98') { return ''; }
          var digits = normalizedPhone();
          var p = digits.slice(0, 3);
          if (!/^9\d{2}$/.test(p)) { return ''; }
          if (/^9(1\d|3[0-3])$/.test(p)) { return 'همراه اول'; }
          if (/^92[0-2]$/.test(p)) { return 'رایتل'; }
          if (/^9(3[5-9]|9[0-6])$/.test(p)) { return 'ایرانسل'; }
          return 'سایر اپراتورها';
        });

        var filteredCountries = computed(function () {
          var q = countryQuery.value.trim().toLowerCase();
          if (!q) { return config.countries; }
          return config.countries.filter(function (c) {
            return c.name.indexOf(q) !== -1 || c.code.indexOf(q) !== -1 || c.iso.toLowerCase().indexOf(q) !== -1;
          });
        });

        var maskedPhone = computed(function () {
          return masked.value || (country.value.code + phone.value);
        });

        function toast(message, type) {
          var id = ++toastId;
          toasts.value.push({ id: id, message: message, type: type || 'info' });
          setTimeout(function () {
            toasts.value = toasts.value.filter(function (t) { return t.id !== id; });
          }, 4200);
        }

        function resetError() { error.value = ''; }

        function applyTheme() {
          var mode = config.appearance.darkMode || 'auto';
          var theme = mode;
          if (mode === 'auto') {
            theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
          }
          mountEl.setAttribute('data-mg-theme', theme);
          mountEl.style.setProperty('--mg-primary', config.appearance.primary);
          mountEl.style.setProperty('--mg-radius', (config.appearance.radius || 12) + 'px');
          mountEl.style.setProperty('--mg-fs', (config.appearance.fontSize || 14) + 'px');
        }

        function normalizedPhone() {
          var fa = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9' };
          var ar = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' };
          var digits = String(phone.value || '')
            .replace(/[۰-۹]/g, function (c) { return fa[c]; })
            .replace(/[٠-٩]/g, function (c) { return ar[c]; })
            .replace(/\D/g, '');
          if (country.value.code === '+98' && digits.charAt(0) === '0') { digits = digits.slice(1); }
          return digits;
        }

        function validatePhone() {
          var digits = normalizedPhone();
          if (!digits) { error.value = s('err_empty_phone', 'شماره موبایل را وارد کنید.'); return false; }
          if (digits.length < 8 || digits.length > 13) { error.value = s('err_invalid_phone', 'شماره موبایل معتبر نیست.'); return false; }
          return true;
        }

        function loadScript(src, callback) {
          var script = document.createElement('script');
          script.src = src;
          script.async = true;
          script.defer = true;
          if (callback) { script.onload = callback; }
          document.head.appendChild(script);
        }

        function setupCaptcha() {
          var cfg = config.security || {};
          var provider = cfg.captcha || 'none';
          if (provider === 'none') { return; }

          if (provider === 'internal') {
            request('/captcha', null, 'GET').then(function (data) {
              captchaId.value = data.id;
              captchaQuestion.value = data.question;
            }).catch(function () {});
            return;
          }

          if (provider === 'arcaptcha') {
            loadScript('https://nwidget.arcaptcha.ir/1/api.js', function () {
              nextTick(function () {
                if (!captchaEl.value) { return; }
                var box = document.createElement('div');
                box.className = 'arcaptcha';
                box.setAttribute('data-site-key', cfg.siteKey || '');
                captchaEl.value.appendChild(box);
                document.addEventListener('input', function (e) {
                  if (e.target && e.target.name === 'arcaptcha-token') { captchaToken.value = e.target.value; }
                });
              });
            });
            return;
          }

          if (provider === 'recaptcha' && cfg.recaptchaV === 'v3') {
            loadScript('https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(cfg.siteKey || ''));
            return;
          }

          var url = provider === 'turnstile'
            ? 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
            : 'https://www.google.com/recaptcha/api.js?render=explicit';
          loadScript(url, function () {
            nextTick(function () {
              if (!captchaEl.value) { return; }
              var done = function (token) { captchaToken.value = token; };
              if (provider === 'turnstile' && window.turnstile) {
                window.turnstile.render(captchaEl.value, { sitekey: cfg.siteKey || '', callback: done });
              } else if (window.grecaptcha && window.grecaptcha.render) {
                window.grecaptcha.render(captchaEl.value, { sitekey: cfg.siteKey || '', callback: done });
              }
            });
          });
        }

        function refreshCaptcha() {
          var provider = (config.security || {}).captcha || 'none';
          if (provider === 'internal') {
            captchaAnswer.value = '';
            request('/captcha', null, 'GET').then(function (data) {
              captchaId.value = data.id;
              captchaQuestion.value = data.question;
            }).catch(function () {});
          } else {
            captchaToken.value = '';
          }
        }

        function startCountdown(seconds) {
          countdown.value = seconds || 30;
          clearInterval(timer);
          timer = setInterval(function () {
            if (countdown.value > 0) { countdown.value--; } else { clearInterval(timer); }
          }, 1000);
        }

        function selectCountry(c) {
          country.value = c;
          countryOpen.value = false;
          countryQuery.value = '';
          resetError();
        }

        function onDocumentClick(e) {
          if (countryRoot.value && !countryRoot.value.contains(e.target)) { countryOpen.value = false; }
        }

        function sendCode() {
          if (!validatePhone()) { return; }
          loading.value = true;
          resetError();

          var payload = {
            phone: normalizedPhone(),
            country_code: country.value.code,
            purpose: purpose.value,
            website: '',
            captcha_id: captchaId.value,
            captcha_answer: captchaAnswer.value,
            captcha_token: captchaToken.value
          };

          function submit(payload) {
            request('/otp/send', payload).then(function (data) {
              challenge.value = data.challenge;
              masked.value = data.masked || '';
              otp.value = new Array(config.otp_length || 6).fill('');
              step.value = 'otp';
              redirectUrl.value = requestedRedirect || redirectUrl.value;
              startCountdown((config.security && config.security.resendWait) || 120);
              toast(data.message || '', 'success');
              if (data.dev_code) { toast('کد توسعه: ' + data.dev_code, 'info'); }
              nextTick(function () { if (otpCells[0]) { otpCells[0].focus(); } });
            }).catch(function (err) {
              error.value = err.message || s('err_generic', 'خطایی رخ داد.');
              refreshCaptcha();
            }).then(function () { loading.value = false; });
          }

          var sec = config.security || {};
          if (sec.captcha === 'recaptcha' && sec.recaptchaV === 'v3' && window.grecaptcha && window.grecaptcha.execute) {
            window.grecaptcha.execute(sec.siteKey, { action: 'marzgard' }).then(function (token) {
              payload.captcha_token = token; submit(payload);
            }).catch(function () { loading.value = false; });
          } else {
            submit(payload);
          }
        }

        function resend() {
          if (countdown.value > 0 || !challenge.value) { return; }
          loading.value = true;
          request('/otp/resend', { challenge: challenge.value }).then(function (data) {
            startCountdown((config.security && config.security.resendWait) || 120);
            toast(data.message || '', 'success');
            if (data.dev_code) { toast('کد توسعه: ' + data.dev_code, 'info'); }
            otp.value = new Array(config.otp_length || 6).fill('');
            nextTick(function () { if (otpCells[0]) { otpCells[0].focus(); } });
          }).catch(function (err) {
            error.value = err.message || s('err_generic', 'خطایی رخ داد.');
          }).then(function () { loading.value = false; });
        }

        function verify() {
          var code = otp.value.join('');
          if (!code) { error.value = s('err_empty_otp', 'کد تأیید را وارد کنید.'); return; }
          loading.value = true;
          resetError();

          request('/otp/verify', {
            challenge: challenge.value,
            otp: code,
            remember: remember.value,
            redirect: redirectUrl.value
          }).then(function (data) {
            successMessage.value = data.message;
            step.value = 'success';
            toast(data.message || '', 'success');
            setTimeout(function () {
              if (data.redirect) { window.location.href = data.redirect; }
            }, 900);
          }).catch(function (err) {
            error.value = err.message || s('err_invalid_otp', 'کد تأیید نادرست است.');
            otp.value = new Array(config.otp_length || 6).fill('');
            nextTick(function () { if (otpCells[0]) { otpCells[0].focus(); } });
          }).then(function () { loading.value = false; });
        }

        function onOtpInput(index, event) {
          var value = event.target.value.replace(/[^0-9]/g, '');
          if (!value) { otp.value[index] = ''; return; }
          var chars = value.split('');
          chars.forEach(function (ch, offset) {
            var i = index + offset;
            if (i < otp.value.length) { otp.value[i] = ch; }
          });
          var next = Math.min(index + chars.length, otp.value.length - 1);
          nextTick(function () { if (otpCells[next]) { otpCells[next].focus(); } });
        }

        function onOtpKeydown(index, event) {
          if (event.key === 'Backspace' && !otp.value[index] && index > 0) {
            otpCells[index - 1].focus();
          } else if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault(); otpCells[index - 1].focus();
          } else if (event.key === 'ArrowRight' && index < otp.value.length - 1) {
            event.preventDefault(); otpCells[index + 1].focus();
          }
        }

        function onOtpPaste(event) {
          var text = (event.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
          if (!text) { return; }
          event.preventDefault();
          text.slice(0, otp.value.length).split('').forEach(function (ch, i) { otp.value[i] = ch; });
          var next = Math.min(text.length, otp.value.length - 1);
          nextTick(function () { if (otpCells[next]) { otpCells[next].focus(); } });
        }

        function switchTab(next) {
          tab.value = next;
          purpose.value = next;
          step.value = 'phone';
          phone.value = '';
          otp.value = new Array(config.otp_length || 6).fill('');
          challenge.value = '';
          resetError();
          clearInterval(timer);
          countdown.value = 0;
        }

        function backToPhone() {
          step.value = 'phone';
          challenge.value = '';
          resetError();
          clearInterval(timer);
          countdown.value = 0;
        }

        onMounted(function () {
          document.addEventListener('click', onDocumentClick);
          request('/config', null, 'GET').then(function (data) {
            Object.keys(config).forEach(function (key) {
              if (data[key] !== undefined) { config[key] = data[key]; }
            });
            if (initialMode === 'register' || initialMode === 'login') {
              config.mode = initialMode;
              tab.value = initialMode;
              purpose.value = initialMode;
            }
            otp.value = new Array(config.otp_length || 6).fill('');
            applyTheme();
            ready.value = true;
            nextTick(setupCaptcha);
          }).catch(function () {
            ready.value = true;
          });
        });

        onBeforeUnmount(function () {
          document.removeEventListener('click', onDocumentClick);
          clearInterval(timer);
        });

        return {
          config: config, ready: ready, step: step, tab: tab, purpose: purpose,
          phone: phone, country: country, otp: otp, otpCells: otpCells,
          remember: remember, loading: loading, error: error, toasts: toasts,
          countdown: countdown, countryOpen: countryOpen, countryQuery: countryQuery,
          filteredCountries: filteredCountries, showTabs: showTabs, operator: operator,
          captchaId: captchaId, captchaQuestion: captchaQuestion, captchaAnswer: captchaAnswer,
          captchaToken: captchaToken, captchaEl: captchaEl,
          maskedPhone: maskedPhone, successMessage: successMessage,
          countryRoot: countryRoot, s: s,
          sendCode: sendCode, resend: resend, verify: verify,
          onOtpInput: onOtpInput, onOtpKeydown: onOtpKeydown, onOtpPaste: onOtpPaste,
          selectCountry: selectCountry, switchTab: switchTab, backToPhone: backToPhone,
          resetError: resetError
        };
      },
      template: [
        '<div class="mg-app" v-if="ready">',
        '  <div class="mg-shell">',
        '    <aside class="mg-aside">',
        '      <div class="mg-brand"><span class="mg-brand__mark"><mg-icon name="shield"></mg-icon></span><span>{{ config.brand }}</span></div>',
        '      <div>',
        '        <h2 class="mg-aside__title">{{ config.tagline }}</h2>',
        '        <p class="mg-aside__text">یک لایه امنیتی ساده و سریع برای ورود کاربران شما؛ بدون رمز عبور، بدون پیچیدگی.</p>',
        '        <ul class="mg-aside__list">',
        '          <li><mg-icon name="check"></mg-icon><span>ورود تنها با شماره موبایل</span></li>',
        '          <li><mg-icon name="check"></mg-icon><span>کد یکبارمصرف با انقضای خودکار</span></li>',
        '          <li><mg-icon name="check"></mg-icon><span>محافظت در برابر تلاش‌های مکرر</span></li>',
        '        </ul>',
        '      </div>',
        '      <div class="mg-aside__foot">© مرزگارد — امنیت در مرزها</div>',
        '    </aside>',
        '',
        '    <main class="mg-panel">',
        '      <div class="mg-panel__head">',
        '        <h1 class="mg-title">{{ step === \'otp\' ? s(\'otp_title\',\'کد تأیید را وارد کنید\') : (tab === \'register\' ? s(\'register_title\',\'ایجاد حساب جدید\') : s(\'login_title\',\'ورود به حساب\')) }}</h1>',
        '        <p class="mg-subtitle">{{ step === \'otp\' ? $s(s(\'otp_subtitle\',\'کد ارسال‌شده به %s را وارد کنید.\'), maskedPhone) : (tab === \'register\' ? s(\'register_subtitle\',\'با شماره موبایل خود عضو شوید.\') : s(\'login_subtitle\',\'شماره موبایل خود را وارد کنید.\')) }}</p>',
        '      </div>',
        '',
        '      <div class="mg-tabs" v-if="showTabs && step !== \'otp\' && step !== \'success\'" role="tablist">',
        '        <button class="mg-tab" role="tab" :aria-selected="tab === \'login\'" @click="switchTab(\'login\')">{{ s(\'login_tab\',\'ورود\') }}</button>',
        '        <button class="mg-tab" role="tab" :aria-selected="tab === \'register\'" @click="switchTab(\'register\')">{{ s(\'register_tab\',\'ثبت نام\') }}</button>',
        '      </div>',
        '',
        '      <div class="mg-alert mg-alert--error" v-if="error" role="alert"><mg-icon name="alert"></mg-icon><span>{{ error }}</span></div>',
        '',
        '      <form v-if="step === \'phone\'" @submit.prevent="sendCode">',
        '        <div class="mg-field">',
        '          <label class="mg-label" for="mg-phone">{{ s(\'phone_label\',\'شماره موبایل\') }}</label>',
        '          <div class="mg-control" :class="{ \'mg-control--error\': error }">',
         '            <input id="mg-phone" class="mg-input" type="tel" inputmode="numeric" dir="ltr" autocomplete="tel-national" :placeholder="s(\'phone_placeholder\',\'9123456789\')" v-model="phone" @input="resetError">',
         '            <span class="mg-control__icon"><mg-icon name="phone"></mg-icon></span>',
        '            <div class="mg-country" ref="countryRoot">',
        '              <button type="button" class="mg-country__trigger" @click="countryOpen = !countryOpen" :aria-expanded="countryOpen">',
        '                <span class="mg-country__flag">{{ country.flag }}</span>',
        '                <span class="mg-country__code">{{ country.code }}</span>',
        '                <mg-icon name="chevron"></mg-icon>',
        '              </button>',
        '              <div class="mg-country__menu" v-if="countryOpen">',
        '                <input class="mg-country__search" type="text" :placeholder="s(\'search_country\',\'جستجوی کشور…\')" v-model="countryQuery" autofocus>',
        '                <button type="button" class="mg-country__item" v-for="c in filteredCountries" :key="c.iso" :class="{ \'mg-country__item--active\': c.iso === country.iso }" @click="selectCountry(c)">',
        '                  <span class="mg-country__flag">{{ c.flag }}</span><span>{{ c.name }}</span><span class="mg-country__code">{{ c.code }}</span>',
        '                </button>',
        '                <div class="mg-hint" v-if="!filteredCountries.length" style="padding:12px">کشوری یافت نشد</div>',
        '              </div>',
        '            </div>',
        '          </div>',
        '          <span class="mg-hint" v-if="!error">{{ operator ? \'اپراتور: \' + operator : \'مثال: ۹۱۲۳۴۵۶۷۸۹\' }}</span>',
        '        </div>',
        '',
        '        <div class="mg-field" v-if="(config.security && config.security.captcha) && config.security.captcha !== \'none\'" ref="captchaEl">',
        '          <label class="mg-label">بررسی امنیتی</label>',
        '          <div v-if="config.security.captcha === \'internal\'" class="mg-control" style="justify-content:space-between;padding:0 12px">',
        '            <span class="mg-hint" style="font-size:13px;color:var(--mg-fg-1)">{{ captchaQuestion }}</span>',
        '            <input class="mg-input" style="width:70px;flex:0 0 70px;text-align:center" inputmode="numeric" dir="ltr" v-model="captchaAnswer" placeholder="؟">',
        '          </div>',
        '        </div>',
        '',
        '        <label class="mg-checkbox" style="margin-bottom:20px">',
        '          <input type="checkbox" v-model="remember">',
        '          <span class="mg-checkbox__box"><mg-icon name="check"></mg-icon></span>',
        '          <span>{{ s(\'remember_me\',\'مرا به خاطر بسپار\') }}</span>',
        '        </label>',
        '',
        '        <button class="mg-btn mg-btn--primary mg-btn--block mg-btn--lg" type="submit" :disabled="loading">',
        '          <span class="mg-spinner" v-if="loading"></span><mg-icon v-else name="arrow"></mg-icon>',
        '          <span>{{ loading ? s(\'sending\',\'در حال ارسال…\') : s(\'continue\',\'ادامه\') }}</span>',
        '        </button>',
        '',
        '        <div class="mg-actions" v-if="showTabs">',
        '          <span class="mg-hint">{{ tab === \'login\' ? s(\'no_account\',\'حسابی ندارید؟\') : s(\'have_account\',\'حساب دارید؟\') }}</span>',
        '          <button type="button" class="mg-link" @click="switchTab(tab === \'login\' ? \'register\' : \'login\')">{{ tab === \'login\' ? s(\'register_tab\',\'ثبت نام\') : s(\'login_tab\',\'ورود\') }}</button>',
        '        </div>',
        '',
        '        <p class="mg-hint" style="margin-top:18px;text-align:center" v-html="s(\'terms\',\'\')"></p>',
        '      </form>',
        '',
        '      <form v-else-if="step === \'otp\'" @submit.prevent="verify">',
        '        <div class="mg-field">',
        '          <label class="mg-label">{{ s(\'otp_label\',\'کد تأیید\') }}</label>',
        '          <div class="mg-otp" @paste="onOtpPaste">',
        '            <input v-for="(digit, i) in otp" :key="i" :ref="function(el){ otpCells[i] = el }" class="mg-otp__cell" :class="{ \'mg-otp__cell--error\': error }" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" :value="digit" @input="onOtpInput(i, $event)" @keydown="onOtpKeydown(i, $event)">',
        '          </div>',
        '          <span class="mg-hint">کد به شماره {{ maskedPhone }} ارسال شد.</span>',
        '        </div>',
        '',
        '        <button class="mg-btn mg-btn--primary mg-btn--block mg-btn--lg" type="submit" :disabled="loading">',
        '          <span class="mg-spinner" v-if="loading"></span><mg-icon v-else name="lock"></mg-icon>',
        '          <span>{{ loading ? s(\'verifying\',\'در حال بررسی…\') : s(\'verify\',\'تأیید و ورود\') }}</span>',
        '        </button>',
        '',
        '        <div class="mg-actions">',
        '          <button type="button" class="mg-link" @click="backToPhone">{{ s(\'change_number\',\'تغییر شماره\') }}</button>',
        '          <button type="button" class="mg-link" :disabled="countdown > 0" @click="resend">',
        '            {{ countdown > 0 ? $s(s(\'resend_in\',\'ارسال مجدد تا %s ثانیه\'), countdown) : s(\'resend\',\'ارسال مجدد کد\') }}',
        '          </button>',
        '        </div>',
        '      </form>',
        '',
        '      <div class="mg-success" v-else>',
        '        <div class="mg-success__icon"><mg-icon name="success"></mg-icon></div>',
        '        <h2 class="mg-title">{{ successMessage }}</h2>',
        '        <p class="mg-subtitle">در حال انتقال به صفحه مورد نظر…</p>',
        '      </div>',
        '',
        '      <div class="mg-overlay" v-if="loading && step === \'success\'"><span class="mg-spinner" style="width:28px;height:28px"></span></div>',
        '',
        '      <div class="mg-footnote">',
        '        <span class="mg-shield"><mg-icon name="shield"></mg-icon> {{ s(\'powered_by\',\'امن شده با مرزگارد\') }}</span>',
        '      </div>',
        '    </main>',
        '  </div>',
        '</div>'
      ].join('\n')
    };

    var app = createApp(App);

    /* Small template helper: replace %s placeholders */
    app.config.globalProperties.$s = function (text, value) {
      return String(text || '').replace(/%s/g, String(value));
    };

    app.mount(mountEl);
  }

  var mounts = document.querySelectorAll('[data-marzgard-form]');
  Array.prototype.forEach.call(mounts, function (el) {
    try {
      createInstance(el);
    } catch (e) {
      el.innerHTML = '<div class="marzgard-error" style="padding:16px;color:#d61e20">مرزگارد: خطایی در بارگذاری رابط کاربری رخ داد.</div>';
    }
  });
})();
