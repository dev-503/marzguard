/* ============================================================
   مرزگارد | MarzGard — Vue 3 admin settings (headless)

   رابط تنظیمات با الهام از پنل مدیریت سامانه پیامکی:
   نوار تب های آبی، تب فعال سفید، ستون تنظیمات و ستون راهنما.
   ============================================================ */
(function () {
  'use strict';

  if (!window.Vue || !window.MARZGARD) { return; }

  var CFG = window.MARZGARD;
  var API = CFG.restUrl.replace(/\/$/, '');

  var ICONS = {
    settings: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Zm0 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z"/><path d="M10.5 2h3l.5 2.2c.5.2 1 .4 1.4.7l2-.9 2.1 2.1-.9 2c.3.4.5.9.7 1.4L22 10.5v3l-2.2.5c-.2.5-.4 1-.7 1.4l.9 2-2.1 2.1-2-.9c-.4.3-.9.5-1.4.7L13.5 22h-3l-.5-2.2c-.5-.2-1-.4-1.4-.7l-2 .9-2.1-2.1.9-2c-.3-.4-.5-.9-.7-1.4L2 13.5v-3l2.2-.5c.2-.5.4-1 .7-1.4l-.9-2 2.1-2.1 2 .9c.4-.3.9-.5 1.4-.7L10.5 2Z"/>',
    sms: '<path d="M4 3h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm0 2v.5l8 5.2 8-5.2V5H4Zm16 3.3-7.1 4.6a1 1 0 0 1-1.1 0L4 8.3V19h16V8.3Z"/>',
    shield: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3Z"/>',
    lock: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 5a3 3 0 1 1 6 0v3H9V7Z"/>',
    palette: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2a10 10 0 1 0 0 20c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.4-.3-.4-.5-.9-.5-1.4 0-1.1.9-2 2-2h1.5A4.5 4.5 0 0 0 21.5 11C21.5 6 17 2 12 2Zm-5.5 10a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm3-4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm3.5 4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/>',
    spark: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2 9.6 8.4 3 11l6.6 2.6L12 20l2.4-6.4L21 11l-6.6-2.6L12 2Z"/>',
    list: '<path d="M4 5h2v2H4V5Zm4 0h12v2H8V5ZM4 11h2v2H4v-2Zm4 0h12v2H8v-2ZM4 17h2v2H4v-2Zm4 0h12v2H8v-2Z"/>',
    badge: '<path d="m12 2 2.2 5.4L20 8l-4.2 3.7 1.2 5.6L12 14.5 7 17.3l1.2-5.6L4 8l5.8-.6L12 2Z"/>',
    play: '<path d="M8 5v14l11-7L8 5Z"/>',
    info: '<path fill-rule="evenodd" clip-rule="evenodd" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/>',
    code: '<path d="m8.5 7-5 5 5 5 1.4-1.4L6.3 12l3.6-3.6L8.5 7Zm7 0-1.4 1.4L17.7 12l-3.6 3.6 1.4 1.4 5-5-5-5Z"/>'
  };

  var MgIcon = {
    name: 'mg-icon',
    props: { name: String },
    template: '<svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><g v-html="path"></g></svg>',
    computed: { path: function () { return ICONS[this.name] || ''; } }
  };

  var SECRET_KEYS = [ 'sms_password', 'sms_api_key', 'captcha_secret_key' ];

  // Fallback list: the SMS tab must stay usable even if /sms/providers fails.
  var NATIVE_PROVIDERS = {
    log: 'ثبت در لاگ (توسعه)',
    melipayamak: 'ملی پیامک',
    faraz: 'فراز اس ام اس',
    kavenegar: 'کاوه نگار',
    smsir: 'اس ام اس دات آی آر',
    custom: 'REST سفارشی'
  };

  var TABS = [
    { key: 'general', label: 'عمومی', icon: 'settings' },
    { key: 'sms', label: 'پیامک', icon: 'sms' },
    { key: 'security', label: 'امنیت', icon: 'lock' },
    { key: 'appearance', label: 'ظاهر', icon: 'palette' },
    { key: 'advanced', label: 'پیشرفته', icon: 'code' },
    { key: 'retention', label: 'ریتنشن', icon: 'spark' },
    { key: 'logs', label: 'لاگ و توسعه', icon: 'list' },
    { key: 'license', label: 'لایسنس', icon: 'badge' }
  ];

  function request(path, body, method) {
    var opts = {
      method: method || 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || '' }
    };
    if (opts.method !== 'GET') { opts.body = JSON.stringify(body || {}); }
    return fetch(API + path, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok || d.success === false) { throw new Error(d.message || 'request_failed'); }
        return d;
      });
    });
  }

  var BOOL_KEYS = [
    'replace_wp_login','enable_register','auto_register','allow_admin_password',
    'hide_login_errors','log_security_events','dev_expose_otp',
    'rt_abandoned','rt_payment','rt_winback'
  ];

  var SAVE_KEYS = [
    'brand_name','brand_tagline','auth_mode','default_role','redirect_url','replace_wp_login','enable_register','allow_admin_password','auto_register','otp_channel','voice_endpoint','bale_chat_id',
    'sms_gateway','sms_provider','sms_username','sms_password','sms_template_id','sms_param_name','sms_pattern_template','sms_endpoint','sms_method','sms_headers','sms_body','sms_success_path','sms_api_key','sms_sender','sms_template',
    'otp_length','otp_ttl','max_send_per_hour','max_send_per_number','max_verify_tries','resend_wait','captcha_provider','captcha_site_key','captcha_secret_key','recaptcha_version','captcha_min_score','captcha_after_fails','max_ip_fails','lockout_duration','hide_login_errors','log_security_events','blocked_phones','blocked_emails','blocked_ips','dev_expose_otp',
    'rt_abandoned','rt_abandoned_delay','rt_abandoned_msg','rt_payment','rt_payment_delay','rt_payment_msg','rt_winback','rt_winback_days','rt_winback_msg','rt_channel','rt_coupon','rt_max_per_customer','rt_quiet_hours',
    'primary_color','radius','dark_mode','font_size','locale','custom_css'
  ];

  var App = {
    components: { MgIcon: MgIcon },
    setup: function () {
      var form = Vue.reactive({});
      var roles = Vue.ref([]);
      var providers = Vue.ref({});
      var securityLog = Vue.ref([]);
      var rtLog = Vue.ref([]);
      var rtResult = Vue.ref('');
      var tab = Vue.ref('general');
      var loading = Vue.ref(true);
      var saving = Vue.ref(false);
      var status = Vue.ref('');
      var statusType = Vue.ref('');
      var smsTest = Vue.ref('');

      var showAside = Vue.computed(function () {
        return tab.value === 'general' || tab.value === 'logs';
      });

      Vue.onMounted(function () {
        request('/settings', null, 'GET').then(function (data) {
          SAVE_KEYS.forEach(function (key) {
            // وردپرس گزینه های بولی را به صورت ۰/۱ ذخیره میکند.
            form[key] = BOOL_KEYS.indexOf(key) !== -1 ? !!Number(data[key]) : data[key];
          });
          roles.value = data.roles || [];
          loading.value = false;
        }).catch(function (e) {
          status.value = e.message || 'خطا در بارگذاری تنظیمات';
          statusType.value = 'err';
          loading.value = false;
        });
        request('/sms/providers', null, 'GET').then(function (data) {
          var list = {};
          Object.keys(NATIVE_PROVIDERS).forEach(function (key) { list[key] = NATIVE_PROVIDERS[key]; });
          if (Array.isArray(data)) {
            data.forEach(function (item) {
              if (item && item.id) { list[item.id] = item.name || item.id; }
            });
          } else if (data && typeof data === 'object') {
            Object.keys(data).forEach(function (key) {
              if (typeof data[key] === 'string' || typeof data[key] === 'number') { list[key] = String(data[key]); }
            });
          }
          if (form.sms_provider && !Object.prototype.hasOwnProperty.call(list, form.sms_provider)) {
            list[form.sms_provider] = form.sms_provider + ' (ذخیرهشده)';
          }
          providers.value = list;
        }).catch(function () { providers.value = NATIVE_PROVIDERS; });
      });

      function selectTab(key) {
        tab.value = key;
        if (key === 'logs') { loadLog(); }
        if (key === 'retention') { loadRtLog(); }
      }

      function testSms() {
        smsTest.value = 'در حال ارسال…';
        request('/sms/test', { to: form.sms_test_to || '' }).then(function (data) {
          smsTest.value = data.message || 'ارسال شد';
        }).catch(function (e) { smsTest.value = e.message || 'خطا'; });
      }

      function loadLog() {
        request('/security/log', null, 'GET').then(function (data) { securityLog.value = data; }).catch(function () {});
      }

      function clearLog() {
        request('/security/log', {}).then(function () { securityLog.value = []; }).catch(function () {});
      }

      function loadRtLog() {
        request('/retention/log', null, 'GET').then(function (data) { rtLog.value = data; }).catch(function () {});
      }

      function runRt() {
        rtResult.value = 'در حال اجرا…';
        request('/retention/run', {}).then(function (data) {
          rtResult.value = data.message || 'انجام شد';
          loadRtLog();
        }).catch(function (e) { rtResult.value = e.message || 'خطا'; });
      }

      function exportSettings() {
        var payload = {};
        SAVE_KEYS.forEach(function (key) {
          if (SECRET_KEYS.indexOf(key) === -1) { payload[key] = form[key]; }
        });
        var blob = new Blob([ JSON.stringify({ marzgard_settings: payload }, null, 2) ], { type: 'application/json' });
        var url  = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'marzgard-settings.json';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        status.value = 'فایل پشتیبان تنظیمات دانلود شد.';
        statusType.value = 'ok';
      }

      function importSettings(event) {
        var file = event.target.files && event.target.files[0];
        if (!file) { return; }
        var reader = new FileReader();
        reader.onload = function () {
          try {
            var parsed = JSON.parse(reader.result);
            var data = parsed && parsed.marzgard_settings ? parsed.marzgard_settings : parsed;
            var count = 0;
            SAVE_KEYS.forEach(function (key) {
              if (SECRET_KEYS.indexOf(key) !== -1 || !Object.prototype.hasOwnProperty.call(data, key)) { return; }
              form[key] = BOOL_KEYS.indexOf(key) !== -1 ? !!Number(data[key]) : data[key];
              count++;
            });
            status.value = count ? count + ' تنظیمات وارد شد — ذخیره تغییرات را فراموش نکنید.' : 'هیچ تنظیمات معتبری در فایل یافت نشد.';
            statusType.value = count ? 'ok' : 'err';
          } catch (e) {
            status.value = 'فایل پشتیبان معتبر نیست.';
            statusType.value = 'err';
          }
          event.target.value = '';
        };
        reader.readAsText(file);
      }

      function save() {
        saving.value = true;
        status.value = '';
        var payload = {};
        SAVE_KEYS.forEach(function (key) {
          payload[key] = BOOL_KEYS.indexOf(key) !== -1 ? (form[key] ? 1 : 0) : form[key];
        });

        request('/settings', payload).then(function (data) {
          status.value = data.message || 'تنظیمات ذخیره شد.';
          statusType.value = 'ok';
        }).catch(function (e) {
          status.value = e.message || 'خطا در ذخیره تنظیمات';
          statusType.value = 'err';
        }).then(function () { saving.value = false; });
      }

      return {
        form: form, roles: roles, providers: providers, securityLog: securityLog,
        apiBase: API, nonce: CFG.nonce || '', version: CFG.version || '1.0.0',
        demosUrl: CFG.demosUrl || '#', tabs: TABS,
        tab: tab, loading: loading, smsTest: smsTest, rtLog: rtLog, rtResult: rtResult,
        saving: saving, status: status, statusType: statusType, showAside: showAside,
        save: save, selectTab: selectTab, testSms: testSms, loadLog: loadLog, clearLog: clearLog,
        loadRtLog: loadRtLog, runRt: runRt, exportSettings: exportSettings, importSettings: importSettings
      };
    },
    template: `
<div v-if="!loading">
  <header class="mga__topbar">
    <div class="mga__brand">
      <span class="mga__mark"><mg-icon name="shield"></mg-icon></span>
      <div>
        <h1 class="mga__title">مرزگارد</h1>
        <p class="mga__subtitle">تنظیمات ورود و ثبت نام مبتنی بر کد یکبارمصرف</p>
      </div>
    </div>
    <div class="mga__top-actions">
      <span class="mga__badge">نسخه {{ version }}</span>
      <a class="mga__dark-btn" :href="demosUrl"><mg-icon name="play"></mg-icon>دمو لاگین ها</a>
    </div>
  </header>

  <div class="mga__panel">
    <nav class="mga__tabs" aria-label="بخش های تنظیمات">
      <button v-for="t in tabs" :key="t.key" type="button" class="mga__tab"
        :class="{ 'is-active': tab === t.key }"
        :aria-current="tab === t.key ? 'true' : 'false'"
        @click="selectTab(t.key)">
        <span class="mga__tab-icon"><mg-icon :name="t.icon"></mg-icon></span>{{ t.label }}
      </button>
    </nav>

    <div class="mga__layout" :class="{ 'mga__layout--wide': !showAside }">
      <div class="mga__content">

        <!-- General -->
        <section class="mga__section" v-if="tab === 'general'">
          <div class="mga__section-head">
            <h2 class="mga__section-title">هویت و رفتار ورود</h2>
            <p class="mga__section-desc">نام برند و نحوه نمایش فرم احراز هویت را مشخص کنید.</p>
          </div>
          <div class="mga__grid">
            <div class="mga__field"><label class="mga__label">نام برند</label><input class="mga__input" v-model="form.brand_name"></div>
            <div class="mga__field"><label class="mga__label">شعار</label><input class="mga__input" v-model="form.brand_tagline"></div>
            <div class="mga__field"><label class="mga__label">حالت احراز هویت</label>
              <select class="mga__select" v-model="form.auth_mode">
                <option value="login_register">ورود و ثبت نام</option>
                <option value="login">فقط ورود</option>
                <option value="register">فقط ثبت نام</option>
              </select>
            </div>
            <div class="mga__field"><label class="mga__label">نقش پیشفرض کاربران جدید</label>
              <select class="mga__select" v-model="form.default_role"><option v-for="r in roles" :key="r.key" :value="r.key">{{ r.name }}</option></select>
            </div>
            <div class="mga__field mga__grid--full"><label class="mga__label">آدرس هدایت پس از ورود</label>
              <input class="mga__input" dir="ltr" placeholder="https://" v-model="form.redirect_url">
              <span class="mga__help">در صورت خالی بودن، به صفحه اصلی هدایت می شود.</span>
            </div>
          </div>

          <div class="mga__section-head" style="margin-top:24px">
            <h2 class="mga__section-title">قابلیت ها</h2>
          </div>
          <div class="mga__toggle-stack">
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">فعال بودن ثبت نام</strong><span class="mga__help">اجازه ایجاد حساب با شماره جدید</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.enable_register"><span></span></span>
            </label>
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">جایگزینی فرم ورود وردپرس</strong><span class="mga__help">نمایش مرزگارد در صفحه wp-login.php</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.replace_wp_login"><span></span></span>
            </label>
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">ثبت نام خودکار هوشمند</strong><span class="mga__help">ساخت حساب جدید پس از تأیید کد</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.auto_register"><span></span></span>
            </label>
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">ورود مدیران با رمز عبور</strong><span class="mga__help">ورود با ایمیل/نام کاربری و رمز عبور</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.allow_admin_password"><span></span></span>
            </label>
          </div>

          <div class="mga__section-head" style="margin-top:24px">
            <h2 class="mga__section-title">کانال ارسال کد</h2>
          </div>
          <div class="mga__grid">
            <div class="mga__field"><label class="mga__label">کانال ارسال کد</label>
              <select class="mga__select" v-model="form.otp_channel">
                <option value="sms">پیامک</option><option value="email">ایمیل</option>
                <option value="voice">پیام صوتی</option><option value="bale">پیامرسان بله</option>
              </select>
            </div>
            <div class="mga__field"><label class="mga__label">آدرس وبسرویس صوتی</label><input class="mga__input" dir="ltr" v-model="form.voice_endpoint"></div>
            <div class="mga__field mga__grid--full"><label class="mga__label">شناسه چت بله</label><input class="mga__input" dir="ltr" v-model="form.bale_chat_id"></div>
          </div>

          <div class="mga__actionbar">
            <span class="mga__status">خروجی کاربران دارای موبایل</span>
            <span class="mga__row-actions">
              <a class="mg-btn mg-btn--secondary" :href="apiBase + '/export/users?format=csv&nonce=' + nonce">Excel (CSV)</a>
              <a class="mg-btn mg-btn--secondary" :href="apiBase + '/export/users?format=vcf&nonce=' + nonce">VCF مخاطبین</a>
            </span>
          </div>
        </section>

        <!-- SMS -->
        <section class="mga__section" v-if="tab === 'sms'">
          <div class="mga__section-head">
            <h2 class="mga__section-title">سرویس پیامک</h2>
            <p class="mga__section-desc">برای اتصال به هر سرویس دهنده ای، الگوی درخواست REST را مطابق مستندات آن تنظیم کنید.</p>
          </div>
          <div class="mga__grid">
            <div class="mga__field"><label class="mga__label">پروایدر پیامک (همه درگاهها)</label>
              <select class="mga__select" v-model="form.sms_provider"><option v-for="(name, id) in providers" :key="id" :value="id">{{ name }}</option></select>
              <span class="mga__help">پروایدرهای «پیامک ووکامرس فارسی» همچنین قابل انتخابند و کد OTP مرزگارد را ارسال میکنند.</span>
            </div>
            <div class="mga__field"><label class="mga__label">درگاه پیشفرض</label>
              <select class="mga__select" v-model="form.sms_gateway"><option value="log">لاگ (توسعه)</option><option value="rest">REST</option></select>
              <span class="mga__help">در حالت لاگ، پیامک ها تنها در لاگ وردپرس ثبت میشوند.</span>
            </div>
            <div class="mga__field"><label class="mga__label">متد درخواست</label>
              <select class="mga__select" v-model="form.sms_method"><option>POST</option><option>GET</option><option>PUT</option></select>
            </div>
            <div class="mga__field"><label class="mga__label">آدرس سرویس</label><input class="mga__input mga__code" dir="ltr" placeholder="https://api.example.com/send" v-model="form.sms_endpoint"></div>
            <div class="mga__field"><label class="mga__label">کلید API</label><input class="mga__input mga__code" dir="ltr" v-model="form.sms_api_key"></div>
            <div class="mga__field"><label class="mga__label">شماره فرستنده</label><input class="mga__input" dir="ltr" v-model="form.sms_sender"></div>
            <div class="mga__field"><label class="mga__label">نام کاربری سرویس</label><input class="mga__input" dir="ltr" v-model="form.sms_username"></div>
            <div class="mga__field"><label class="mga__label">رمز / توکن سرویس</label><input class="mga__input" type="password" dir="ltr" v-model="form.sms_password"></div>
            <div class="mga__field"><label class="mga__label">شناسه الگوی OTP</label><input class="mga__input" dir="ltr" v-model="form.sms_template_id"></div>
            <div class="mga__field"><label class="mga__label">نام متغیر کد</label><input class="mga__input" dir="ltr" v-model="form.sms_param_name"></div>
            <div class="mga__field"><label class="mga__label">مسیر موفقیت در پاسخ</label><input class="mga__input mga__code" dir="ltr" placeholder="data.success" v-model="form.sms_success_path"></div>
            <div class="mga__field"><label class="mga__label">هدرها (JSON)</label><textarea class="mga__textarea mga__code" dir="ltr" v-model="form.sms_headers"></textarea></div>
            <div class="mga__field"><label class="mga__label">بدنه درخواست (JSON)</label><textarea class="mga__textarea mga__code" dir="ltr" v-model="form.sms_body"></textarea></div>
            <div class="mga__field mga__grid--full"><label class="mga__label">متن پیامک</label>
              <textarea class="mga__textarea" v-model="form.sms_template"></textarea>
              <span class="mga__help">متغیرها: {code}، {minutes}، {brand}</span>
            </div>
            <div class="mga__field mga__grid--full"><label class="mga__label">الگوی پترن (اختیاری)</label>
              <textarea class="mga__textarea mga__code" dir="ltr" v-model="form.sms_pattern_template" placeholder="pattern:12345&#10;code:{code}"></textarea>
              <span class="mga__help">برای درگاههای پترن؛ متغیر {code} جایگزین کد OTP میشود.</span>
            </div>
          </div>
          <div class="mga__actionbar">
            <span class="mga__status">{{ smsTest || 'برای تست، شمارهای وارد کنید' }}</span>
            <span class="mga__row-actions">
              <input class="mga__input" style="width:160px" dir="ltr" v-model="form.sms_test_to" placeholder="09123456789">
              <button class="mg-btn mg-btn--secondary" type="button" @click="testSms">ارسال تست</button>
            </span>
          </div>
        </section>

        <!-- Security -->
        <section class="mga__section" v-if="tab === 'security'">
          <div class="mga__section-head">
            <h2 class="mga__section-title">امنیت کدها و جلوگیری از اس ام اس بمبینگ</h2>
            <p class="mga__section-desc">کدها هش ذخیره میشوند؛ ارسال مجدد هر شماره ۲ دقیقه قفل است و لیمیت ساعتی جداگانه دارد.</p>
          </div>
          <div class="mga__grid">
            <div class="mga__field"><label class="mga__label">طول کد</label><input class="mga__input" type="number" min="4" max="8" v-model.number="form.otp_length"></div>
            <div class="mga__field"><label class="mga__label">اعتبار کد (ثانیه)</label><input class="mga__input" type="number" min="60" max="1800" v-model.number="form.otp_ttl"></div>
            <div class="mga__field"><label class="mga__label">فاصله ارسال مجدد (ثانیه)</label><input class="mga__input" type="number" min="30" v-model.number="form.resend_wait"><span class="mga__help">پیشفرض ۱۲۰ ثانیه (۲ دقیقه)</span></div>
            <div class="mga__field"><label class="mga__label">حداکثر ارسال در ساعت (هر IP)</label><input class="mga__input" type="number" min="1" v-model.number="form.max_send_per_hour"></div>
            <div class="mga__field"><label class="mga__label">حداکثر ارسال در ساعت (هر شماره)</label><input class="mga__input" type="number" min="1" v-model.number="form.max_send_per_number"></div>
            <div class="mga__field"><label class="mga__label">حداکثر تلاش تأیید</label><input class="mga__input" type="number" min="1" v-model.number="form.max_verify_tries"></div>
            <div class="mga__field"><label class="mga__label">حداکثر شکست هر IP</label><input class="mga__input" type="number" min="1" v-model.number="form.max_ip_fails"></div>
            <div class="mga__field"><label class="mga__label">مدت مسدودی (ثانیه)</label><input class="mga__input" type="number" min="60" v-model.number="form.lockout_duration"></div>
          </div>

          <div class="mga__section-head" style="margin-top:24px"><h2 class="mga__section-title">کپچا</h2></div>
          <div class="mga__grid">
            <div class="mga__field"><label class="mga__label">سرویس کپچا</label>
              <select class="mga__select" v-model="form.captcha_provider"><option value="none">غیرفعال</option><option value="internal">کپچای داخلی (جمع)</option><option value="recaptcha">Google reCAPTCHA</option><option value="turnstile">Cloudflare Turnstile</option><option value="arcaptcha">آرکپچا</option></select>
            </div>
            <div class="mga__field"><label class="mga__label">نسخه reCAPTCHA</label><select class="mga__select" v-model="form.recaptcha_version"><option value="v2">v2</option><option value="v3">v3 (امتیاز)</option></select></div>
            <div class="mga__field"><label class="mga__label">Site Key</label><input class="mga__input" dir="ltr" v-model="form.captcha_site_key"></div>
            <div class="mga__field"><label class="mga__label">Secret Key</label><input class="mga__input" type="password" dir="ltr" v-model="form.captcha_secret_key"></div>
            <div class="mga__field"><label class="mga__label">حداقل امتیاز v3</label><input class="mga__input" type="number" step="0.1" min="0" max="1" v-model.number="form.captcha_min_score"></div>
            <div class="mga__field"><label class="mga__label">فعال شدن کپچا پس از چند شکست</label><input class="mga__input" type="number" min="0" v-model.number="form.captcha_after_fails"></div>
          </div>

          <div class="mga__section-head" style="margin-top:24px"><h2 class="mga__section-title">مسدودسازی</h2></div>
          <div class="mga__grid">
            <div class="mga__field"><label class="mga__label">شمارههای مسدود (هر سطر یک شماره)</label><textarea class="mga__textarea" dir="ltr" v-model="form.blocked_phones"></textarea></div>
            <div class="mga__field"><label class="mga__label">ایمیلهای مسدود</label><textarea class="mga__textarea" dir="ltr" v-model="form.blocked_emails"></textarea></div>
            <div class="mga__field mga__grid--full"><label class="mga__label">آدرسهای IP مسدود</label><textarea class="mga__textarea" dir="ltr" v-model="form.blocked_ips"></textarea></div>
          </div>

          <div class="mga__section-head" style="margin-top:24px"><h2 class="mga__section-title">سایر تنظیمات امنیتی</h2></div>
          <div class="mga__toggle-stack">
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">پنهان کردن خطاهای ورود</strong><span class="mga__help">نمایش پیام عمومی به جای جزئیات</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.hide_login_errors"><span></span></span>
            </label>
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">لاگ رویدادهای امنیتی</strong><span class="mga__help">ذخیره ۲۰۰ رویداد آخر — از تب «لاگ و توسعه» قابل مشاهده است</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.log_security_events"><span></span></span>
            </label>
          </div>
        </section>

        <!-- Retention -->
        <section class="mga__section" v-if="tab === 'retention'">
          <div class="mga__section-head">
            <h2 class="mga__section-title">ریتنشن مارکتینگ</h2>
            <p class="mga__section-desc">کمپینهای خودکار سبد رها شده، یادآوری پرداخت و بازگشت مشتری — از طریق همان پروایدر پیامک مرزگارد.</p>
          </div>
          <div class="mga__grid">
            <div class="mga__field"><label class="mga__label">کانال ارسال</label><select class="mga__select" v-model="form.rt_channel"><option value="sms">پیامک</option><option value="email">ایمیل</option><option value="bale">بله</option></select></div>
            <div class="mga__field"><label class="mga__label">کد تخفیف کمپین بازگشت</label><input class="mga__input" dir="ltr" v-model="form.rt_coupon"></div>
            <div class="mga__field"><label class="mga__label">حداکثر پیام به هر مشتری</label><input class="mga__input" type="number" min="1" v-model.number="form.rt_max_per_customer"></div>
            <div class="mga__field"><label class="mga__label">ساعات خاموش (۲۳ تا ۸)</label><select class="mga__select" v-model.number="form.rt_quiet_hours"><option :value="0">غیرفعال</option><option :value="1">فعال</option></select></div>
          </div>
          <div class="mga__toggle-stack" style="margin-top:22px">
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">کمپین سبد رها شده</strong><span class="mga__help">پس از تاخیر تنظیمشده (دقیقه) — لینک بازیابی سبد</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.rt_abandoned"><span></span></span>
            </label>
            <div class="mga__field"><input class="mga__input" type="number" min="5" v-model.number="form.rt_abandoned_delay" aria-label="تاخیر سبد رها شده"><textarea class="mga__textarea" v-model="form.rt_abandoned_msg"></textarea><span class="mga__help">متغیرها: site , link</span></div>
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">یادآوری پرداخت</strong><span class="mga__help">سفارشهای در انتظار پرداخت</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.rt_payment"><span></span></span>
            </label>
            <div class="mga__field"><input class="mga__input" type="number" min="1" v-model.number="form.rt_payment_delay" aria-label="تاخیر یادآوری پرداخت"><textarea class="mga__textarea" v-model="form.rt_payment_msg"></textarea><span class="mga__help">متغیرها: order , link</span></div>
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">کمپین بازگشت مشتری</strong><span class="mga__help">مشتریان بدون خرید اخیر</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.rt_winback"><span></span></span>
            </label>
            <div class="mga__field"><input class="mga__input" type="number" min="7" v-model.number="form.rt_winback_days" aria-label="روزهای عدم خرید"><textarea class="mga__textarea" v-model="form.rt_winback_msg"></textarea><span class="mga__help">متغیرها: coupon , site</span></div>
          </div>
          <div class="mga__actionbar" style="margin-top:22px">
            <span class="mga__status">{{ rtResult || (rtLog.length + ' پیام ارسالشده') }}</span>
            <button class="mg-btn mg-btn--primary" type="button" @click="runRt">اجرای دستی کمپینها</button>
          </div>
          <div class="mga__log" style="margin-top:12px">
            <div class="mga__log-row" v-for="(e, i) in rtLog" :key="i"><span>{{ new Date(e.time * 1000).toLocaleString('fa-IR') }}</span><span>{{ e.type }} — {{ e.to }}</span><span>{{ e.status }}</span></div>
            <div class="mga__empty" v-if="!rtLog.length">پیامی ارسال نشده است.</div>
          </div>
        </section>

        <!-- Appearance -->
        <section class="mga__section" v-if="tab === 'appearance'">
          <div class="mga__section-head">
            <h2 class="mga__section-title">ظاهر رابط کاربری</h2>
            <p class="mga__section-desc">منطبق با توکن های دیزاین سیستم سرخآب.</p>
          </div>
          <div class="mga__grid">
            <div class="mga__field"><label class="mga__label">رنگ اصلی</label><div class="mga__color"><input type="color" v-model="form.primary_color"><input class="mga__input mga__code" dir="ltr" v-model="form.primary_color"></div></div>
            <div class="mga__field"><label class="mga__label">شعاع گوشه ها (px)</label><input class="mga__input" type="number" min="0" max="24" v-model.number="form.radius"></div>
            <div class="mga__field"><label class="mga__label">تم</label><select class="mga__select" v-model="form.dark_mode"><option value="auto">خودکار</option><option value="light">روشن</option><option value="dark">تاریک</option></select></div>
            <div class="mga__field"><label class="mga__label">اندازه فونت (px)</label><input class="mga__input" type="number" min="12" max="20" v-model.number="form.font_size"></div>
          </div>
          <div class="mga__note"><mg-icon name="info"></mg-icon><span>فونت مورد استفاده: آراد (Arad Variable) — فونت پیشفرض دیزاین سیستم سرخآب.</span></div>
        </section>

        <!-- Advanced -->
        <section class="mga__section" v-if="tab === 'advanced'">
          <div class="mga__section-head">
            <h2 class="mga__section-title">پشتیبان گیری و انتقال تنظیمات</h2>
            <p class="mga__section-desc">تنظیمات را در یک فایل JSON ذخیره یا بازگردانی کنید. رمزها و کلیدهای API در فایل پشتیبان ذخیره نمیشوند.</p>
          </div>
          <div class="mga__actionbar">
            <span class="mga__status">پشتیبان فقط شامل تنظیمات مرزگارد است.</span>
            <span class="mga__row-actions">
              <button class="mg-btn mg-btn--secondary" type="button" @click="exportSettings">دانلود پشتیبان (JSON)</button>
              <label class="mg-btn mg-btn--secondary" style="cursor:pointer">وارد کردن پشتیبان<input type="file" accept="application/json" style="display:none" @change="importSettings"></label>
            </span>
          </div>

          <div class="mga__section-head" style="margin-top:24px">
            <h2 class="mga__section-title">CSS سفارشی</h2>
            <p class="mga__section-desc">استایل های شما فقط روی فرم های مرزگارد اعمال میشود و تگ های HTML حذف میگردند.</p>
          </div>
          <textarea class="mga__textarea mga__code" dir="ltr" v-model="form.custom_css" placeholder=".mg-card { border-radius: 20px; }" rows="8"></textarea>
        </section>

        <!-- Logs & development -->
        <section class="mga__section" v-if="tab === 'logs'">
          <div class="mga__section-head">
            <h2 class="mga__section-title">لاگ امنیتی</h2>
            <p class="mga__section-desc">آخرین ۲۰۰ رویداد امنیتی ثبتشده توسط مرزگارد.</p>
          </div>
          <div class="mga__actionbar">
            <span class="mga__status">{{ securityLog.length }} رویداد ثبت شده</span>
            <button class="mg-btn mg-btn--ghost" type="button" @click="clearLog">پاک کردن لاگ</button>
          </div>
          <div class="mga__log" style="margin-top:12px">
            <div class="mga__log-row" v-for="(e, i) in securityLog" :key="i"><span>{{ new Date(e.time * 1000).toLocaleString('fa-IR') }}</span><span>{{ e.type }}</span><span dir="ltr">{{ e.ip }}</span></div>
            <div class="mga__empty" v-if="!securityLog.length">رویدادی ثبت نشده است.</div>
          </div>

          <div class="mga__section-head" style="margin-top:24px">
            <h2 class="mga__section-title">حالت توسعه</h2>
            <p class="mga__section-desc">نمایش کد OTP در پاسخ API — تنها در محیط توسعه فعال کنید.</p>
          </div>
          <div class="mga__toggle-stack">
            <label class="mga__toggle-row">
              <span><strong class="mga__toggle-title">نمایش کد در پاسخ API</strong><span class="mga__help">کد تأیید را در پاسخ otp/send برمیگرداند</span></span>
              <span class="mga__switch"><input type="checkbox" v-model="form.dev_expose_otp"><span></span></span>
            </label>
          </div>
        </section>

        <!-- License / about -->
        <section class="mga__section" v-if="tab === 'license'">
          <div class="mga__section-head">
            <h2 class="mga__section-title">درباره مرزگارد</h2>
            <p class="mga__section-desc">بازنویسی و بازطراحی افزونه دیجیتس به عنوان یک افزونه مستقل و هدلس برای وردپرس.</p>
          </div>
          <div class="mga__about">
            <div class="mga__about-row"><span>نسخه</span><strong>{{ version }}</strong></div>
            <div class="mga__about-row"><span>مجوز</span><strong dir="ltr">GPL-2.0-or-later</strong></div>
            <div class="mga__about-row"><span>دیزاین سیستم</span><strong>سرخآب — آروان</strong></div>
            <div class="mga__about-row"><span>فونت</span><strong>آراد (Arad Variable)</strong></div>
            <div class="mga__about-row"><span>زبانها</span><strong>فارسی (RTL)</strong></div>
          </div>
          <div class="mga__actionbar">
            <span class="mga__status">دموها و پیش نمایش های رابط کاربری</span>
            <a class="mg-btn mg-btn--secondary" :href="demosUrl">دمو لاگین ها</a>
          </div>
        </section>

      </div>

      <aside class="mga__aside" v-if="showAside">
        <div class="mga__promo">
          <span class="mga__promo-icon"><mg-icon name="spark"></mg-icon></span>
          <h3>پوسته دیجی کالا در مرزگارد</h3>
          <p>برای نمایش فرم ورود با پوسته دیجی کالا، کافی است شورت کد اختصاصی را در صفحه دلخواه درج کنید.</p>
          <a class="mga__promo-link" :href="demosUrl">مشاهده دمو ↗</a>
          <div class="mga__coupon">[marzgard_digikala]</div>
        </div>

        <div class="mga__guide">
          <h3>راهنمای استفاده</h3>
          <ol>
            <li>در تب <button type="button" class="mga__link-btn" @click="selectTab('sms')">پیامک</button>، درگاه ارسال کد را پیکربندی کنید.</li>
            <li>شورت کد <code>[marzgard_form]</code> را در صفحه دلخواه قرار دهید.</li>
            <li>در تب <button type="button" class="mga__link-btn" @click="selectTab('security')">امنیت</button>، محدودیت های ارسال را تنظیم کنید.</li>
            <li>در نهایت <strong>ذخیره تغییرات</strong> را بزنید تا تنظیمات اعمال شوند.</li>
          </ol>
        </div>

        <div class="mga__quick">
          <h3>دسترسی سریع</h3>
          <div class="mga__quick-item"><span>وضعیت درگاه</span><span>{{ form.sms_gateway === 'log' ? 'توسعه (لاگ)' : 'REST' }}</span></div>
          <div class="mga__quick-item"><span>کانال کد</span><span>{{ form.otp_channel || 'sms' }}</span></div>
          <div class="mga__quick-item"><span>دمو لاگین ها</span><a :href="demosUrl">مشاهده ↗</a></div>
        </div>
      </aside>
    </div>

    <div class="mga__savebar">
      <button class="mg-btn mg-btn--primary" type="button" :disabled="saving" @click="save">
        <span class="mg-spinner" v-if="saving"></span>
        <span v-else>ذخیره تغییرات</span>
      </button>
      <span class="mga__status" :class="{ 'mga__status--ok': statusType === 'ok', 'mga__status--err': statusType === 'err' }">
        {{ status || 'تغییرات خود را ذخیره کنید' }}
      </span>
    </div>
  </div>
</div>
`
  };

  var el = document.getElementById('marzgard-admin-app');
  if (el) {
    var app = Vue.createApp(App);
    app.mount(el);
  }
})();
