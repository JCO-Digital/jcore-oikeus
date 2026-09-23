<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * JCORE Oikeus: the restricted site_admin role.
 *
 * @package Jcore\Oikeus
 */

namespace Jcore\Oikeus;

use Jcore\Ydin\BootstrapInterface;
use WP_User;

/**
 * Adds a `site_admin` role: an Editor who can also manage users and navigation
 * menus, without being able to touch administrators, themes or anything else
 * that sits behind `edit_theme_options`.
 *
 * Two rules hold for every user who cannot `manage_options`, not only site admins:
 *
 * - They can only hand out roles whose capabilities they have themselves.
 * - They can only change users whose capabilities they have themselves, so an
 *   administrator can never be demoted or locked out by someone below them.
 */
class Bootstrap implements BootstrapInterface {

	/**
	 * The role slug.
	 */
	public const ROLE = 'site_admin';

	/**
	 * Custom capability that grants Appearance > Menus, and nothing else.
	 */
	public const MENU_CAP = 'edit_nav_menus';

	/**
	 * Bump when the role's capabilities change, so existing sites pick them up.
	 */
	private const ROLE_VERSION = 2;

	/**
	 * Option that records which ROLE_VERSION the site's role is at.
	 */
	private const ROLE_VERSION_OPTION = 'jcore_oikeus_role_version';

	/**
	 * Capabilities the role gets on top of the Editor's.
	 */
	private const EXTRA_CAPS = array( 'create_users', 'promote_users', 'list_users', self::MENU_CAP );

	/**
	 * The admin-ajax actions the Menus screen calls. Each one still checks its own
	 * nonce and `edit_theme_options`; this list only decides where that capability
	 * is lent to a menu editor.
	 */
	private const MENU_AJAX_ACTIONS = array( 'add-menu-item', 'menu-get-metabox', 'menu-locations-save', 'menu-quick-search' );

	/**
	 * Meta capabilities that act on another user.
	 */
	private const USER_META_CAPS = array( 'promote_user', 'edit_user', 'delete_user', 'remove_user' );

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
		add_action( 'init', array( self::class, 'maybe_update_role' ), 11 );
		add_action( 'admin_menu', array( self::class, 'adjust_admin_menu' ), 999 );

		add_filter( 'parent_file', array( self::class, 'menus_parent_file' ) );
		add_filter( 'editable_roles', array( self::class, 'filter_editable_roles' ) );
		add_filter( 'map_meta_cap', array( self::class, 'protect_privileged_users' ), 10, 4 );
		add_filter( 'user_has_cap', array( self::class, 'allow_access_to_nav_menus' ), 10, 4 );
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
	 * Create the role, or bring an existing one up to date.
	 *
	 * Roles live in the database, so this only writes when ROLE_VERSION moves.
	 *
	 * @return void
	 */
	public static function maybe_update_role(): void {
		if ( (int) get_option( self::ROLE_VERSION_OPTION, 0 ) >= self::ROLE_VERSION ) {
			return;
		}

		$editor = get_role( 'editor' );
		if ( ! $editor ) {
			return;
		}

		$role = get_role( self::ROLE ) ?? add_role( self::ROLE, __( 'Site Admin', 'jcore' ), $editor->capabilities );
		if ( ! $role ) {
			return;
		}

		foreach ( self::EXTRA_CAPS as $cap ) {
			$role->add_cap( $cap );
		}

		update_option( self::ROLE_VERSION_OPTION, self::ROLE_VERSION );
	}

	/**
	 * Only offer roles the current user could not use to gain capabilities.
	 *
	 * @param array $roles Roles keyed by slug, each with a `capabilities` array.
	 *
	 * @return array
	 */
	public static function filter_editable_roles( $roles ): array {
		if ( current_user_can( 'manage_options' ) ) {
			return $roles;
		}

		$own = wp_get_current_user()->allcaps;

		return array_filter(
			(array) $roles,
			static fn( $role ) => self::caps_within( (array) ( $role['capabilities'] ?? array() ), $own )
		);
	}

	/**
	 * Stop users from changing anyone who holds capabilities they do not.
	 *
	 * `promote_user` maps straight to `promote_users`, with no regard for who the
	 * target is, so without this a site admin could demote an administrator from
	 * the bulk "Change role to" action or the REST users endpoint.
	 *
	 * @param string[] $caps    Primitive capabilities required.
	 * @param string   $cap     The meta capability being checked.
	 * @param int      $user_id The acting user.
	 * @param array    $args    Extra arguments; the target user ID comes first.
	 *
	 * @return string[]
	 */
	public static function protect_privileged_users( $caps, $cap, $user_id, $args ): array {
		if ( ! in_array( $cap, self::USER_META_CAPS, true ) ) {
			return $caps;
		}

		$target_id = (int) ( $args[0] ?? 0 );
		if ( $target_id < 1 || $target_id === (int) $user_id ) {
			return $caps;
		}

		// Administrators, and super admins on multisite, keep core's behaviour.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return $caps;
		}

		$actor  = get_userdata( $user_id );
		$target = get_userdata( $target_id );
		if ( ! $actor || ! $target ) {
			return $caps;
		}

		if ( ! self::caps_within( self::own_caps( $target ), $actor->allcaps ) ) {
			$caps[] = 'do_not_allow';
		}

		return $caps;
	}

	/**
	 * Lend `edit_theme_options` to menu editors, but only on the Menus screen.
	 *
	 * The capability also gates the Site Editor, Themes, Widgets, the Font Library
	 * and any plugin screen registered against it, so it is never granted outright.
	 *
	 * @param array   $allcaps The user's capabilities, before this filter.
	 * @param array   $caps    Primitive capabilities being checked.
	 * @param array   $args    The requested capability, then the user ID.
	 * @param WP_User $user    The user being checked.
	 *
	 * @return array
	 */
	public static function allow_access_to_nav_menus( $allcaps, $caps, $args, $user ): array {
		if ( 'edit_theme_options' !== ( $args[0] ?? '' ) ) {
			return $allcaps;
		}

		if ( ! self::is_menu_editor( (array) $allcaps ) || ! self::is_nav_menu_request() ) {
			return $allcaps;
		}

		$allcaps['edit_theme_options'] = true;

		return $allcaps;
	}

	/**
	 * Give menu editors a top level "Menus" item in place of Appearance.
	 *
	 * Menus would be the only item under Appearance a menu editor can use, and
	 * core drops a parent whose only accessible child points at the same screen.
	 * On the Menus screen itself the lent capability would also expose Themes
	 * under Appearance, so Appearance goes either way.
	 *
	 * @return void
	 */
	public static function adjust_admin_menu(): void {
		if ( ! self::is_menu_editor( wp_get_current_user()->allcaps ) ) {
			return;
		}

		remove_menu_page( 'themes.php' );

		if ( ! current_theme_supports( 'menus' ) && ! current_theme_supports( 'widgets' ) ) {
			return;
		}

		add_menu_page(
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Reuses core's own "Menus" string and translation.
			__( 'Menus' ),
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Reuses core's own "Menus" string and translation.
			__( 'Menus' ),
			self::MENU_CAP,
			'nav-menus.php',
			'',
			'dashicons-menu',
			60
		);
	}

	/**
	 * Highlight the top level Menus item while on the Menus screen.
	 *
	 * @param string $parent_file The parent file for the current screen.
	 *
	 * @return string
	 */
	public static function menus_parent_file( $parent_file ) {
		global $pagenow;

		if ( 'nav-menus.php' === $pagenow && self::is_menu_editor( wp_get_current_user()->allcaps ) ) {
			return 'nav-menus.php';
		}

		return $parent_file;
	}

	/**
	 * Whether a user edits menus through MENU_CAP rather than natively.
	 *
	 * @param array $allcaps The user's own capabilities, unfiltered.
	 *
	 * @return bool
	 */
	private static function is_menu_editor( array $allcaps ): bool {
		return ! empty( $allcaps[ self::MENU_CAP ] ) && empty( $allcaps['edit_theme_options'] );
	}

	/**
	 * Whether the current request is the Menus screen or one of its AJAX calls.
	 *
	 * @return bool
	 */
	private static function is_nav_menu_request(): bool {
		global $pagenow;

		if ( ! is_admin() ) {
			return false;
		}

		if ( 'nav-menus.php' === $pagenow ) {
			return true;
		}

		if ( ! wp_doing_ajax() || ! isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only routes a capability; the handler verifies its own nonce.
			return false;
		}

		$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only routes a capability; the handler verifies its own nonce.

		return in_array( $action, self::MENU_AJAX_ACTIONS, true );
	}

	/**
	 * A user's granted capabilities, without the role names WordPress mixes in.
	 *
	 * @param WP_User $user The user.
	 *
	 * @return array
	 */
	private static function own_caps( WP_User $user ): array {
		$roles = wp_roles();

		return array_filter(
			$user->allcaps,
			static fn( $grant, $cap ) => $grant && ! $roles->is_role( $cap ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Whether every granted capability in `$caps` is also granted in `$held`.
	 *
	 * @param array $caps Capabilities to check, as cap => grant.
	 * @param array $held Capabilities held, as cap => grant.
	 *
	 * @return bool
	 */
	private static function caps_within( array $caps, array $held ): bool {
		foreach ( $caps as $cap => $grant ) {
			if ( $grant && empty( $held[ $cap ] ) ) {
				return false;
			}
		}

		return true;
	}
}
