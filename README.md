# JCORE Oikeus Module

This module provides a custom `site_admin` role for WordPress, based on the Editor role, with additional capabilities and restrictions. It is designed to allow advanced site management without full administrator privileges.

## Features

- **Custom Role:** Adds a `site_admin` role based on Editor, with extra capabilities:
  - Can create, promote, and list users.
  - Can manage navigation menus.
- **Restricted Access:**
  - `site_admin` users cannot access the Site Editor or Themes screens.
  - The Themes and Site Editor menu items are hidden for `site_admin` users.
  - `site_admin` users cannot assign the Administrator role to others.
- **Menu and Capability Management:**
  - Ensures `site_admin` can access Appearance > Menus.
  - Prevents access to theme and plugin editors.

## Usage

1. **Installation:**  
   Require this module in your project and ensure it is loaded before the `init` hook.

2. **Initialization:**  
   Call the bootstrapper early in your plugin or theme:
   ```php
   Jcore\Oikeus\Bootstrap::init();st.org that tracks this repository