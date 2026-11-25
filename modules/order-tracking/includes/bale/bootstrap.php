<?php
/**
 * ماژول اعلان بله (ادغام‌شده از افزونه‌ی Hamidreza Order Notifier — فقط بله، بدون تلگرام و پروکسی)
 *
 * @package DeiaraOrderTracking
 */

defined( 'ABSPATH' ) || exit;

// اگر افزونه‌ی قدیمی «Hamidreza Order Notifier» هنوز فعال است، این ماژول را بارگذاری نکن
// تا اعلان‌ها دوبار ارسال نشوند.
if ( defined( 'HRON_VERSION' ) || class_exists( 'Hron_Notifier', false ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-warning"><p><strong>پیگیری سفارشات دیارا:</strong> افزونه‌ی «Hamidreza Order Notifier» هنوز فعال است. چون اعلان بله اکنون داخل همین افزونه است، آن را غیرفعال کنید (تنظیمات شما حفظ می‌شود و نیازی به وارد کردن دوباره نیست).</p></div>';
	} );
	return;
}

require_once DOT_PATH . 'includes/bale/class-bale-settings.php';
require_once DOT_PATH . 'includes/bale/class-bale-log.php';
require_once DOT_PATH . 'includes/bale/class-bale-jalali.php';
require_once DOT_PATH . 'includes/bale/class-bale-notifier.php';
require_once DOT_PATH . 'includes/bale/class-bale-actions.php';
require_once DOT_PATH . 'includes/bale/class-bale-admin.php';

/** متن‌های پیام به فارسی (بدون نیاز به فایل ترجمه) */
add_filter( 'gettext', function ( $translated, $text, $domain ) {
	if ( 'hamidreza' !== $domain ) {
		return $translated;
	}
	static $map = null;
	if ( null === $map ) {
		$map = array(
			'WooCommerce Order Update'      => 'بروزرسانی سفارش',
			'Order: #%s'                    => 'سفارش: #%s',
			'Status: %s'                    => 'وضعیت: %s',
			'Customer: %s'                  => 'مشتری: %s',
			'Phone: %s'                     => 'تلفن: %s',
			'Payment: %s'                   => 'پرداخت: %s',
			'Total: %s'                     => 'مبلغ کل: %s',
			'Shipping: %s'                  => 'هزینه ارسال: %s',
			'Coupon: %s'                    => 'کد تخفیف: %s',
			'Date: %s'                      => 'تاریخ: %s',
			'Customer note: %s'             => 'یادداشت مشتری: %s',
			'Order:'                        => 'سفارش:',
			'Status:'                       => 'وضعیت:',
			'Customer:'                     => 'مشتری:',
			'Phone:'                        => 'تلفن:',
			'Payment:'                      => 'پرداخت:',
			'Total:'                        => 'مبلغ کل:',
			'Shipping:'                     => 'هزینه ارسال:',
			'Coupon:'                       => 'کد تخفیف:',
			'Date:'                         => 'تاریخ:',
			'Customer note:'                => 'یادداشت مشتری:',
			'Items:'                        => 'اقلام:',
			'Shipping address:'             => 'آدرس ارسال:',
			'SKU: %s'                       => 'کد محصول: %s',
			'Guest'                         => 'مهمان',
			'Open order'                    => 'مشاهده سفارش',
			'Test'                          => 'آزمایشی',
			'Test message delivered successfully.' => 'پیام آزمایشی با موفقیت ارسال شد.',
			'Test failed.'                  => 'ارسال آزمایشی ناموفق بود.',
			'Delivered to chat %s'          => 'ارسال شد به چت %s',
			'Delivery failed'               => 'ارسال ناموفق بود',
			'Bot token and chat ID are required.' => 'توکن ربات و شناسه چت لازم است.',
			'Chat ID is required for this platform.' => 'شناسه چت لازم است.',
			'Unexpected API response (%d).' => 'پاسخ نامعتبر از API (%d).',
			'Order notification could not be delivered to Bale.' => 'ارسال اعلان این سفارش به بله ناموفق بود.',
			'Sample Customer'               => 'مشتری نمونه',
			'Cash on delivery'              => 'پرداخت در محل',
			'Express Courier'               => 'پیک',
			'Tehran'                        => 'تهران',
			'Sample product'                => 'محصول نمونه',
			'Sample Street 1'               => 'خیابان نمونه ۱',
			'Sample City'                   => 'شهر نمونه',
			'Please call before delivery.'  => 'لطفاً قبل از تحویل تماس بگیرید.',
		);
	}
	return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
}, 10, 3 );

new DOT_Bale_Notifier();
DOT_Bale_Actions::init();
DOT_Bale_Admin::init();
