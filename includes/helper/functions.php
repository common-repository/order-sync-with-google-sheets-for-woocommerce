<?php
/**
 * Re-usable non-OOP helping functions for the plugin
 *
 * @package OrderSyncWithGoogleSheetForWooCommerce
 */


if ( ! function_exists( 'osgsw_get_option' ) ) {
	/**
	 * Get an option from the options table
	 *
	 * @param string $option_name The option name.
	 * @param mixed  $default     The default value.
	 * @return mixed
	 */
	function osgsw_get_option( $option_name, $default = null ) {
		$value = get_option( OSGSW_PREFIX . $option_name );
		if ( ! $value ) {
			return $default;
		}
		return apply_filters( 'osgsw_get_' . $option_name, $value );
	}
}

// update option.
if ( ! function_exists( 'osgsw_update_option' ) ) {
	/**
	 * Update an option in the options table
	 *
	 * @param string $option_name The option name.
	 * @param mixed  $value       The value.
	 * @return bool
	 */
	function osgsw_update_option( $option_name, $value ) {
		$value = apply_filters( 'osgsw_update_' . $option_name, $value );
		do_action( 'osgsw_before_update_' . $option_name, $value );
		$updated = update_option( OSGSW_PREFIX . $option_name, $value );
		if ( $updated ) {
			do_action( 'osgsw_updated_' . $option_name, $value );
		}
		return $updated;
	}
}

/**
 * Get all custom meta fields data
 *
 * @return array
 */
if ( ! function_exists( 'osgsw_get_product_custom_fields' ) ) {
	/**
	 *  Get custom meta fields data.
	 *
	 * @return array
	 */
	function osgsw_get_product_custom_fields() {
		global $wpdb;
		$custom_order_table = get_option( 'woocommerce_custom_orders_table_enabled' );

		if ( isset( $custom_order_table ) && 'yes' === $custom_order_table ) {
			$ordermeta_table = $wpdb->prefix . 'wc_orders_meta';
			$order_table = $wpdb->prefix . 'wc_orders';

			$sql = "SELECT DISTINCT pm.meta_key, pm.meta_value
			FROM $ordermeta_table pm
			INNER JOIN $order_table p ON pm.order_id = p.id
			WHERE p.type = %s";
			$sql = $wpdb->prepare($sql,'shop_order');//phpcs:ignore

		} else {
			$ordermeta_table = $wpdb->prefix . 'postmeta';
			$order_table = $wpdb->prefix . 'posts';

			$sql = "SELECT DISTINCT pm.meta_key, pm.meta_value
				FROM $ordermeta_table pm
				INNER JOIN $order_table p ON pm.post_id = p.ID
				WHERE p.post_type = %s";

			$sql = $wpdb->prepare($sql,'shop_order');//phpcs:ignore
		}

		$results = $wpdb->get_results($sql);//phpcs:ignore
		$custom_fields = [];
		foreach ( $results as $result ) {
			$custom_fields[ $result->meta_key ] = $result->meta_key;
		}
		$item_meta = osgsw_get_order_item_meta();
		$all_fields = $custom_fields + $item_meta;
		return $all_fields;
	}
}
/**
 * Get all custom order item meta fields data
 *
 * @return array
 */
if ( ! function_exists( 'osgsw_get_order_item_meta' ) ) {
	/**
	 * Get custom order item meta fields data.
	 *
	 * @return array
	 */
	function osgsw_get_order_item_meta() {
		global $wpdb;
		$order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$order_items_table = $wpdb->prefix . 'woocommerce_order_items';

		$sql = "SELECT DISTINCT oim.meta_key, oim.meta_value
			FROM $order_itemmeta_table oim
			INNER JOIN $order_items_table oi ON oim.order_item_id = oi.order_item_id";

		$results = $wpdb->get_results($sql); // phpcs:ignore
		$custom_fields = [];

		foreach ( $results as $result ) {
			$meta_key = $result->meta_key . '(Itemmeta)';
			$custom_fields[ $meta_key ] = $result->meta_key;
		}

		return $custom_fields;
	}
}
function osgsw_divided_prefix($text, $keyword) {
	if (strpos($text, $keyword) !== false) {
		$parts = explode($keyword, $text);
		return [
			'before' => $parts[0],
			'keyword' => $keyword,
		];
	} else {
		return false;
	}
}
/**
 * Is Ultimate License activated?
 *
 * @return bool
 * @version 1.0.0
 */
 function osgsw_is_ultimate_license_activated() {
	$ultimate_license = get_option( 'osgsw_license_active' );
	if ( true == $ultimate_license ) { //phpcs:ignore
		return true;
	}
	return false;
}
/**
 *  Get all the custom order status.
 *
 * @return array
 */
function ossgsw_get_order_statuses() {
	$osgsw_status = osgsw_get_option('custom_order_status_bolean');
	if (osgsw_is_ultimate_license_activated() && $osgsw_status ) {
		$status = [
			'wc-pending',
			'wc-processing',
			'wc-on-hold',
			'wc-completed',
			'wc-cancelled',
			'wc-refunded',
			'wc-failed',
			'wc-checkout-draft'
		];
		$status = apply_filters('osgsw_update_custom_order_status', $status);
		return $status;
	} else {
		return [ 
			'wc-pending',
			'wc-processing',
			'wc-on-hold',
			'wc-completed',
			'wc-cancelled',
			'wc-refunded',
			'wc-failed',
			'wc-checkout-draft'
		];
	}

}

/**
 * Save meta fields value
 *
 * @param  int   $id The option id.
 * @param  mixed $meta_key       The meta key.
 * @param  mixed $value       The meta value.
 *
 * @return void
 */
function osgsw_meta_field_value_save( $id, $meta_key, $value ) {
	if ( function_exists( 'get_field' ) ) {
		$field_object = acf_get_field( $meta_key );
		if ( is_array( $field_object ) && ! empty( $field_object ) ) {
			if ( array_key_exists( 'choices', $field_object ) ) {
				$choices = $field_object['choices'];
				if ( array_key_exists( $value, $choices ) ) {
					update_post_meta( $id, $meta_key, $value );
				}
			} else {
				update_post_meta( $id, $meta_key, $value );
			}
		} else {
			update_post_meta( $id, $meta_key, $value );
		}
	} else {
		update_post_meta( $id, $meta_key, $value );
	}
}

/**
 * Check acf fields type
 *
 * @param string $meta_key acf meta key.
 * @return string
 */
function check_osgsw_file_type( $meta_key ) {
	$all_acf_type = osgsw_all_type_field_in_acf();
	if ( function_exists( 'get_field' ) ) {
		$field_object = acf_get_field( $meta_key );
		if ( is_array( $field_object ) && ! empty( $field_object ) ) {
			if ( array_key_exists( 'type', $field_object ) ) {
				if ( 'select' === $field_object['type'] ) {
					if ( 1 === $field_object['multiple'] ) {
						return 'not_suported';
					}
				} else if ( in_array( $field_object['type'], $all_acf_type ) ) {
					return 'not_suported';
				}
			}
		}
	}
	return 'suported';
}




/**
 * Collection of all type fields in acf.
 *
 * @return array
 */
function osgsw_all_type_field_in_acf() {
	$field_types = [
		'wysiwyg',
		'image',
		'file',
		'checkbox',
		'post_object',
		'page_link',
		'relationship',
		'taxonomy',
		'user',
		'google_map',
		'date_picker',
		'date_time_picker',
		'time_picker',
		'message',
		'tab',
		'group',
		'repeater',
		'flexible_content',
		'clone',
		'accordion',
		'gallery',
		'block',
		'nav_menu',
		'post_taxonomy',
		'sidebar',
		'widget_area',
		'user_role',
		'true_false',
		'button_group',
		'link',
	];
	return $field_types;
}


/**
 * Sql reserved keyword check
 *
 * @param string $key key.
 * @return string
 */
function osgsw_reserved_keyword( $key ) {
	$reserved_keywords = [
		'ADD',
		'ALL',
		'ALTER',
		'AND',
		'AS',
		'ASC',
		'BETWEEN',
		'BY',
		'CHAR',
		'CHECK',
		'COLUMN',
		'CONNECT',
		'CREATE',
		'CURRENT',
		'DECIMAL',
		'DEFAULT',
		'DELETE',
		'DESC',
		'DISTINCT',
		'DROP',
		'ELSE',
		'EXCLUSIVE',
		'EXISTS',
		'FILE',
		'FLOAT',
		'FOR',
		'FROM',
		'GRANT',
		'GROUP',
		'HAVING',
		'IDENTIFIED',
		'IMMEDIATE',
		'IN',
		'INCREMENT',
		'INDEX',
		'INITIAL',
		'INSERT',
		'INTEGER',
		'INTERSECT',
		'INTO',
		'IS',
		'JOIN',
		'LIKE',
		'LOCK',
		'LONG',
		'MAXEXTENTS',
		'MINUS',
		'MLSLABEL',
		'MODE',
		'MODIFY',
		'NOAUDIT',
		'NOCOMPRESS',
		'NOT',
		'NOWAIT',
		'NULL',
		'NUMBER',
		'OF',
		'OFFLINE',
		'ON',
		'ONLINE',
		'OPTION',
		'OR',
		'ORDER',
		'PCTFREE',
		'PRIOR',
		'PRIVILEGES',
		'PUBLIC',
		'RAW',
		'RENAME',
		'RESOURCE',
		'REVOKE',
		'ROW',
		'ROWID',
		'ROWNUM',
		'ROWS',
		'SELECT',
		'SESSION',
		'SET',
		'SMALLINT',
		'START',
		'SYNONYM',
		'SYSDATE',
		'TABLE',
		'THEN',
		'TO',
		'TRIGGER',
		'UID',
		'UNION',
		'UNIQUE',
		'UPDATE',
		'USER',
		'VALIDATE',
		'VALUES',
		'VARCHAR',
		'VIEW',
		'WHENEVER',
		'WHERE',
		'WITH',
		'RANGE',
		'LIMIT',
		'GROUP BY',
		'ORDER BY',
		'WHERE',
		'FROM',
	];
	$value_lower = strtolower( $key );
	$keywords_lower = array_map( 'strtolower', $reserved_keywords );
	if ( in_array( $value_lower, $keywords_lower ) ) {
		return 'yes';
	} else {
		return 'no';
	}
}


if ( ! function_exists( 'osgsw' ) ) {
	/**
	 * Get the app instance
	 *
	 * @return App
	 */
	function osgsw() {
		return new \OrderSyncWithGoogleSheetForWooCommerce\App();
	}
}


/**
 * Convert readable string for serialized data
 */
function osgsw_serialize_to_readable_string($data) {
	
    // Check if the data is serialized and unserialize it
    if (is_serialized($data)) {
        $data = maybe_unserialize($data);
    }

    if (is_array($data)) {
        $result = [];
        foreach ($data as $key => $value) {
            $result[] = $key . ': ' . osgsw_serialize_to_readable_string($value);
        }
        return implode(', ', $result);
    } elseif (is_object($data)) {
        $result = [];
        foreach (get_object_vars($data) as $key => $value) {
            $result[] = $key . ': ' . osgsw_serialize_to_readable_string($value);
        }
        return implode(', ', $result);
    } else {
        return isset($data) ? (string)$data : '';
    }
}

/**
 * Format item meta data
 *
 * @return string
 */

 function ssgsw_format_item_meta($data) {
	if (strpos($data, 'ssgsw_sep,') !== false) {
		$items = explode('ssgsw_sep,', $data);
		if( is_array($items) && !empty($items)) {
			$new_data = '';
			foreach($items as $key => $item) {
				$new_data .=', ' . ssgsw_find_out_item_meta_values($item);
			}
			return $new_data;
		}
	} else {
		return ssgsw_find_out_item_meta_values($data);
	}
 }

 /**
  * Find out item_meta values
  *
  */
function ssgsw_find_out_item_meta_values($items) {
	if (strpos($items, '(ssgsw_itemmeta_value:') !== false ) {
		// Explode the item string to separate the plain text and the serialized data
		$exploded_data = explode('(ssgsw_itemmeta_value:', $items);
		$plain_text = isset($exploded_data[0]) ? trim($exploded_data[0]) : '';
    	$remaining_data = isset($exploded_data[1]) ? $exploded_data[1] : '';
		$serialized_data = str_replace('ssgsw_itemmeta_end)', '', $remaining_data);
		$new_sr  = osgsw_serialize_to_readable_string($serialized_data);
		if (!empty($plain_text)) {
			return $plain_text . '('. $new_sr .')';
		} else {
			return $new_sr;
		}
	} else {
		return osgsw_serialize_to_readable_string($items);
	}
}


/**
 * Check if the Barcode Scanner Lite POS plugin is active.
 *
 * This function checks whether the "Barcode Scanner Lite POS" plugin
 * is active by verifying its file path in the WordPress plugins directory.
 * 
 * @return bool True if the plugin is active, false otherwise.
 */
function ssgsw_is_barcode_scanner_plugin_active() {
    if ( ! function_exists('is_plugin_active') ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    // Define the relative path to the main plugin file of Barcode Scanner Lite POS.
    $plugin_path = 'barcode-scanner-lite-pos-to-manage-products-inventory-and-orders/barcode-scanner.php';
    if ( is_plugin_active($plugin_path) ) {
        return true;
    } else {
        return false;
    }
}
