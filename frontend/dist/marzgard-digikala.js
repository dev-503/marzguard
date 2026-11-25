/* ============================================================
   مرزگارد — Digikala-style Vue 3 client (shortcode / Elementor)
   Talks to the real MarzGard REST API.
   ============================================================ */
(function () {
  'use strict';

  if (!window.Vue || !window.MARZGARD) { return; }

  var ref = Vue.ref;
  var computed = Vue.computed;
  var nextTick = Vue.nextTick;
  var onBeforeUnmount = Vue.onBeforeUnmount;
  var CFG = window.MARZGARD;
  var API = CFG.restUrl.replace(/\/$/, '');

  /* --- Persian mobile helpers (port of PhoneInput utils) --- */
  var FA_MAP = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9' };
  var AR_MAP = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' };

  function toLatinDigits(text) {
    return String(text || '').replace(/[۰-۹]/g, function (c) { return FA_MAP[c]; }).replace(/[٠-٩]/g, function (c) { return AR_MAP[c]; });
  }

  function normalizeIranMobile(value) {
    var s = toLatinDigits(value).replace(/\D/g, '');
    if (s.length === 11 && s.charAt(0) === '0') { s = s.slice(1); }
    if (s.length > 10) { s = s.slice(0, 10); }
    return s;
  }

  function isIranMobile(digits) { return /^9\d{9}$/.test(digits); }

  function formatIranMobile(digits) {
    if (!digits || digits.length <= 3) { return digits; }
    if (digits.length <= 6) { return digits.slice(0, 3) + ' ' + digits.slice(3); }
    return digits.slice(0, 3) + ' ' + digits.slice(3, 6) + ' ' + digits.slice(6, 10);
  }

  function mobileOperator(digits) {
    var p = digits.slice(0, 3);
    if (!/^9\d{2}$/.test(p)) { return ''; }
    if (/^9(1\d|3[0-3])$/.test(p)) { return 'همراه اول'; }
    if (/^92[0-2]$/.test(p)) { return 'رایتل'; }
    if (/^9(3[5-9]|9[0-6])$/.test(p)) { return 'ایرانسل'; }
    return 'سایر اپراتورها';
  }

  function request(path, body) {
    return fetch(API + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || '' },
      body: JSON.stringify(body || {})
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok || d.success === false) {
          var e = new Error(d.message || 'request_failed');
          e.code = d.code || '';
          throw e;
        }
        return d;
      });
    });
  }

  var mounts = document.querySelectorAll('[data-marzgard-digikala]');

  Array.prototype.forEach.call(mounts, function (mountEl) {
    var d = mountEl.dataset || {};

    var App = {
      setup: function () {
        var step = ref('identity');
        var identity = ref('');
        var error = ref('');
        var loading = ref(false);
        var code = ref(new Array(6).fill(''));
        var cells = [];
        var demoCode = ref('');
        var masked = ref('');
        var challenge = ref('');
        var countdown = ref(0);
        var timer = null;

        var labels = {
          title: d.title || 'ورود یا ثبتنام در دیجیکالا',
          subtitle: d.subtitle || 'لطفا شماره موبایل یا ایمیل خود را وارد کنید',
          placeholder: d.placeholder || 'شماره موبایل یا پست الکترونیک',
          button: d.button || 'ورود دیجیکالا',
          otpTitle: d['otpTitle'] || d['otp-title'] || 'تایید شماره موبایل',
          otpButton: d['otpButton'] || d['otp-button'] || 'تایید و ورود',
          terms: d.terms || 'ورود به معنای پذیرش شرایط و قوانین حریم خصوصی است',
          phoneLabel: d['phoneLabel'] || d['phone-label'] || 'شماره موبایل',
          showLogo: d.showLogo === '1' || d['show-logo'] === '1'
        };

        var valid = computed(function () { return isIranMobile(identity.value); });
        var formatted = computed(function () { return formatIranMobile(identity.value); });
        var operator = computed(function () { return mobileOperator(identity.value); });

        function onPhoneInput(event) {
          identity.value = normalizeIranMobile(event.target.value);
          error.value = '';
        }

        function startTimer() {
          countdown.value = 30;
          clearInterval(timer);
          timer = setInterval(function () {
            if (countdown.value > 0) { countdown.value--; } else { clearInterval(timer); }
          }, 1000);
        }

        function sendCode() {
          var value = identity.value.trim();
          if (!value) { error.value = 'لطفا شماره موبایل یا ایمیل خود را وارد کنید'; return; }

          var isEmail = value.indexOf('@') !== -1;
          if (!isEmail && !isIranMobile(identity.value)) {
            error.value = identity.value.length === 10
              ? 'شماره باید با ۹ شروع شود و ۱۰ رقم باشد'
              : 'شماره موبایل معتبر نیست';
            return;
          }

          loading.value = true;
          error.value = '';

          var payload = {
            purpose: d.mode || 'login',
            website: ''
          };
          if (isEmail) {
            payload.email = value;
          } else {
            payload.phone = identity.value;
            payload.country_code = '+98';
          }

          request('/otp/send', payload).then(function (data) {
            challenge.value = data.challenge;
            masked.value = data.masked || value;
            demoCode.value = data.dev_code || '';
            code.value = new Array(6).fill('');
            step.value = 'otp';
            startTimer();
            nextTick(function () { if (cells[0]) { cells[0].focus(); } });
          }).catch(function (err) {
            error.value = err.message || 'خطایی رخ داد. لطفا دوباره تلاش کنید.';
          }).then(function () { loading.value = false; });
        }

        function resend() {
          if (countdown.value > 0 || !challenge.value) { return; }
          request('/otp/resend', { challenge: challenge.value }).then(function (data) {
            demoCode.value = data.dev_code || '';
            code.value = new Array(6).fill('');
            startTimer();
            nextTick(function () { if (cells[0]) { cells[0].focus(); } });
          }).catch(function (err) {
            error.value = err.message || 'خطایی رخ داد.';
          });
        }

        function verify() {
          var value = code.value.join('');
          if (value.length < 6) { error.value = 'لطفا کد ۶ رقمی را وارد کنید'; return; }
          loading.value = true;
          error.value = '';

          request('/otp/verify', {
            challenge: challenge.value,
            otp: value,
            remember: true,
            redirect: d.redirect || ''
          }).then(function (data) {
            step.value = 'success';
            if (data.redirect) { setTimeout(function () { window.location.href = data.redirect; }, 900); }
          }).catch(function (err) {
            error.value = err.message || 'کد وارد شده نادرست است';
            code.value = new Array(6).fill('');
            nextTick(function () { if (cells[0]) { cells[0].focus(); } });
          }).then(function () { loading.value = false; });
        }

        function onInput(index, event) {
          var chars = event.target.value.replace(/[^0-9]/g, '').split('');
          if (!chars.length) { code.value[index] = ''; return; }
          chars.forEach(function (ch, offset) {
            var i = index + offset;
            if (i < code.value.length) { code.value[i] = ch; }
          });
          var next = Math.min(index + chars.length, code.value.length - 1);
          nextTick(function () { if (cells[next]) { cells[next].focus(); } });
        }

        function onKeydown(index, event) {
          if (event.key === 'Backspace' && !code.value[index] && index > 0) {
            cells[index - 1].focus();
          } else if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault(); cells[index - 1].focus();
          } else if (event.key === 'ArrowRight' && index < code.value.length - 1) {
            event.preventDefault(); cells[index + 1].focus();
          }
        }

        function onPaste(event) {
          var text = (event.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
          if (!text) { return; }
          event.preventDefault();
          text.slice(0, 6).split('').forEach(function (ch, i) { code.value[i] = ch; });
          nextTick(function () { if (cells[Math.min(text.length, 5)]) { cells[Math.min(text.length, 5)].focus(); } });
        }

        function reset() {
          clearInterval(timer);
          countdown.value = 0;
          step.value = 'identity';
          identity.value = '';
          code.value = new Array(6).fill('');
          error.value = '';
          demoCode.value = '';
          challenge.value = '';
        }

        onBeforeUnmount(function () { clearInterval(timer); });

        return {
          step: step, identity: identity, error: error, loading: loading,
          code: code, cells: cells, demoCode: demoCode, masked: masked,
          countdown: countdown, labels: labels,
          valid: valid, formatted: formatted, operator: operator,
          sendCode: sendCode, resend: resend, verify: verify,
          onPhoneInput: onPhoneInput,
          onInput: onInput, onKeydown: onKeydown, onPaste: onPaste, reset: reset
        };
      },
      template: [
        '<div class="dk-card">',
        '  <div class="dk-card__head">',
        '    <button class="dk-back" type="button" @click="reset" aria-label="بازگشت"><svg viewBox="0 0 24 24" fill="none"><path d="M5 12h14m0 0-6-6m6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>',
        '    <div class="dk-logo" v-if="labels.showLogo"><span class="dk-logo__text">دیجی کالا</span><svg class="dk-logo__smile" viewBox="0 0 64 32"><path d="M6 5c4 16 48 16 52 0" stroke="currentColor" stroke-width="9" stroke-linecap="round" fill="none"/></svg></div>',
        '  </div>',
        '',
        '  <form v-if="step === \'identity\'" @submit.prevent="sendCode" class="dk-body">',
        '    <h1 class="dk-title">{{ labels.title }}</h1>',
        '    <p class="dk-subtitle">{{ labels.subtitle }}</p>',
        '    <div class="dk-field">',
        '      <label class="dk-label" for="dk-phone">{{ labels.phoneLabel }}</label>',
        '      <div class="dk-phone" dir="rtl" :class="{ \'dk-phone--error\': error }">',
        '        <span class="dk-phone__addon" dir="ltr"><span class="dk-phone__flag">🇮🇷</span> +98 <span class="dk-phone__iso">IR</span></span>',
        '        <input id="dk-phone" class="dk-phone__input" type="tel" inputmode="tel" dir="ltr" autocomplete="tel-national" :placeholder="labels.placeholder" :value="formatted" @input="onPhoneInput" :aria-invalid="error ? true : undefined">',
        '        <svg v-if="valid" class="dk-phone__check" viewBox="0 0 24 24" fill="none"><path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        '      </div>',
        '      <p class="dk-error" v-if="error">{{ error }}</p>',
        '      <p class="dk-hint" v-else-if="operator">اپراتور: {{ operator }}</p>',
        '      <p class="dk-hint" v-else>بدون صفر اول هم می‌توانید وارد کنید</p>',
        '    </div>',
        '    <button class="dk-btn" type="submit" :disabled="loading"><span class="dk-spinner" v-if="loading"></span><span v-else>{{ labels.button }}</span></button>',
        '    <p class="dk-terms">{{ labels.terms }}</p>',
        '  </form>',
        '',
        '  <form v-else-if="step === \'otp\'" @submit.prevent="verify" class="dk-body">',
        '    <h1 class="dk-title">{{ labels.otpTitle }}</h1>',
        '    <p class="dk-subtitle">کد ۶ رقمی ارسال شده به <b dir="ltr">{{ masked }}</b> را وارد کنید</p>',
        '    <div class="dk-field">',
        '      <div class="dk-otp" @paste="onPaste">',
        '        <input v-for="(digit, i) in code" :key="i" :ref="function(el){ cells[i] = el }" class="dk-otp__cell" :class="{ \'dk-otp__cell--error\': error }" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" dir="ltr" :value="digit" @input="onInput(i, $event)" @keydown="onKeydown(i, $event)">',
        '      </div>',
        '      <p class="dk-error" v-if="error">{{ error }}</p>',
        '      <p class="dk-demo-code" v-if="demoCode">کد دمو: <b dir="ltr">{{ demoCode }}</b></p>',
        '    </div>',
        '    <button class="dk-btn" type="submit" :disabled="loading"><span class="dk-spinner" v-if="loading"></span><span v-else>{{ labels.otpButton }}</span></button>',
        '    <div class="dk-actions">',
        '      <button class="dk-link" type="button" @click="reset">ویرایش شماره</button>',
        '      <button class="dk-link" type="button" :disabled="countdown > 0" @click="resend">{{ countdown > 0 ? \'ارسال مجدد تا \' + countdown + \' ثانیه\' : \'ارسال مجدد کد\' }}</button>',
        '    </div>',
        '  </form>',
        '',
        '  <div v-else class="dk-body dk-success">',
        '    <div class="dk-success__icon"><svg viewBox="0 0 24 24" fill="none"><path d="m5 12.5 4.5 4.5L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>',
        '    <h1 class="dk-title">با موفقیت وارد شدید</h1>',
        '    <p class="dk-subtitle">به مرزگارد خوش آمدید :)</p>',
        '    <button class="dk-btn" type="button" @click="reset">بازگشت به صفحه ورود</button>',
        '  </div>',
        '</div>'
      ].join('\n')
    };

    Vue.createApp(App).mount(mountEl);
  });
})();
