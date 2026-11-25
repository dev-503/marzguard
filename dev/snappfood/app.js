/* دموی ورود تکنولایف — Vue 3 + کد OTP دمو */
(function () {
  'use strict';

  var FA = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9' };
  var AR = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' };
  function normalize(v) {
    var s = String(v || '').replace(/[۰-۹]/g, function (c) { return FA[c]; }).replace(/[٠-٩]/g, function (c) { return AR[c]; }).replace(/\D/g, '');
    if (s.length === 11 && s[0] === '0') { s = s.slice(1); }
    return s.slice(0, 10);
  }
  function valid(d) { return /^9\d{9}$/.test(d); }
  function format(d) { return !d || d.length <= 3 ? d : d.length <= 6 ? d.slice(0, 3) + ' ' + d.slice(3) : d.slice(0, 3) + ' ' + d.slice(3, 6) + ' ' + d.slice(6); }
  function operator(d) {
    var p = d.slice(0, 3);
    if (!/^9\d{2}$/.test(p)) { return ''; }
    if (/^9(1\d|3[0-3])$/.test(p)) { return 'همراه اول'; }
    if (/^92[0-2]$/.test(p)) { return 'رایتل'; }
    if (/^9(3[5-9]|9[0-6])$/.test(p)) { return 'ایرانسل'; }
    return 'سایر اپراتورها';
  }

  Vue.createApp({
    setup: function () {
      var ref = Vue.ref, computed = Vue.computed, nextTick = Vue.nextTick;
      var step = ref('identity'), tab = ref('login'), phone = ref(''), error = ref('');
      var code = ref(new Array(6).fill('')), cells = [], demoCode = ref(''), masked = ref(''), countdown = ref(0), timer = null;

      var digits = computed(function () { return phone.value; });
      var formatted = computed(function () { return format(digits.value); });
      var isValid = computed(function () { return valid(digits.value); });
      var operatorName = computed(function () { return operator(digits.value); });

      function onPhoneInput(e) { phone.value = normalize(e.target.value); error.value = ''; }
      function startTimer() { countdown.value = 30; clearInterval(timer); timer = setInterval(function () { if (countdown.value > 0) { countdown.value--; } else { clearInterval(timer); } }, 1000); }
      function sendCode() {
        if (!isValid.value) { error.value = digits.value.length === 10 ? 'شماره باید با ۹ شروع شود و ۱۰ رقم باشد' : 'شماره موبایل معتبر نیست'; return; }
        error.value = '';
        demoCode.value = String(Math.floor(100000 + Math.random() * 900000));
        masked.value = '+98 ' + formatted.value;
        code.value = new Array(6).fill('');
        step.value = 'otp';
        startTimer();
        nextTick(function () { if (cells[0]) { cells[0].focus(); } });
      }
      function resend() { if (countdown.value > 0) { return; } demoCode.value = String(Math.floor(100000 + Math.random() * 900000)); code.value = new Array(6).fill(''); startTimer(); }
      function verify() {
        if (code.value.join('').length < 6) { error.value = 'لطفا کد ۶ رقمی را وارد کنید'; return; }
        if (code.value.join('') === demoCode.value) { step.value = 'success'; error.value = ''; }
        else { error.value = 'کد وارد شده نادرست است'; code.value = new Array(6).fill(''); }
      }
      function onInput(i, e) {
        var chars = e.target.value.replace(/\D/g, '').split('');
        if (!chars.length) { code.value[i] = ''; return; }
        chars.forEach(function (c, o) { if (i + o < 6) { code.value[i + o] = c; } });
        nextTick(function () { var n = Math.min(i + chars.length, 5); if (cells[n]) { cells[n].focus(); } });
      }
      function onKeydown(i, e) {
        if (e.key === 'Backspace' && !code.value[i] && i > 0) { cells[i - 1].focus(); }
        else if (e.key === 'ArrowLeft' && i > 0) { e.preventDefault(); cells[i - 1].focus(); }
        else if (e.key === 'ArrowRight' && i < 5) { e.preventDefault(); cells[i + 1].focus(); }
      }
      function onPaste(e) {
        var t = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        if (!t) { return; }
        e.preventDefault();
        t.slice(0, 6).split('').forEach(function (c, i) { code.value[i] = c; });
      }
      function reset() { clearInterval(timer); countdown.value = 0; step.value = 'identity'; phone.value = ''; code.value = new Array(6).fill(''); error.value = ''; demoCode.value = ''; }

      return {
        step: step, tab: tab, formatted: formatted, valid: isValid, operator: operatorName,
        phone: phone, error: error, code: code, cells: cells, demoCode: demoCode, masked: masked, countdown: countdown,
        onPhoneInput: onPhoneInput, sendCode: sendCode, resend: resend, verify: verify,
        onInput: onInput, onKeydown: onKeydown, onPaste: onPaste, reset: reset
      };
    }
  }).mount('#app');
})();
