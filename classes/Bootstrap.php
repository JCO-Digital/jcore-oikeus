<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Jcore\Oikeus;

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
		add_action( 'init', [ self::class, 'add_site_admin_role' ], 11 );
		add_action( 'current_screen', [ self::class, 'restrict_screen_access' ] );
		add_action( 'admin_menu', [ self::class, 'hide_items_from_menu' ], 999 );

		add_filter( 'editable_roles', [ self::class, 'filter_editable_roles' ] );
		add_filter( 'user_has_cap', [ self::class, 'allow_access_to_nav_menus' ], 10, 4 );
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

	/**
	 * Adds the 'site_admin' role to WordPress.
	 * This role is based on the Editor role and has additional capabilities.
	 *
	 * @return void
	 */
	public static function add_site_admin_role(): void {
		// Get Editor capabilities
		$editor = get_role( 'editor' );
		if ( ! $editor ) {
			return;
		}
		add_role(
			'site_admin',
			__( 'Site Admin', 'jcore' ),
			$editor->capabilities
		);

		// Add additional capabilities
		$site_admin = get_role( 'site_admin' );

		// Remove all capabilities
		if ( ! $site_admin ) {
			return;
		}

		$site_admin->add_cap( 'create_users' );
		$site_admin->add_cap( 'promote_users' );
		$site_admin->add_cap( 'list_users' );
	}

	/**
	 *  Filter editable roles to remove the 'administrator' role for non-admin users.
	 *
	 * @param array $roles Array of roles.
	 *
	 * @return array
	 */
	public static function filter_editable_roles( $roles ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			unset( $roles['administrator'] );
		}
		return $roles;
	}

	/**
	 * Restrict access to the site editor and themes page for site admins.
	 * If a site admin tries to access the site editor or themes page, they will be redirected to the admin dashboard.
	 *
	 * @return void
	 */
	public static function restrict_screen_access(): void {
		if ( self::current_user_is_site_admin() && is_admin() ) {
			// Check if the user is trying to access site-editor.php
			if ( function_exists( 'get_current_screen' ) ) {
				$current_screen = get_current_screen();
				if ( $current_screen && $current_screen->id === 'site-editor' || $current_screen->id === 'themes' ) {
					wp_redirect( admin_url() );
					exit;
				}
			}
		}
	}

	/**
	 * Hide the themes and site editor submenu items from the admin menu for site admins.
	 * This function removes the themes and site editor submenu items from the 'Appearance'
	 * menu for users with the 'site_admin' role.
	 *
	 * @return void
	 */
	public static function hide_items_from_menu(): void {
		if ( self::current_user_is_site_admin() ) {
			remove_submenu_page( 'themes.php', 'themes.php' );
			remove_submenu_page( 'themes.php', 'site-editor.php' );
		}
	}


	/**
	 * Allow access to nav menus screen for site admins.
	 *
	 * @param array $all_caps Array of all capabilities for the user.
	 * @param array $caps Array of capabilities being checked.
	 * @param array $args Array of arguments passed to the capability check.
	 * @param \WP_User $user The user object for the current user.
	 *
	 * @return array
	 */
	public static function allow_access_to_nav_menus( $all_caps, $caps, $args, $user ) : array {
		if ( !is_admin()) {
			return $all_caps;
		}

		if ( 'edit_theme_options' !== $args[0] ) {
			return $all_caps;
		}

		if ( ! $user || ! in_array( 'site_admin', (array) $user->roles, true ) ) {
			return $all_caps;
		}

		// If the user is a site admin, add the 'edit_theme_options' capability.
		if ( ! isset( $all_caps['edit_theme_options'] ) || ! $all_caps['edit_theme_options'] ) {
			$all_caps['edit_theme_options'] = true;
		}

		return $all_caps;
	}

	/*
	 * Helper function to check if the current user is a site admin.
	 */
	public static function current_user_is_site_admin(): bool {
		$current_user = wp_get_current_user();

		if ($current_user->exists() && in_array('site_admin', (array) $current_user->roles)) {
			return true;
		}

		return false;
	}
}
