<?php
/**
 * This file holds the shiny updates list table class.
 *
 * @package Shiny_Updates
 */

/**
 * List table used on the available updates screen.
 *
 * @since 4.X.0
 */
class Shiny_Updates_List_Table extends WP_List_Table {
	/**
	 * The current WordPress version.
	 *
	 * @since  4.X.0
	 * @access protected
	 *
	 * @var string
	 */
	protected $cur_wp_version;

	/**
	 * The available WordPress version, if applicable.
	 *
	 * @since  4.X.0
	 * @access protected
	 *
	 * @var string|false
	 */
	protected $core_update_version;

	/**
	 * Whether there are any available updates.
	 *
	 * @since 4.X.0
	 * @access protected
	 *
	 * @var bool
	 */
	protected $has_available_updates = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Update' ),
			'plural'   => __( 'Updates' ),
		) );
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @access public
	 * @since  4.X.0
	 * @uses   WP_List_Table::set_pagination_args()
	 */
	public function prepare_items() {
		global $wp_version;

		$this->cur_wp_version = preg_replace( '/-.*$/', '', $wp_version );

		$core_updates = (array) get_core_updates();
		$plugins      = (array) get_plugin_updates();
		$themes       = (array) get_theme_updates();
		$translations = (array) wp_get_translation_updates();

		if ( ! empty( $core_updates ) ) {
			$this->items[] = array(
				'type' => 'core',
				'slug' => 'core',
				'data' => $core_updates,
			);
		}

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$this->items[] = array(
				'type' => 'plugin',
				'slug' => $plugin_file,
				'data' => $plugin_data,
			);
		}

		foreach ( $themes as $stylesheet => $theme ) {
			$this->items[] = array(
				'type' => 'theme',
				'slug' => $stylesheet,
				'data' => $theme,
			);
		}

		if ( ! empty( $translations ) ) {
			$this->items[] = array(
				'type' => 'translations',
				'slug' => 'translations',
				'data' => $translations,
			);
		}

		if ( ! isset( $core_updates[0]->response ) ||
		     'latest' == $core_updates[0]->response ||
		     'development' == $core_updates[0]->response ||
		     version_compare( $core_updates[0]->current, $this->cur_wp_version, '=' )
		) {
			$this->core_update_version = false;
		} else {
			$this->core_update_version = $core_updates[0]->current;
		}

		if ( $this->core_update_version || ! empty( $plugins ) || ! empty( $themes ) || ! empty( $translations ) ) {
			$this->has_available_updates = true;
		}

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->set_pagination_args( array(
			'total_items' => count( $this->items ),
			'per_page'    => count( $this->items ),
			'total_pages' => 1,
		) );
	}

	/**
	 * Message to be displayed when there are no items.
	 *
	 * @since  4.X.0
	 * @access public
	 */
	public function no_items() {
		_e( 'Your site is up to date, there are no available updates.' );
	}

	/**
	 * Get a list of columns.
	 *
	 * @since  4.X.0
	 * @access public
	 *
	 * @return array The list table columns.
	 */
	public function get_columns() {
		ob_start();
		?>
		<form method="post" action="update-core.php?action=do-all-upgrade" name="upgrade-all">
			<?php wp_nonce_field( 'upgrade-core', '_wpnonce' ); ?>
			<button class="button button-primary update-link" data-type="all" type="submit" value="" name="upgrade-all" <?php echo ! $this->has_available_updates ? 'disabled' : ''; ?>>
				<?php esc_attr_e( 'Update All' ); ?>
			</button>
		</form>
		<?php
		$action = ob_get_clean();

		return array(
			'title'  => __( 'Available Updates' ),
			'type'   => __( 'Type' ),
			'action' => $action,
		);
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @since  4.X.0
	 * @access public
	 *
	 * @param object $item The current item.
	 */
	public function single_row( $item ) {
		$data       = '';
		$attributes = array( 'data-type' => $item['type'] );

		if ( 'core' === $item['type'] ) {
			$attributes['data-version'] = esc_attr( $item['data'][0]->current );
			$attributes['data-locale']  = esc_attr( $item['data'][0]->locale );
		} else if ( 'theme' === $item['type'] ) {
			$attributes['data-slug'] = $item['slug'];
		} else if ( 'plugin' === $item['type'] ) {
			$attributes['data-slug']   = $item['data']->update->slug;
			$attributes['data-plugin'] = $item['slug'];
		}

		foreach ( $attributes as $attribute => $value ) {
			$data .= $attribute . '="' . esc_attr( $value ) . '" ';
		}

		echo "<tr $data>";
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Handles the title column output.
	 *
	 * @since  4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_title( $item ) {
		if ( method_exists( $this, 'column_title_' . $item['type'] ) ) {
			call_user_func(
				array( $this, 'column_title_' . $item['type'] ),
				$item
			);
		}
	}

	/**
	 * Handles the title column output for a theme update item.
	 *
	 * @since  4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_title_theme( $item ) {
		/* @var WP_Theme $theme */
		$theme = $item['data'];
		?>
		<p>
			<img src="<?php echo esc_url( $theme->get_screenshot() ); ?>" width="85" height="64" class="updates-table-screenshot" alt=""/>
			<strong><?php echo $theme->display( 'Name' ); ?></strong>
			<?php
			/* translators: 1: theme version, 2: new version */
			printf( __( 'You have version %1$s installed. Update to %2$s.' ),
				$theme->display( 'Version' ),
				$theme->update['new_version']
			);
			?>
		</p>
		<?php
	}

	/**
	 * Handles the title column output for a plugin update item.
	 *
	 * @since  4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_title_plugin( $item ) {
		$plugin = $item['data'];

		// Get plugin compat for running version of WordPress.
		if ( isset( $plugin->update->tested ) && version_compare( $plugin->update->tested, $this->cur_wp_version, '>=' ) ) {
			$compat = '<br />' . sprintf( __( 'Compatibility with WordPress %1$s: 100%% (according to its author)' ), $this->cur_wp_version );
		} elseif ( isset( $plugin->update->compatibility->{$this->cur_wp_version} ) ) {
			$compat = $plugin->update->compatibility->{$this->cur_wp_version};
			$compat = '<br />' . sprintf( __( 'Compatibility with WordPress %1$s: %2$d%% (%3$d "works" votes out of %4$d total)' ), $this->cur_wp_version, $compat->percent, $compat->votes, $compat->total_votes );
		} else {
			$compat = '<br />' . sprintf( __( 'Compatibility with WordPress %1$s: Unknown' ), $this->cur_wp_version );
		}

		// Get plugin compat for updated version of WordPress.
		if ( $this->core_update_version ) {
			if ( isset( $plugin->update->tested ) && version_compare( $plugin->update->tested, $this->core_update_version, '>=' ) ) {
				$compat .= '<br />' . sprintf( __( 'Compatibility with WordPress %1$s: 100%% (according to its author)' ), $this->core_update_version );
			} elseif ( isset( $plugin->update->compatibility->{$this->core_update_version} ) ) {
				$update_compat = $plugin->update->compatibility->{$this->core_update_version};
				$compat .= '<br />' . sprintf( __( 'Compatibility with WordPress %1$s: %2$d%% (%3$d "works" votes out of %4$d total)' ), $this->core_update_version, $update_compat->percent, $update_compat->votes, $update_compat->total_votes );
			} else {
				$compat .= '<br />' . sprintf( __( 'Compatibility with WordPress %1$s: Unknown' ), $this->core_update_version );
			}
		}

		$upgrade_notice = '';

		// Get the upgrade notice for the new plugin version.
		if ( isset( $plugin->update->upgrade_notice ) ) {
			$upgrade_notice = '<br />' . strip_tags( $plugin->update->upgrade_notice );
		}

		$details_url = self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin->update->slug . '&section=changelog&TB_iframe=true&width=640&height=662' );
		$details     = sprintf(
			'<a href="%1$s" class="thickbox open-plugin-details-modal" aria-label="%2$s">%3$s</a>',
			esc_url( $details_url ),
			/* translators: 1: plugin name, 2: version number */
			esc_attr( sprintf( __( 'View %1$s version %2$s details' ), $plugin->Name, $plugin->update->new_version ) ),
			/* translators: %s: plugin version */
			sprintf( __( 'View version %s details.' ), $plugin->update->new_version )
		);
		?>
		<p>
			<strong><?php echo $plugin->Name; ?></strong>
			<?php
			/* translators: 1: plugin version, 2: new version */
			printf( __( 'You have version %1$s installed. Update to %2$s.' ),
				$plugin->Version,
				$plugin->update->new_version
			);
			echo ' ' . $details . $compat . $upgrade_notice;
			?>
		</p>
		<?php
	}

	/**
	 * Handles the title column output for a core update item.
	 *
	 * @since  4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_title_core( $item ) {
		?>
		<p>
			<img src="<?php echo esc_url( admin_url( 'images/wordpress-logo.svg' ) ); ?>" width="85" height="85" class="updates-table-screenshot" alt=""/>
			<strong><?php _e( 'WordPress' ); ?></strong>
			<?php
			foreach ( (array) $item['data'] as $update ) {
				$this->_list_core_update( $update );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Handles the title column output for the translations item.
	 *
	 * @since  4.X.0
	 * @access public
	 */
	public function column_title_translations() {
		?>
		<p>
			<span class="dashicons dashicons-translation"></span>
			<strong><?php _e( 'Translations' ); ?></strong>
		</p>
		<p><?php _e( 'New translations are available.' ); ?></p>
		<?php
	}

	/**
	 * Handles the type column output.
	 *
	 * @since  4.X.0
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_type( $item ) {
		switch ( $item['type'] ) {
			case 'plugin':
				echo __( 'Plugin' );
				break;
			case 'theme':
				echo __( 'Theme' );
				break;
			case 'translations':
				echo __( 'Translations' );
				break;
			default:
				echo __( 'Core' );
				break;
		}
	}

	/**
	 * Get the data attributes for a given list table item.
	 *
	 * @param array $item The current item.
	 *
	 * @return array Data attributes as key value pairs.
	 */
	protected function _get_data_attributes( $item ) {
		$attributes = array();

		if ( 'plugin' === $item['type'] ) {
			$attributes['data-plugin'] = esc_attr( $item['slug'] );
			$attributes['data-slug']   = esc_attr( $item['data']->update->slug );
			$attributes['data-name']   = esc_attr( $item['data']->Name );
			$attributes['aria-label']  = esc_attr( sprintf( __( 'Update %s now' ), $item['data']->Name ) );
		}

		return $attributes;
	}

	/**
	 * Handles the action column output.
	 *
	 * @access public
	 *
	 * @param array $item The current item.
	 */
	public function column_action( $item ) {
		$slug         = $item['slug'];
		$checkbox_id  = 'checkbox_' . md5( $slug );
		$form_action  = sprintf( 'update-core.php?action=do-%s-upgrade', $item['type'] );
		$nonce_action = 'translations' === $item['type'] ? 'upgrade-translations' : 'upgrade-core';
		$data         = '';

		foreach ( $this->_get_data_attributes( $item ) as $attribute => $value ) {
			$data .= $attribute . '="' . esc_attr( $value ) . '" ';
		}

		// No update available, hide button.
		if ( 'core' === $item['type'] && ! $this->core_update_version ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( $form_action ); ?>" name="upgrade-all">
			<?php wp_nonce_field( $nonce_action ); ?>
			<?php if ( 'core' === $item['type'] ) : ?>
				<input name="version" value="<?php echo esc_attr( $item['data'][0]->current ); ?>" type="hidden"/>
				<input name="locale" value="<?php echo esc_attr( $item['data'][0]->locale ); ?>" type="hidden"/>
			<?php else : ?>
				<input type="hidden" name="checked[]" id="<?php echo $checkbox_id; ?>" value="<?php echo esc_attr( $slug ); ?>"/>
			<?php endif; ?>
			<?php
			printf(
				'<button type="submit" name="%1$s" id="%1$s" class="button update-link" %2$s>%3$s</button>',
				'core' === $item['type'] ? 'upgrade' : $checkbox_id,
				$data,
				esc_attr__( 'Update' )
			);
			?>
		</form>
		<?php
	}

	/**
	 * Lists a single core update.
	 *
	 * @global string $wp_version The current WordPress version.
	 *
	 * @since  4.X.0
	 * @access public
	 *
	 * @param object $update The current core update item.
	 */
	protected function _list_core_update( $update ) {
		global $wp_version;

		if ( 'en_US' == $update->locale && 'en_US' == get_locale() ) {
			$version_string = $update->current;
		} // If the only available update is a partial builds, it doesn't need a language-specific version string.
		elseif ( 'en_US' == $update->locale && $update->packages->partial && $wp_version == $update->partial_version && ( $updates = get_core_updates() ) && 1 == count( $updates ) ) {
			$version_string = $update->current;
		} else {
			$version_string = sprintf( '%s&ndash;<code>%s</code>', $update->current, $update->locale );
		}

		$current = false;

		if ( ! isset( $update->response ) || 'latest' == $update->response ) {
			$current = true;
		}

		if ( 'development' == $update->response ) {
			echo '<p>';
			_e( 'You are using a development version of WordPress. You can update to the latest nightly build automatically.' );
			echo '</p>';
		} else {
			if ( $current ) {
				echo '<p>';
				printf( __( 'If you need to re-install version %s, you can do so here.' ), $version_string );
				echo '</p>';

				echo '<form method="post" action="update-core.php?action=do-core-reinstall" name="upgrade" class="upgrade">';
				wp_nonce_field( 'upgrade-core' );
				echo '<p>';
				echo '<input name="version" value="' . esc_attr( $update->current ) . '" type="hidden"/>';
				echo '<input name="locale" value="' . esc_attr( $update->locale ) . '" type="hidden"/>';

				printf(
					'<button type="submit" name="upgrade" id="upgrade" class="button">%s</button>',
					esc_attr__( 'Re-install Now' )
				);

				echo '</p>';

				echo '</form>';
			} else {
				echo '<p>';
				printf( __( 'You can update to <a href="https://codex.wordpress.org/Version_%1$s">WordPress %2$s</a> automatically.' ), $update->current, $version_string );
				echo '</p>';
			}
		}
	}
}
