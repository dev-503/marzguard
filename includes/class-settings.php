<?php
/**
 * Admin settings screen.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page.
 */
class MarzGard_Settings {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MARZGARD_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add the settings page.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'مرزگارد', 'marzgard' ),
			__( 'مرزگارد', 'marzgard' ),
			'manage_options',
			'marzgard',
			array( $this, 'render_page' ),
			'dashicons-shield',
			81
		);

		add_submenu_page(
			'marzgard',
			__( 'دمو لاگین ها', 'marzgard' ),
			__( 'دمو لاگین ها', 'marzgard' ),
			'manage_options',
			'marzgard-demos',
			array( $this, 'render_demos_page' )
		);
	}

	/**
	 * Register the option with WordPress.
	 */
	public function register_settings() {
		register_setting(
			'marzgard_settings_group',
			'marzgard_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'marzgard_sanitize_options',
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Add a settings link to the plugin row.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=marzgard' ) ),
			esc_html__( 'تنظیمات', 'marzgard' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Demo logins gallery (served from the plugin's dev folder).
	 */
	public function render_demos_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$demos = array(
			array(
				'key'   => 'mihan',
				'title' => __( 'ورود میهن (ایکس کارت)', 'marzgard' ),
				'desc'  => __( 'پوسته تیره ایکس کارت با دکمه سبز ورود', 'marzgard' ),
				'icon'  => '⬛',
				'file'  => 'dev/mihan/index.html',
			),
			array(
				'key'   => 'snappfood',
				'title' => __( 'ورود اسنپ فود', 'marzgard' ),
				'desc'  => __( 'پوسته سبز اسنپ فود با دکمه ثابت انتهای صفحه', 'marzgard' ),
				'icon'  => '🟩',
				'file'  => 'dev/snappfood/index.html',
			),
			array(
				'key'   => 'technolife',
				'title' => __( 'ورود تکنولایف', 'marzgard' ),
				'desc'  => __( 'پوسته آبی تکنولایف با ورود و ثبت نام', 'marzgard' ),
				'icon'  => '🟦',
				'file'  => 'dev/technolife/index.html',
			),
			array(
				'key'   => 'digikala',
				'title' => __( 'ورود دیجی کالا', 'marzgard' ),
				'desc'  => __( 'پوسته قرمز دیجی کالا (شورت کد و المنتور)', 'marzgard' ),
				'icon'  => '🟥',
				'file'  => 'dev/preview-digikala.html',
			),
			array(
				'key'   => 'sorkhab',
				'title' => __( 'ورود سرخآب', 'marzgard' ),
				'desc'  => __( 'پوسته پیشفرض مرزگارد براساس دیزاین سیستم سرخآب', 'marzgard' ),
				'icon'  => '🟩',
				'file'  => 'dev/preview.html',
			),
			array(
				'key'   => 'admin',
				'title' => __( 'پیش نمایش تنظیمات', 'marzgard' ),
				'desc'  => __( 'رابط Vue صفحه تنظیمات مرزگارد', 'marzgard' ),
				'icon'  => '⚙️',
				'file'  => 'dev/preview-admin.html',
			),
		);
		?>
		<div class="wrap marzgard-admin-wrap">
			<div class="mgd-head">
				<h1><?php esc_html_e( 'دمو لاگین ها', 'marzgard' ); ?></h1>
				<p><?php esc_html_e( 'پیش نمایش زنده پوسته های مختلف ورود مرزگارد. این دموها فقط برای نمایش رابط هستند و پیامک واقعی ارسال نمیکنند.', 'marzgard' ); ?></p>
			</div>

			<div class="mgd-grid">
				<?php foreach ( $demos as $demo ) : ?>
					<button
						type="button"
						class="mgd-card"
						data-src="<?php echo esc_url( MARZGARD_URL . $demo['file'] ); ?>"
						data-title="<?php echo esc_attr( $demo['title'] ); ?>"
					>
						<span class="mgd-card__icon" aria-hidden="true"><?php echo esc_html( $demo['icon'] ); ?></span>
						<strong><?php echo esc_html( $demo['title'] ); ?></strong>
						<small><?php echo esc_html( $demo['desc'] ); ?></small>
						<span class="mgd-card__cta"><?php esc_html_e( 'نمایش دمو ↑', 'marzgard' ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="mgd-modal" id="mgd-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?php esc_attr_e( 'پیش نمایش دمو', 'marzgard' ); ?>">
			<div class="mgd-modal__backdrop" data-mgd-close></div>
			<div class="mgd-modal__panel">
				<div class="mgd-bar">
					<strong id="mgd-title"></strong>
					<div class="mgd-bar__actions">
						<a id="mgd-open" href="#" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'تب جدید', 'marzgard' ); ?> ↗</a>
						<button type="button" class="mgd-close" data-mgd-close aria-label="<?php esc_attr_e( 'بستن', 'marzgard' ); ?>">×</button>
					</div>
				</div>
				<iframe id="mgd-frame" title="<?php esc_attr_e( 'پیش نمایش دمو', 'marzgard' ); ?>"></iframe>
			</div>
		</div>
		<script>
		(function () {
			var cards = document.querySelectorAll('.mgd-card');
			var modal = document.getElementById('mgd-modal');
			var frame = document.getElementById('mgd-frame');
			var title = document.getElementById('mgd-title');
			var open = document.getElementById('mgd-open');

			function close() {
				modal.classList.remove('is-open');
				modal.setAttribute('aria-hidden', 'true');
				frame.src = 'about:blank';
				document.body.style.overflow = '';
			}

			Array.prototype.forEach.call(cards, function (card) {
				card.addEventListener('click', function () {
					title.textContent = card.dataset.title;
					frame.src = card.dataset.src;
					open.href = card.dataset.src;
					modal.classList.add('is-open');
					modal.setAttribute('aria-hidden', 'false');
					document.body.style.overflow = 'hidden';
					window.scrollTo({ top: 0, behavior: 'smooth' });
				});
			});

			Array.prototype.forEach.call(document.querySelectorAll('[data-mgd-close]'), function (el) {
				el.addEventListener('click', close);
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && modal.classList.contains('is-open')) { close(); }
			});
		})();
		</script>
		<?php
	}

	/**
	 * Render the Vue-powered settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap marzgard-admin-wrap">
			<div id="marzgard-admin-app" class="marzgard-admin-mount">
				<div class="marzgard-admin-fallback">
					<?php esc_html_e( 'در حال بارگذاری تنظیمات مرزگارد…', 'marzgard' ); ?>
				</div>
			</div>
		</div>
		<?php
	}
}
