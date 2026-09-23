# JCORE Oikeus Module

Adds a `site_admin` role for WordPress: an Editor who can also manage users and
navigation menus, without the rest of what an Administrator can do.

## The role

`site_admin` starts from the Editor's capabilities and adds:

- `create_users`, `promote_users`, `list_users` — add users and change their roles.
- `edit_nav_menus` — access to the Menus screen, shown as a top level **Menus** item.

It deliberately does not get `edit_theme_options`. That capability also gates the
Site Editor, Themes, Widgets, the Customizer, the Font Library and any plugin
screen registered against it. Instead it is lent to menu editors on the Menus
screen and the four AJAX actions that screen uses, and nowhere else.

## Rules for users below administrator

These apply to anyone who cannot `manage_options`, not only site admins:

- **Roles they can hand out** are limited to roles whose capabilities they hold
  themselves, so nobody can grant more than they have.
- **Users they can change** are limited to users whose capabilities they hold
  themselves. Core maps `promote_user` straight to `promote_users` whatever the
  target, so without this a site admin could demote an administrator through the
  bulk "Change role to" action or the REST users endpoint.

Administrators, and super admins on multisite, keep core's behaviour.

## Usage

Register the bootstrap as a module, and Ydin initializes it on `init`:

```php
add_filter(
    'jcore_theme_load_modules',
    function ( $modules ) {
        $modules[] = \Jcore\Oikeus\Bootstrap::class;
        return $modules;
    }
);
```

Or call `\Jcore\Oikeus\Bootstrap::init()` yourself before `init`.

## Upgrading

The role is stored in the database, so its capabilities are applied once per
version of the role, tracked in the `jcore_oikeus_role_version` option. Existing
sites pick up `edit_nav_menus` on the first request after updating.

A site admin who opens a screen outside the Menus allowlist gets WordPress's
standard "not allowed" page. Earlier versions redirected to the dashboard, and
only for the Themes and Site Editor screens.

## Requirements

- `jcore/ydin` 3.5 or newer, for `BootstrapInterface`. Works with 4.x and 5.x.
