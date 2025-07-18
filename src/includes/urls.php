<?php

if ( !function_exists( 'add_menu_link' ) ) {
	/**
	 * Adds a new top-level menu item to the WordPress admin dashboard.
	 *
	 * Unlike WordPress core's add_menu_page(), this function accepts a direct URL
	 * instead of a callback function, making it ideal for external links or
	 * modern JavaScript framework integration.
	 *
	 * @param string               $menu_title The text to be displayed in the menu.
	 * @param string               $url        The URL that the menu item should link to.
	 * @param string               $capability The capability required for this menu to be displayed to the user.
	 * @param string|array<string> $icon_url   The URL to the icon to be used for this menu.
	 *                                         Pass an array to include a class [url, class].
	 * @param int|float|null       $position   The position in the menu order this item should appear.
	 * @return bool Always returns TRUE.
	 */
	function add_menu_link (
		string         $menu_title,
		string         $url,
		string         $capability = 'manage_options',
		string|array   $icon_url = '',
		int|float|null $position = NULL
	): bool {
		global $menu;

		if ( empty( $icon_url ) ) {
			$icon_url   = 'dashicons-admin-generic';
			$icon_class = 'menu-icon-generic ';
		}
		else {
			$icon_url   = (array) $icon_url;
			$icon_class = $icon_url[1] ?? '';
			$icon_url   = set_url_scheme( $icon_url[0] );
		}

		$new_menu = [ $menu_title, $capability, $url, $menu_title, 'menu-top ' . $icon_class, NULL, $icon_url ];

		if ( NULL !== $position && !is_numeric( $position ) ) {
			_doing_it_wrong( __FUNCTION__, sprintf( __( 'The fifth parameter passed to %s should be numeric representing menu position.' ), '<code>add_menu_link()</code>' ), '6.0.0' );
			$position = NULL;
		}

		if ( NULL === $position || !is_numeric( $position ) ) {
			$menu[] = $new_menu;
		}
		elseif ( isset( $menu[(string) $position] ) ) {
			$collision_avoider = base_convert( substr( md5( $url . $menu_title ), -4 ), 16, 10 ) * 0.00001;
			$position          = (string) ( $position + $collision_avoider );
			$menu[$position]   = $new_menu;
		}
		else {
			/*
			 * Cast menu position to a string.
			 *
			 * This allows for floats to be passed as the position. PHP will normally cast a float to an
			 * integer value, this ensures the float retains its mantissa (positive fractional part).
			 *
			 * A string containing an integer value, eg "10", is treated as a numeric index.
			 */
			$position        = (string) $position;
			$menu[$position] = $new_menu;
		}

		return TRUE;
	}
}

if ( !function_exists( 'add_submenu_link' ) ) {
	/**
	 * Adds a submenu item under an existing top-level menu.
	 *
	 * Unlike WordPress core's add_submenu_page(), this function accepts a direct URL
	 * instead of a callback function. It automatically handles parent menu creation
	 * and maintains proper menu hierarchy.
	 *
	 * @param string    $parent_slug The slug name for the parent menu (or the file name of a standard WordPress admin page).
	 * @param string    $menu_title  The text to be displayed in the menu.
	 * @param string    $url         The URL that the submenu item should link to.
	 * @param string    $capability  The capability required for this menu to be displayed to the user.
	 * @param int|null  $position    The position in the menu order this item should appear.
	 * @param bool|null $raw_slug    Whether to skip plugin_basename() processing on the parent_slug.
	 * @return bool True on success, false if the current user lacks the required capability.
	 *
	 */
	function add_submenu_link (
		string $parent_slug,
		string $menu_title,
		string $url,
		string $capability = 'manage_options',
		?int   $position = NULL,
		?bool  $raw_slug = NULL
	): bool {
		global $submenu, $menu, $_wp_real_parent_file, $_wp_submenu_nopriv, $_parent_pages;

		// If you want to reference an `add_menu_link()` created menu item, you can't use `plugin_basename()` because it changes the url
		if ( $raw_slug === FALSE || ( !$raw_slug && !in_array( $parent_slug, array_column( $menu, 2 ) ) ) ) {
			$parent_slug = plugin_basename( $parent_slug );
		}

		if ( isset( $_wp_real_parent_file[$parent_slug] ) ) {
			$parent_slug = $_wp_real_parent_file[$parent_slug];
		}

		if ( !current_user_can( $capability ) ) {
			$_wp_submenu_nopriv[$parent_slug][$url] = TRUE;

			return FALSE;
		}

		/*
		 * If the parent doesn't already have a submenu, add a link to the parent
		 * as the first item in the submenu. If the submenu file is the same as the
		 * parent file someone is trying to link back to the parent manually. In
		 * this case, don't automatically add a link back to avoid duplication.
		 */
		if ( !isset( $submenu[$parent_slug] ) ) {
			foreach ( (array) $menu as $parent_menu ) {
				if ( $parent_menu[2] === $parent_slug && current_user_can( $parent_menu[1] ) ) {
					$submenu[$parent_slug][] = array_slice( $parent_menu, 0, 4 );
				}
			}
		}

		$new_sub_menu = [ $menu_title, $capability, $url, $menu_title ];

		if ( NULL !== $position && !is_numeric( $position ) ) {
			_doing_it_wrong( __FUNCTION__, sprintf( __( 'The fifth parameter passed to %s should be numeric representing menu position.' ), '<code>add_submenu_link()</code>' ), '5.3.0' );
			$position = NULL;
		}

		if ( NULL === $position || ( !isset( $submenu[$parent_slug] ) || $position >= count( $submenu[$parent_slug] ) ) ) {
			$submenu[$parent_slug][] = $new_sub_menu;
		}
		else {
			// Test for a negative position.
			$position = max( $position, 0 );
			if ( 0 === $position ) {
				// For negative or `0` positions, prepend the submenu.
				array_unshift( $submenu[$parent_slug], $new_sub_menu );
			}
			else {
				$position = absint( $position );
				// Grab all the items before the insertion point.
				$before_items = array_slice( $submenu[$parent_slug], 0, $position, TRUE );
				// Grab all the items after the insertion point.
				$after_items = array_slice( $submenu[$parent_slug], $position, NULL, TRUE );
				// Add the new item.
				$before_items[] = $new_sub_menu;
				// Merge the items.
				$submenu[$parent_slug] = array_merge( $before_items, $after_items );
			}
		}

		// Sort the parent array.
		ksort( $submenu[$parent_slug] );

		// No parent as top level.
		$_parent_pages[$url] = $parent_slug;

		return TRUE;
	}
}