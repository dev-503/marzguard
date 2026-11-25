<?php
/**
 * Elementor integration for MarzGard.
 *
 * @package MarzGard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Elementor widget: Digikala-style login.
 */
class MarzGard_Elementor_Digikala extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'marzgard-digikala-login';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'مرزگارد — ورود دیجی کالا', 'marzgard' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-lock-user';
	}

	/**
	 * Widget category.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'marzgard' );
	}

	/**
	 * Widget keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'marzgard', ' Digikala', 'دیجی کالا', 'login', 'ورود', 'otp' );
	}

	/**
	 * Register controls (modern Elementor 3.x).
	 */
	public function register_controls() {
		$cm = \Elementor\Controls_Manager::class;

		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'محتوا', 'marzgard' ),
				'tab'   => $cm::TAB_CONTENT,
			)
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'عنوان', 'marzgard' ),
				'type'    => $cm::TEXT,
				'default' => __( 'ورود یا ثبتنام در دیجیکالا', 'marzgard' ),
			)
		);

		$this->add_control(
			'subtitle',
			array(
				'label'   => __( 'توضیحات', 'marzgard' ),
				'type'    => $cm::TEXT,
				'default' => __( 'لطفا شماره موبایل یا ایمیل خود را وارد کنید', 'marzgard' ),
			)
		);

		$this->add_control(
			'placeholder',
			array(
				'label'   => __( 'متن راهنمای اینپوت', 'marzgard' ),
				'type'    => $cm::TEXT,
				'default' => '912 345 6789',
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'   => __( 'متن دکمه ورود', 'marzgard' ),
				'type'    => $cm::TEXT,
				'default' => __( 'ورود دیجیکالا', 'marzgard' ),
			)
		);

		$this->add_control(
			'otp_title',
			array(
				'label'   => __( 'عنوان مرحله کد', 'marzgard' ),
				'type'    => $cm::TEXT,
				'default' => __( 'تایید شماره موبایل', 'marzgard' ),
			)
		);

		$this->add_control(
			'otp_button',
			array(
				'label'   => __( 'متن دکمه تأیید کد', 'marzgard' ),
				'type'    => $cm::TEXT,
				'default' => __( 'تایید و ورود', 'marzgard' ),
			)
		);

		$this->add_control(
			'terms',
			array(
				'label'   => __( 'متن قوانین', 'marzgard' ),
				'type'    => $cm::TEXTAREA,
				'default' => __( 'ورود به معنای پذیرش شرایط و قوانین حریم خصوصی است', 'marzgard' ),
			)
		);

		$this->add_control(
			'mode',
			array(
				'label'   => __( 'حالت فرم', 'marzgard' ),
				'type'    => $cm::SELECT,
				'default' => '',
				'options' => array(
					''          => __( 'پیشفرض تنظیمات', 'marzgard' ),
					'login'     => __( 'فقط ورود', 'marzgard' ),
					'register'  => __( 'فقط ثبت نام', 'marzgard' ),
				),
			)
		);

		$this->add_control(
			'redirect',
			array(
				'label'   => __( 'آدرس هدایت پس از ورود', 'marzgard' ),
				'type'    => $cm::URL,
				'default' => '',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'ظاهر', 'marzgard' ),
				'tab'   => $cm::TAB_STYLE,
			)
		);

		$this->add_control(
			'color',
			array(
				'label'   => __( 'رنگ برند', 'marzgard' ),
				'type'    => $cm::COLOR,
				'default' => '#e60023',
			)
		);

		$this->add_control(
			'show_logo',
			array(
				'label'        => __( 'نمایش لوگو', 'marzgard' ),
				'type'         => $cm::SWITCHER,
				'label_on'     => __( 'بله', 'marzgard' ),
				'label_off'    => __( 'خیر', 'marzgard' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'class',
			array(
				'label'   => __( 'کلاس CSS اضافی', 'marzgard' ),
				'type'    => $cm::TEXT,
				'default' => '',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Backwards compatibility with Elementor 2.x.
	 */
	protected function _register_controls() {
		$this->register_controls();
	}

	/**
	 * Render the widget.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! marzgard() || ! marzgard()->shortcodes() ) {
			return;
		}

		echo marzgard()->shortcodes()->render_digikala( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/**
	 * Render in Elementor editor preview.
	 */
	protected function _content_template() {
		?>
		<div class="marzgard-digikala" style="--dk-red:{{ settings.color || '#e60023' }}">
			<div class="dk-card" style="max-width:100%;box-shadow:none">
				<div class="dk-card__head">
					<div class="dk-logo"><span class="dk-logo__text">دیجی کالا</span></div>
				</div>
				<div class="dk-body">
					<h1 class="dk-title">{{ settings.title }}</h1>
					<p class="dk-subtitle">{{ settings.subtitle }}</p>
					<div class="dk-input" style="display:flex;align-items:center;color:#a5a5a5">{{ settings.placeholder }}</div>
					<div class="dk-btn" style="margin-top:16px">{{ settings.button_text }}</div>
					<p class="dk-terms">{{ settings.terms }}</p>
				</div>
			</div>
		</div>
		<?php
	}
}

/**
 * Elementor bootstrap.
 */
class MarzGard_Elementor {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_category' ) );

		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.0.0', '>=' ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		} else {
			add_action( 'elementor/widgets/widgets_registered', array( $this, 'register_widget_legacy' ) );
		}
	}

	/**
	 * Add the MarzGard category.
	 *
	 * @param object $elements_manager Elements manager.
	 */
	public function add_category( $elements_manager ) {
		$elements_manager->add_category(
			'marzgard',
			array(
				'title' => __( 'مرزگارد', 'marzgard' ),
				'icon'  => 'fa fa-shield',
			)
		);
	}

	/**
	 * Register widget (Elementor 3.x).
	 *
	 * @param object $widgets_manager Widgets manager.
	 */
	public function register_widget( $widgets_manager ) {
		$widgets_manager->register( new MarzGard_Elementor_Digikala() );
	}

	/**
	 * Register widget (Elementor 2.x).
	 *
	 * @param object $widgets_manager Widgets manager.
	 */
	public function register_widget_legacy( $widgets_manager ) {
		if ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( new MarzGard_Elementor_Digikala() );
		}
	}
}
