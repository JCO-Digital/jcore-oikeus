<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Jcore\Template;

use Jcore\Ydin\BootstrapInterface;

if ( is_file( __DIR__ . '/../vendor/autoload.php' ) ) {
	require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * The bootstrap class, should be used by all dependencies.
 */
class Bootstrap implements BootstrapInterface {
	/**
	 * The singleton instance.
	 *
	 * @var Bootstrap|null
	 */
	private static ?Bootstrap $instance = null;

	/**
	 * Bootstrap constructor.
	 */
	private function __construct() {
		// TODO: Add stuff here to use
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Bootstrap
	 */
	public static function init(): Bootstrap {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
