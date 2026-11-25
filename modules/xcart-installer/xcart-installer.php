<?php
/*
 * Plugin Name: XCart Installer
 * Plugin URI: https://xcart.ir/
 * Description: نصب کننده پلاگین ایکس کارت
 * Version: 1.0.0
 * Author: XCart
 * Author URI: https://xcart.ir/
 * Requires PHP: 7.4
 * Text Domain: xcart-installer
 *
 * @package XCartInstaller
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XCART_INSTALLER_VERSION', '1.0.0');
define('XCART_INSTALLER_PACKAGE_URL', 'https://xcart.ir/upserver/xcart.zip');
define('XCART_INSTALLER_PACKAGE_SHA256', '');
define('XCART_INSTALLER_ALLOWED_PACKAGE_HOST', 'xcart.ir');
define('XCART_INSTALLER_MAX_PACKAGE_BYTES', 31457280);
define('XCART_INSTALLER_MAIN_PLUGIN', 'xcart/xcart.php');
define('XCART_INSTALLER_PAGE_SLUG', 'xcart-installer');
define('XCART_INSTALLER_REDIRECT_OPTION', 'xcart_installer_do_redirect');
define('XCART_INSTALLER_LOCK_KEY', 'xcart_installer_install_lock');

function xcart_installer_admin_url($path = '')
{
    if (is_network_admin()) {
        return network_admin_url($path);
    }

    return admin_url($path);
}

function xcart_installer_activate()
{
    update_option(XCART_INSTALLER_REDIRECT_OPTION, '1', false);
}
register_activation_hook(__FILE__, 'xcart_installer_activate');

function xcart_installer_admin_menu()
{
    add_submenu_page(
        'marzgard',
        'نصب کننده ایکس کارت',
        'ایکس کارت (نصب)',
        'install_plugins',
        XCART_INSTALLER_PAGE_SLUG,
        'xcart_installer_render_page'
    );
}
add_action('admin_menu', 'xcart_installer_admin_menu');
add_action('network_admin_menu', 'xcart_installer_admin_menu');

function xcart_installer_admin_body_class($classes)
{
    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
    if ($page === XCART_INSTALLER_PAGE_SLUG) {
        $classes .= ' xcart-installer-admin';
    }

    return $classes;
}
add_filter('admin_body_class', 'xcart_installer_admin_body_class');

function xcart_installer_is_page()
{
    $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

    return $page === XCART_INSTALLER_PAGE_SLUG;
}

function xcart_installer_silence_admin_notices()
{
    if (!xcart_installer_is_page()) {
        return;
    }

    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
    remove_all_actions('network_admin_notices');
    remove_all_actions('user_admin_notices');
}
add_action('admin_init', 'xcart_installer_silence_admin_notices', 999);
add_action('current_screen', 'xcart_installer_silence_admin_notices', 999);

function xcart_installer_maybe_redirect()
{
    if (!current_user_can('install_plugins')) {
        return;
    }

    if (wp_doing_ajax() || (defined('WP_CLI') && WP_CLI)) {
        return;
    }

    if (get_option(XCART_INSTALLER_REDIRECT_OPTION) !== '1') {
        return;
    }

    delete_option(XCART_INSTALLER_REDIRECT_OPTION);

    wp_safe_redirect(xcart_installer_admin_url('plugins.php?page=' . XCART_INSTALLER_PAGE_SLUG));
    exit;
}
add_action('admin_init', 'xcart_installer_maybe_redirect');

function xcart_installer_render_page()
{
    if (!current_user_can('install_plugins')) {
        wp_die(esc_html__('شما دسترسی نصب افزونه را ندارید.', 'xcart-installer'));
    }

    $nonce = wp_create_nonce('xcart_installer_run');
    $ajax_url = admin_url('admin-ajax.php');
    $done_url = admin_url('admin.php?page=xcart&action=xcart_starter&xcart_step=0');
    $plugins_url = xcart_installer_admin_url('plugins.php');
    ?>
    <div class="wrap xcart-installer-wrap" dir="rtl">
        <style>
            body.xcart-installer-admin #wpbody-content > .notice,
            body.xcart-installer-admin #wpbody-content > .updated,
            body.xcart-installer-admin #wpbody-content > .error,
            body.xcart-installer-admin .wrap > .notice,
            body.xcart-installer-admin .wrap > .updated,
            body.xcart-installer-admin .wrap > .error,
            body.xcart-installer-admin .xcart-installer-wrap .notice,
            body.xcart-installer-admin .xcart-installer-wrap .updated,
            body.xcart-installer-admin .xcart-installer-wrap .error,
            body.xcart-installer-admin .xcart-installer-wrap [class*="notice"],
            body.xcart-installer-admin .xcart-installer-wrap .woocommerce-message,
            body.xcart-installer-admin .xcart-installer-wrap .woocommerce-error,
            body.xcart-installer-admin .xcart-installer-wrap .woocommerce-info {
                display: none !important;
            }
            .xcart-installer-wrap {
                min-height: calc(100vh - 140px);
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                font-family: Tahoma, Arial, sans-serif;
            }
            .xcart-installer-box {
                width: min(720px, calc(100vw - 72px));
                padding: 0;
                overflow: hidden;
                border: 1px solid #d8dee8;
                background: #ffffff;
                border-radius: 14px;
                box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
            }
            .xcart-installer-head {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 24px 28px;
                background: linear-gradient(135deg, #0f172a 0%, #1f2937 55%, #0b5cab 100%);
                color: #fff;
            }
            .xcart-installer-logo {
                display: grid;
                width: 48px;
                height: 48px;
                place-items: center;
                border-radius: 12px;
                background: rgba(255, 255, 255, .12);
                border: 1px solid rgba(255, 255, 255, .18);
                font-size: 18px;
                font-weight: 700;
                letter-spacing: 0;
            }
            .xcart-installer-title {
                margin: 0;
                font-size: 23px;
                line-height: 1.3;
                color: #fff;
            }
            .xcart-installer-subtitle {
                margin: 5px 0 0;
                color: rgba(255, 255, 255, .76);
                font-size: 13px;
            }
            .xcart-installer-body {
                padding: 26px 28px 28px;
            }
            .xcart-installer-status {
                display: flex;
                align-items: center;
                gap: 10px;
                margin: 0;
                color: #1f2937;
                font-size: 15px;
                font-weight: 600;
            }
            .xcart-installer-spinner {
                display: inline-block;
                flex: 0 0 auto;
                width: 20px;
                height: 20px;
                border: 3px solid #dbeafe;
                border-top-color: #2563eb;
                border-radius: 50%;
                animation: xcart-installer-spin .8s linear infinite;
            }
            .xcart-installer-progress {
                height: 8px;
                margin-top: 22px;
                overflow: hidden;
                border-radius: 999px;
                background: #e5e7eb;
            }
            .xcart-installer-progress-bar {
                width: 18%;
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2563eb, #16a34a);
                transition: width .45s ease;
            }
            .xcart-installer-hints {
                position: relative;
                min-height: 116px;
                margin-top: 22px;
                padding: 18px 20px;
                overflow: hidden;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: linear-gradient(180deg, #ffffff, #f8fafc);
            }
            .xcart-installer-hint {
                position: absolute;
                inset: 18px 20px;
                opacity: 0;
                transform: translateY(12px);
                transition: opacity .35s ease, transform .35s ease;
            }
            .xcart-installer-hint.is-active {
                opacity: 1;
                transform: translateY(0);
            }
            .xcart-installer-hint-kicker {
                display: inline-flex;
                align-items: center;
                min-height: 24px;
                padding: 3px 9px;
                border-radius: 999px;
                background: #e0f2fe;
                color: #075985;
                font-size: 12px;
                font-weight: 700;
            }
            .xcart-installer-hint-title {
                margin: 13px 0 0;
                color: #111827;
                font-size: 17px;
                line-height: 1.8;
                font-weight: 700;
            }
            .xcart-installer-hint-text {
                margin: 6px 0 0;
                color: #64748b;
                font-size: 13px;
                line-height: 1.9;
            }
            .xcart-installer-hint-dots {
                display: flex;
                gap: 6px;
                margin-top: 14px;
            }
            .xcart-installer-hint-dot {
                width: 7px;
                height: 7px;
                border-radius: 999px;
                background: #cbd5e1;
                transition: width .25s ease, background .25s ease;
            }
            .xcart-installer-hint-dot.is-active {
                width: 22px;
                background: #2563eb;
            }
            .xcart-installer-actions {
                display: none;
                gap: 8px;
                margin-top: 18px;
            }
            .xcart-installer-error {
                display: none;
                margin-top: 16px;
                padding: 12px 14px;
                border-right: 4px solid #dc2626;
                background: #fcf0f1;
                color: #1d2327;
                line-height: 1.9;
                text-align: right;
            }
            .xcart-installer-note {
                margin: 18px 0 0;
                color: #6b7280;
                font-size: 12px;
            }
            @keyframes xcart-installer-spin {
                to { transform: rotate(360deg); }
            }
            @media (max-width: 782px) {
                .xcart-installer-wrap {
                    align-items: flex-start;
                    padding-top: 24px;
                }
                .xcart-installer-box {
                    width: calc(100vw - 32px);
                }
                .xcart-installer-hints {
                    min-height: 150px;
                }
            }
        </style>

        <div class="xcart-installer-box">
            <div class="xcart-installer-head">
                <div class="xcart-installer-logo">XC</div>
                <div>
                    <h1 class="xcart-installer-title">نصب ایکس کارت</h1>
                </div>
            </div>
            <div class="xcart-installer-body">
                <p id="xcart-installer-status" class="xcart-installer-status">
                    <span class="xcart-installer-spinner" aria-hidden="true"></span>
                    آماده‌سازی نصب...
                </p>
                <div class="xcart-installer-progress" aria-hidden="true">
                    <div class="xcart-installer-progress-bar" id="xcart-installer-progress-bar"></div>
                </div>
                <div class="xcart-installer-hints" id="xcart-installer-hints" aria-live="polite">
                    <div class="xcart-installer-hint is-active">
                        <span class="xcart-installer-hint-kicker">بازیابی سبد خرید</span>
                        <p class="xcart-installer-hint-title">با کمپین سفارش رها شده، مشتری را در زمان درست به خرید برگردانید.</p>
                    </div>
                    <div class="xcart-installer-hint">
                        <span class="xcart-installer-hint-kicker">ارسال انبوه</span>
                        <p class="xcart-installer-hint-title">برای گروه‌های مختلف مشتریان، پیام‌های جداگانه و دقیق ارسال کنید.</p>
                    </div>
                    <div class="xcart-installer-hint">
                        <span class="xcart-installer-hint-kicker">ورود سریع</span>
                        <p class="xcart-installer-hint-title">ورود با کد تایید، مسیر خرید را کوتاه‌تر و ساده‌تر می‌کند.</p>
                    </div>
                    <div class="xcart-installer-hint">
                        <span class="xcart-installer-hint-kicker">گزارش فروش</span>
                        <p class="xcart-installer-hint-title">اثر هر کمپین را با گزارش‌های دقیق XCart دنبال کنید.</p>
                    </div>
                    <div class="xcart-installer-hint-dots" aria-hidden="true">
                        <span class="xcart-installer-hint-dot is-active"></span>
                        <span class="xcart-installer-hint-dot"></span>
                        <span class="xcart-installer-hint-dot"></span>
                        <span class="xcart-installer-hint-dot"></span>
                    </div>
                </div>
                <div id="xcart-installer-error" class="xcart-installer-error"></div>
                <div id="xcart-installer-actions" class="xcart-installer-actions">
                    <button type="button" class="button button-primary" id="xcart-installer-retry">تلاش دوباره</button>
                    <a class="button" href="<?php echo esc_url(XCART_INSTALLER_PACKAGE_URL); ?>" target="_blank" rel="noopener">دانلود دستی بسته</a>
                    <a class="button" href="<?php echo esc_url($plugins_url); ?>">صفحه افزونه‌ها</a>
                </div>
                <p class="xcart-installer-note">این فرآیند معمولاً چند ثانیه طول می‌کشد. لطفاً این صفحه را نبندید.</p>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var statusEl = document.getElementById('xcart-installer-status');
            var errorEl = document.getElementById('xcart-installer-error');
            var actionsEl = document.getElementById('xcart-installer-actions');
            var retryEl = document.getElementById('xcart-installer-retry');
            var progressEl = document.getElementById('xcart-installer-progress-bar');
            var hintEls = Array.prototype.slice.call(document.querySelectorAll('.xcart-installer-hint'));
            var hintDotEls = Array.prototype.slice.call(document.querySelectorAll('.xcart-installer-hint-dot'));
            var ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
            var nonce = <?php echo wp_json_encode($nonce); ?>;
            var fallbackUrl = <?php echo wp_json_encode($done_url); ?>;
            var progressTimer = null;
            var hintTimer = null;
            var hintIndex = 0;
            var messages = [
                'در حال دانلود بسته کامل ایکس کارت...',
                'در حال بررسی فایل دانلود شده...',
                'در حال نصب افزونه در وردپرس...',
                'در حال فعال‌سازی ایکس کارت...'
            ];

            function showHint(index) {
                hintEls.forEach(function (el, itemIndex) {
                    el.classList.toggle('is-active', itemIndex === index);
                });
                hintDotEls.forEach(function (el, itemIndex) {
                    el.classList.toggle('is-active', itemIndex === index);
                });
            }

            function startHints() {
                if (hintTimer || !hintEls.length) {
                    return;
                }

                hintTimer = window.setInterval(function () {
                    hintIndex = (hintIndex + 1) % hintEls.length;
                    showHint(hintIndex);
                }, 2600);
            }

            function setBusy(message) {
                statusEl.innerHTML = '<span class="xcart-installer-spinner" aria-hidden="true"></span>' + message;
                errorEl.style.display = 'none';
                actionsEl.style.display = 'none';
            }

            function setError(message) {
                if (progressTimer) {
                    window.clearInterval(progressTimer);
                    progressTimer = null;
                }
                progressEl.style.width = '100%';
                progressEl.style.background = '#dc2626';
                statusEl.textContent = 'نصب ایکس کارت کامل نشد.';
                errorEl.textContent = message || 'خطای نامشخص هنگام نصب رخ داد.';
                errorEl.style.display = 'block';
                actionsEl.style.display = 'flex';
            }

            function install() {
                var progress = 18;
                var messageIndex = 0;

                startHints();
                progressEl.style.width = progress + '%';
                progressEl.style.background = 'linear-gradient(90deg, #2563eb, #16a34a)';
                setBusy(messages[0]);

                if (progressTimer) {
                    window.clearInterval(progressTimer);
                }

                progressTimer = window.setInterval(function () {
                    progress = Math.min(progress + 13, 86);
                    messageIndex = Math.min(messageIndex + 1, messages.length - 1);
                    progressEl.style.width = progress + '%';
                    setBusy(messages[messageIndex]);
                }, 1400);

                var body = new window.URLSearchParams();
                body.append('action', 'xcart_installer_run');
                body.append('nonce', nonce);

                window.fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (payload) {
                        if (!payload || !payload.success) {
                            var message = payload && payload.data && payload.data.message ? payload.data.message : '';
                            setError(message);
                            return;
                        }

                        if (progressTimer) {
                            window.clearInterval(progressTimer);
                            progressTimer = null;
                        }
                        progressEl.style.width = '100%';
                        statusEl.textContent = 'ایکس کارت نصب شد. در حال انتقال...';
                        window.location.href = payload.data.redirect_url || fallbackUrl;
                    })
                    .catch(function (error) {
                        setError(error && error.message ? error.message : 'هنگام ارتباط با وردپرس خطا رخ داد.');
                    });
            }

            retryEl.addEventListener('click', install);
            install();
        }());
    </script>
    <?php
}

function xcart_installer_run()
{
    check_ajax_referer('xcart_installer_run', 'nonce');

    if (!current_user_can('install_plugins')) {
        wp_send_json_error(array('message' => 'شما دسترسی نصب افزونه را ندارید.'), 403);
    }

    if (get_transient(XCART_INSTALLER_LOCK_KEY)) {
        wp_send_json_error(array('message' => 'نصب ایکس کارت در حال اجراست. چند لحظه صبر کنید و دوباره تلاش کنید.'), 409);
    }

    set_transient(XCART_INSTALLER_LOCK_KEY, '1', 10 * MINUTE_IN_SECONDS);

    try {
        $result = xcart_installer_install_main_plugin();
    } catch (Throwable $exception) {
        delete_transient(XCART_INSTALLER_LOCK_KEY);
        wp_send_json_error(array('message' => $exception->getMessage()), 500);
    }

    delete_transient(XCART_INSTALLER_LOCK_KEY);

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 500);
    }

    delete_transient('xcartcore_activation_redirect');
    xcart_installer_finalize_self_removal();

    wp_send_json_success(array(
        'message' => 'ایکس کارت با موفقیت نصب شد.',
        'redirect_url' => admin_url('admin.php?page=xcart&action=xcart_starter&xcart_step=0'),
    ));
}
add_action('wp_ajax_xcart_installer_run', 'xcart_installer_run');

function xcart_installer_install_main_plugin()
{
    $package_url_result = xcart_installer_validate_package_url(XCART_INSTALLER_PACKAGE_URL);
    if (is_wp_error($package_url_result)) {
        return $package_url_result;
    }

    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (!function_exists('download_url')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    if (function_exists('wp_raise_memory_limit')) {
        wp_raise_memory_limit('admin');
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    if (xcart_installer_main_plugin_is_active()) {
        return true;
    }

    if (file_exists(WP_PLUGIN_DIR . '/' . XCART_INSTALLER_MAIN_PLUGIN) && xcart_installer_has_valid_main_header()) {
        return xcart_installer_activate_main_plugin();
    }

    $package = download_url(XCART_INSTALLER_PACKAGE_URL, 300);
    if (is_wp_error($package)) {
        return new WP_Error(
            'xcart_installer_download_failed',
            'دانلود بسته ایکس کارت انجام نشد: ' . $package->get_error_message()
        );
    }

    try {
        $checksum_result = xcart_installer_verify_checksum($package);
        if (is_wp_error($checksum_result)) {
            return $checksum_result;
        }

        $package_result = xcart_installer_validate_downloaded_package($package);
        if (is_wp_error($package_result)) {
            return $package_result;
        }

        $skin = new Automatic_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $installed = $upgrader->install($package, array(
            'clear_update_cache' => true,
            'overwrite_package' => true,
        ));

        if (is_wp_error($installed)) {
            return $installed;
        }

        if (!$installed) {
            return xcart_installer_upgrade_error($skin, 'وردپرس نتوانست بسته دانلود شده ایکس کارت را نصب کند.');
        }

        xcart_installer_clean_plugin_cache();
    } finally {
        if (is_string($package) && file_exists($package)) {
            wp_delete_file($package);
        }
    }

    if (!file_exists(WP_PLUGIN_DIR . '/' . XCART_INSTALLER_MAIN_PLUGIN)) {
        return new WP_Error(
            'xcart_installer_missing_main_file',
            'بسته ایکس کارت نصب شد، اما فایل xcart/xcart.php پیدا نشد.'
        );
    }

    if (!xcart_installer_has_valid_main_header()) {
        return new WP_Error(
            'xcart_installer_invalid_main_header',
            'فایل اصلی ایکس کارت نصب شد، اما header افزونه از دید وردپرس معتبر نیست.'
        );
    }

    return xcart_installer_activate_main_plugin();
}

function xcart_installer_validate_package_url($url)
{
    if (function_exists('wp_http_validate_url') && !wp_http_validate_url($url)) {
        return new WP_Error(
            'xcart_installer_invalid_package_url',
            'آدرس دانلود بسته ایکس کارت معتبر نیست.'
        );
    }

    $parts = wp_parse_url($url);
    $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
    $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
    $path = isset($parts['path']) ? (string) $parts['path'] : '';

    if ($scheme !== 'https' || $host !== XCART_INSTALLER_ALLOWED_PACKAGE_HOST || $path !== '/upserver/xcart.zip') {
        return new WP_Error(
            'xcart_installer_untrusted_package_url',
            'آدرس بسته ایکس کارت در لیست مجاز installer نیست.'
        );
    }

    return true;
}

function xcart_installer_validate_downloaded_package($package)
{
    if (!is_string($package) || !file_exists($package)) {
        return new WP_Error(
            'xcart_installer_missing_download',
            'فایل دانلود شده ایکس کارت پیدا نشد.'
        );
    }

    $size = filesize($package);
    if (!$size || $size < 1024) {
        return new WP_Error(
            'xcart_installer_empty_download',
            'فایل دانلود شده ایکس کارت خالی یا ناقص است.'
        );
    }

    if ($size > XCART_INSTALLER_MAX_PACKAGE_BYTES) {
        return new WP_Error(
            'xcart_installer_oversized_download',
            'حجم بسته دانلود شده از سقف مجاز installer بیشتر است.'
        );
    }

    if (!class_exists('ZipArchive')) {
        return true;
    }

    $zip = new ZipArchive();
    if ($zip->open($package) !== true) {
        return new WP_Error(
            'xcart_installer_invalid_zip',
            'فایل دانلود شده یک بسته zip معتبر نیست.'
        );
    }

    $entry_index = $zip->locateName(XCART_INSTALLER_MAIN_PLUGIN, ZipArchive::FL_NODIR);
    if ($entry_index === false) {
        $entry_index = $zip->locateName(XCART_INSTALLER_MAIN_PLUGIN);
    }

    if ($entry_index === false) {
        $zip->close();
        return new WP_Error(
            'xcart_installer_zip_missing_main_plugin',
            'داخل بسته دانلود شده فایل xcart/xcart.php وجود ندارد.'
        );
    }

    $main_contents = $zip->getFromIndex($entry_index, 8192);
    $zip->close();

    if (!is_string($main_contents) || strpos($main_contents, 'Plugin Name: XCart') === false) {
        return new WP_Error(
            'xcart_installer_zip_invalid_header',
            'header فایل اصلی XCart داخل بسته دانلود شده معتبر نیست.'
        );
    }

    return true;
}

function xcart_installer_verify_checksum($package)
{
    $expected = trim((string) XCART_INSTALLER_PACKAGE_SHA256);
    if ($expected === '') {
        return true;
    }

    $actual = hash_file('sha256', $package);
    if (!is_string($actual) || !hash_equals(strtolower($expected), strtolower($actual))) {
        wp_delete_file($package);
        return new WP_Error(
            'xcart_installer_checksum_failed',
            'checksum بسته دانلود شده ایکس کارت معتبر نیست.'
        );
    }

    return true;
}

function xcart_installer_activate_main_plugin()
{
    if (!function_exists('activate_plugin')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    xcart_installer_clean_plugin_cache();

    $network_wide = is_multisite() && is_network_admin();
    $activated = activate_plugin(XCART_INSTALLER_MAIN_PLUGIN, '', $network_wide, false);
    if (is_wp_error($activated)) {
        return new WP_Error(
            'xcart_installer_activation_failed',
            'ایکس کارت نصب شد اما فعال‌سازی انجام نشد: ' . $activated->get_error_message()
        );
    }

    return true;
}

function xcart_installer_has_valid_main_header()
{
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    clearstatcache(true, WP_PLUGIN_DIR . '/' . XCART_INSTALLER_MAIN_PLUGIN);
    xcart_installer_clean_plugin_cache();

    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . XCART_INSTALLER_MAIN_PLUGIN, false, false);

    return !empty($plugin_data['Name']);
}

function xcart_installer_clean_plugin_cache()
{
    if (function_exists('wp_clean_plugins_cache')) {
        wp_clean_plugins_cache(true);
    }

    wp_cache_delete('plugins', 'plugins');
}

function xcart_installer_main_plugin_is_active()
{
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (!file_exists(WP_PLUGIN_DIR . '/' . XCART_INSTALLER_MAIN_PLUGIN) || !xcart_installer_has_valid_main_header()) {
        return false;
    }

    if (is_plugin_active(XCART_INSTALLER_MAIN_PLUGIN)) {
        return true;
    }

    return is_multisite()
        && function_exists('is_plugin_active_for_network')
        && is_plugin_active_for_network(XCART_INSTALLER_MAIN_PLUGIN);
}

function xcart_installer_deactivate_self()
{
    if (!function_exists('deactivate_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    deactivate_plugins(plugin_basename(__FILE__), true, is_multisite() && is_network_admin());
}

function xcart_installer_finalize_self_removal()
{
    xcart_installer_deactivate_self();

    if (!current_user_can('delete_plugins')) {
        return;
    }

    if (!function_exists('delete_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $deleted = delete_plugins(array(plugin_basename(__FILE__)));
    if (is_wp_error($deleted)) {
        update_option('xcart_installer_delete_error', $deleted->get_error_message(), false);
    }
}

function xcart_installer_upgrade_error($skin, $fallback)
{
    if (is_object($skin) && method_exists($skin, 'get_errors')) {
        $errors = $skin->get_errors();
        if (is_wp_error($errors) && $errors->has_errors()) {
            return $errors;
        }
    }

    return new WP_Error('xcart_installer_install_failed', $fallback);
}
