<?php
/**
 * Class ORder
 * includes/model/order.php
 *
 * @package OrderSyncWithGoogleSheetForWooCommerce
 */

namespace OrderSyncWithGoogleSheetForWooCommerce;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! class_exists( '\OrderSyncWithGoogleSheetForWooCommerce\Order' ) ) {
	/**
	 * Class Order
	 */
	class Order extends Base {
		/**
		 * Utility Trait to use in all classes globally
		 */
		use Utilities;

		/**
		 * Sync all orders
		 *
		 * @param boolean $condition Sync condtion applied.
		 */
		public function sync_all( $condition = false ) {//phpcs:ignore
			if ( $this->app->is_plugin_ready() !== true ) {
				/**
				 * Reason for commenting this code is It was giving a fatal error when someone trying to create a new order after installing our plugin but without
				 * completing the setup.
				 *
				 * @todo: I think It's alright to return for now. later we can handle this exception.
				 */
				// throw new \Exception( 'Configure the plugin first.' );.
				return false;
			}

			$order_data_storage_option = get_option( 'woocommerce_custom_orders_table_enabled' );
			if ( 'yes' === $order_data_storage_option ) {
				$data = $this->get_all_hpos_orders();
			} else {
				$data = $this->get_all_orders();
			}
			$data = $this->marge_header_with_data($data);
			$google_sheet = new Sheet();
			$updated = $google_sheet->update_values( 'A1', $data );
			return wp_validate_boolean( $updated );
		}

		/**
		 * Marge header with data
		 *
		 * @return array
		 */
		public function marge_header_with_data($data, $single = false) {
			$data = $this->duplicate_based_on_product_names($data);
			$columns = new Column();
			$headers = [ $columns->get_column_names() ];
			return array_merge( $headers, $data );
		}
		/**
		 * Divied products items.
		 *
		 * @param array $data
		 */
		public function duplicate_based_on_product_names($data) {
			$order_items = get_option('osgsw_multiple_itmes', false );
			$order_qty = get_option('osgsw_show_product_qt', false );
			$total_items = get_option('osgsw_sync_total_items', false );
			
			$sync_total_price = get_option('osgsw_sync_total_price', false);
			return array_reduce($data, function ($result, $row) use ($order_items, $order_qty, $sync_total_price, $total_items) {
				if($order_items) {
					if (isset($row['order_items']) && !empty($row['order_items']) && strpos($row['order_items'], 'ssgsw_wppool_,') !== false) {
						$products = explode('ssgsw_wppool_, ', $row['order_items']);
						$count = is_array($products) ? count($products) : 0;
						$row['order_items'] = $row['order_items'] . '['.$count.' Products]';
						$result[] = $this->format_serilization_data($row, false , 0, $order_qty);
					
						foreach ($products as $key_p => $product) {
							$key_up = $key_p + 1;
							$new_row = $row;
							if($order_qty) {
								$new_row['order_items'] = $this->format_qty_beside_product($product);
							} else {
								$new_row['order_items'] = $this->remove_ssgsw_text($product);
							}
							$new_row['order_items'] = $new_row['order_items']. ''. '[Product '. $key_up .' of ' . $count.']';
							if($total_items) {
								$new_row['qty'] = $this->get_osgsw_dynamic_qty_and_price($product, 'ssgsw_wppool_qty');
							}
							if($sync_total_price) {
								$new_row['order_total'] = $this->get_osgsw_dynamic_qty_and_price($product, 'ssgsw_wppool_price');
							}
							$result[] = $this->format_serilization_data($new_row, true, $key_p, $order_qty);
						}
					} else {
						$result[] = $this->format_serilization_data($row, false , 0, $order_qty);
					}
				} else {
					$result[] = $this->format_serilization_data($row, false , 0, $order_qty);
				}
				return $result;
			}, []);
		}
		/**
		 * Format qty for beside the product.
		 *
		 * @return string
		 */
		public function format_qty_beside_product($inputText) {
			$outputText = preg_replace('/ssgsw_wppool_qty\s*:\s*([\d]+),?\s*(ssgsw_wppool_price\s*:\s*[\d]+\s*)?/', 'qty: $1', $inputText);
			return $outputText;
		}
		/**
		 * Remove osgsw_text from order.
		 *
		 * @return string
		 */
		public function remove_ssgsw_text($inputText) {
			if (strpos($inputText, 'ssgsw_wppool_') !== false) {
				$outputText = preg_replace('/\([^()]*ssgsw_wppool_[^()]*\)/', '', $inputText);
				return trim($outputText);
			}
			return $inputText;
		}
		/**
		 * Get qty and price value
		 *
		 * @return mixed
		 */
		public function get_osgsw_dynamic_qty_and_price($inputText, $key) {
			$pattern = '/\(([^)]+)\)/';
			preg_match($pattern, $inputText, $matches);
			if (!empty($matches)) {
				$valuePattern = '/'. preg_quote($key, '/') .'\s*:\s*([\d]+)/';
				preg_match($valuePattern, $matches[1], $valueMatches);
				
				if (!empty($valueMatches)) {
					return $valueMatches[1];
				}
			}
			
			return null;
		}
		/**
		 * Format serilization data.
		 *
		 * @return array
		 */
		public function format_serilization_data($rows = [], $sub = false , $key1 = 0, $qty = false ) {
			$formatted_row = [];
			foreach ($rows as $key => $row) {
				if ($sub) {
					$condition = $this->collection_of_visiable_values($key);
					if ( 2 === $condition ) {
						$formatted_row[$key] = ssgsw_format_item_meta($row);
					} else if( 3 === $condition ) {
						$new_meta = $this->split_by_delimiter($row, $key1);
						if(empty($new_meta)) {
							$unserialize = ssgsw_format_item_meta($row);
							$formatted_row[$key] = $unserialize;
						} else {
							$formatted_row[$key] = ssgsw_format_item_meta($new_meta);
						}
					} else {
						$formatted_row[$key] = '';
					}
				} else{
					if ($key === 'order_items') {
						if ($qty) {
							$formatted_row[ $key ] = $this->remove_osgsw_sep_from_text($row, 'ssgsw_wppool_');
						} else {
							$replace = $this->remove_ssgsw_text($row);
							$formatted_row[ $key ] = $this->remove_osgsw_sep_from_text($replace, 'ssgsw_wppool_');
						}
					} else {
						$unserialize_data = ssgsw_format_item_meta($row);
						$formatted_row[ $key ] = $this->remove_osgsw_sep_from_text($unserialize_data, 'ssgsw_sep');
					}
				}
				
			}
			return array_values( (array) $formatted_row);
		}
		/**
		 * Remove osgsw separater from text
		 *
		 * @return string
		 */
		public function remove_osgsw_sep_from_text($input_text, $delimiter ) {
			if (strpos($input_text, $delimiter) !== false) {
				$output_text = str_replace($delimiter, '', $input_text);
				return $output_text;
			}
			return $input_text;
		}
		/**
		 * Split sting to array
		 *
		 * @return string.
		 */
		public function split_by_delimiter($input_text, $key) {
			$delimiter = 'ssgsw_sep,';
			if (strpos($input_text, $delimiter) !== false) {
				$result_array = preg_split('/' . preg_quote($delimiter, '/') . '/', $input_text, -1, PREG_SPLIT_NO_EMPTY);
				return isset($result_array[$key]) ? $result_array[$key] :'';
			}
			return '';
		}
		/**
		 * Callection of visiable values for individual row products.
		 *
		 * @return boolean
		 */
		 public function collection_of_visiable_values($key) {
			$cols = [
				'order_id',
				'order_items',
				'status',
				'qty',
				'order_total',
			];
			if ( in_array($key, $cols) ) {
				return 2;
			}
			$text = "Itemmeta";
			if (strpos($key, $text) !== false) {
				return 3;
			}

		 }
		/**
		 * Get all orders
		 *
		 * @param int $order_id for retribing single order.
		 * @param int $trash for deleting single order.
		 *
		 * @return array
		 */
		public function get_all_orders( $order_id = null, $trash = false ) {
			global $wpdb;
			$total_discount   = true === wp_validate_boolean( osgsw_get_option( 'total_discount', false ) );
			$shipping_details = true === wp_validate_boolean( osgsw_get_option( 'add_shipping_details_sheet', false ) );
			$order_date       = true === wp_validate_boolean( osgsw_get_option( 'show_order_date', false ) );
			$payment_method   = true === wp_validate_boolean( osgsw_get_option( 'show_payment_method', false ) );
			$customer_note    = true === wp_validate_boolean( osgsw_get_option( 'show_customer_note', false ) );
			$order_url        = true === wp_validate_boolean( osgsw_get_option( 'show_order_url', false ) );
			$who_place_order  = true === wp_validate_boolean( osgsw_get_option( 'who_place_order', false ) );
			$show_product_qt  = true === wp_validate_boolean( osgsw_get_option( 'show_product_qt', false ) );
			$show_custom_meta_fields = true === wp_validate_boolean( osgsw_get_option( 'show_custom_meta_fields', false ) );
			$show_custom_fields = true === wp_validate_boolean( osgsw_get_option( 'show_custom_fields', false ) );
			$sync_total_items = true === wp_validate_boolean( osgsw_get_option( 'sync_total_items', false ) );
			$sync_total_price = true === wp_validate_boolean( osgsw_get_option( 'sync_total_price', false ) );
			$billing_deatils  = true === wp_validate_boolean( osgsw_get_option( 'show_billing_details', false ) );
			$order_urls       = esc_url( admin_url( 'post.php?' ) );
			$order = 'SELECT
				p.ID as order_id';
			$order .= ", (SELECT IFNULL(GROUP_CONCAT(CONCAT(oi.order_item_name, ' (ssgsw_wppool_qty: ', IFNULL(c.meta_value, '0'), ',ssgsw_wppool_price: ', IFNULL(pt.meta_value, '0'), ')') SEPARATOR 'ssgsw_wppool_, '), '** No Products **') FROM {$wpdb->prefix}woocommerce_order_items AS oi LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS c ON oi.order_item_id = c.order_item_id AND c.meta_key = '_qty' LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS pt ON oi.order_item_id = pt.order_item_id AND pt.meta_key = '_line_total' WHERE oi.order_item_type = 'line_item' AND oi.order_id = p.ID) AS order_items";
			$order .= ', p.post_status as status';
			if ( $sync_total_items ) {
				$order .= ", (SELECT IFNULL(SUM(c.meta_value), 0 ) FROM {$wpdb->prefix}woocommerce_order_items AS oi JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS c ON c.order_item_id = oi.order_item_id AND c.meta_key = '_qty' WHERE oi.order_id = p.ID) AS qty";
			}
			if ( $sync_total_price ) {
				$order .= ", MAX( CASE WHEN pm.meta_key = '_order_total' AND p.ID = pm.post_id THEN pm.meta_value END ) as order_total";
			}
			if (defined('OSGSW_ULTIMATE_VERSION') && OSGSW_ULTIMATE_VERSION > '1.0.5') {
				$order = apply_filters('ossgs_order_post_fields', $order);
			} else {
				if ( $total_discount ) {
					$order .= ", MAX( CASE WHEN pm.meta_key = '_cart_discount' AND p.ID = pm.post_id THEN pm.meta_value END ) as discount";
				}
				if ( $billing_deatils ) {
					$order .= ", IFNULL(MAX( CASE WHEN pm.meta_key = '_billing_address_index' AND p.ID = pm.post_id THEN pm.meta_value END ), 'Billing Details Not Set' ) as billing_details";
				}
				if ( $shipping_details ) {
					$order .= ", IFNULL(MAX( CASE WHEN pm.meta_key = '_shipping_address_index' AND p.ID = pm.post_id THEN pm.meta_value END ), 'Shipping Details Not Set' ) as shipping_details";
				}
				if ( $order_date ) {
					$order .= ', p.post_date';
				}
				if ( $payment_method ) {
					$order .= ", IFNULL(MAX( CASE WHEN pm.meta_key = '_payment_method' AND p.ID = pm.post_id THEN pm.meta_value END ), 'No Method Selected' ) as method_title";
				}
				if ( $customer_note ) {
					$order .= ", IFNULL(p.post_excerpt, 'No notes from customer' ) as customer_note";
				}
				if ( $who_place_order ) {
					$order .= ", IFNULL(
						CASE 
							WHEN MAX(CASE WHEN pm.meta_key = '_customer_user' AND p.ID = pm.post_id THEN pm.meta_value END) = 0 THEN 'Anonymous user'
							ELSE (SELECT u.user_login FROM {$wpdb->prefix}users u WHERE u.ID = MAX(CASE WHEN pm.meta_key = '_customer_user' AND p.ID = pm.post_id THEN pm.meta_value END))
						END, 
						'Anonymous user'
					) as who_place_order";
				}
				if ( $order_url ) {
					$order .= ', ';
					$order .= "CONCAT('" . $order_urls . "','post=',pm.post_id,'&action=edit')";
					$order .= ' as order_urls';
				}
				if ( $show_custom_meta_fields && $show_custom_fields ) {
					$custom_fields = osgsw_get_option( 'show_custom_fields' );
					foreach ( $custom_fields as $value ) {
						$item_extis = osgsw_divided_prefix($value, '(Itemmeta)');
						if ($item_extis) {
							$value2 = $item_extis['before'];
							$custom_field = $wpdb->prepare(
								"(
									SELECT IFNULL(
										GROUP_CONCAT(CONCAT(order_item_name, '(ssgsw_itemmeta_value:', c.meta_value, 'ssgsw_itemmeta_end)') SEPARATOR 'ssgsw_sep, '), 
										'** No Item Found **'
									) 
									FROM {$wpdb->prefix}woocommerce_order_items AS oi 
									LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS c 
									ON oi.order_item_id = c.order_item_id 
									AND c.meta_key = %s 
									WHERE oi.order_id = p.ID
								) AS %s",
								$value2, $value
							);
							$order .= ', ' . $custom_field;
						} else {
							$custom_field = $wpdb->prepare("IFNULL(MAX( CASE WHEN pm.meta_key = %s AND p.ID = pm.post_id THEN pm.meta_value END ), '' ) as %s", $value, $value);
							$order .= ', ' . $custom_field;
						}
						
					}
				}
			}

			$order .= " FROM 
				{$wpdb->prefix}posts p 
				LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
				LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta b ON oi.order_item_id = b.order_item_id
				WHERE ";
			if ( $order_id ) {
				$order .= "p.ID = $order_id AND ";
			}
			$order .= "p.post_type = 'shop_order' ";
			if ( ! $trash ) {
				$order .= "AND p.post_status NOT IN ('trash', 'auto-draft') ";
			}
			$order .= 'GROUP BY p.ID';
			$posts   = $wpdb->get_results( $order, ARRAY_A ); //phpcs:ignore
			return $posts;
		}
		/**
		 * Get Hpos orders
		 *
		 * @param int   $order_id for retribing single order.
		 * @param mixed $trash for deleting single order.
		 *
		 * @return array
		 */
		public function get_all_hpos_orders( $order_id = null, $trash = false ) {
			global $wpdb;
			$total_discount   = true === wp_validate_boolean( osgsw_get_option( 'total_discount', false ) );
			$shipping_details = true === wp_validate_boolean( osgsw_get_option( 'add_shipping_details_sheet', false ) );
			$order_date       = true === wp_validate_boolean( osgsw_get_option( 'show_order_date', false ) );
			$payment_method   = true === wp_validate_boolean( osgsw_get_option( 'show_payment_method', false ) );
			$customer_note    = true === wp_validate_boolean( osgsw_get_option( 'show_customer_note', false ) );
			$order_url        = true === wp_validate_boolean( osgsw_get_option( 'show_order_url', false ) );
			$who_place_order  = true === wp_validate_boolean( osgsw_get_option( 'who_place_order', false ) );
			$show_product_qt  = true === wp_validate_boolean( osgsw_get_option( 'show_product_qt', false ) );
			$show_custom_meta_fields = true === wp_validate_boolean( osgsw_get_option( 'show_custom_meta_fields', false ) );
			$show_custom_fields = true === wp_validate_boolean( osgsw_get_option( 'show_custom_fields', false ) );
			$sync_total_items = true === wp_validate_boolean( osgsw_get_option( 'sync_total_items', false ) );
			$sync_total_price = true === wp_validate_boolean( osgsw_get_option( 'sync_total_price', false ) );
			$billing_deatils  = true === wp_validate_boolean( osgsw_get_option( 'show_billing_details', false ) );
			$order_urls       = esc_url( admin_url( 'post.php?' ) );
			$order = 'SELECT
				p.id as order_id';
			$order .= ", (SELECT IFNULL(GROUP_CONCAT(CONCAT(oi.order_item_name, ' (ssgsw_wppool_qty: ', IFNULL(c.meta_value, '0'), ',ssgsw_wppool_price: ', IFNULL(pt.meta_value, '0'), ')') SEPARATOR 'ssgsw_wppool_, '), '** No Products **') FROM {$wpdb->prefix}woocommerce_order_items AS oi LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS c ON oi.order_item_id = c.order_item_id AND c.meta_key = '_qty' LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS pt ON oi.order_item_id = pt.order_item_id AND pt.meta_key = '_line_total' WHERE oi.order_item_type = 'line_item' AND oi.order_id = p.id) AS order_items";
			$order .= ', p.status';
			if ( $sync_total_items ) {
				$order .= ", (SELECT IFNULL(SUM(c.meta_value), 0 ) FROM {$wpdb->prefix}woocommerce_order_items AS oi JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS c ON c.order_item_id = oi.order_item_id AND c.meta_key = '_qty' WHERE oi.order_id = p.id) AS qty";
			}
			if ( $sync_total_price ) {
				$order .= ', MAX(p.total_amount) as order_total';
			}
			if (defined('OSGSW_ULTIMATE_VERSION') && OSGSW_ULTIMATE_VERSION > '1.0.5') {
				$order = apply_filters('ossgs_order_hpos_fields',$order);
			} else {
				if ( $total_discount ) {
					$order .= ', MAX( cf.discount_total_amount ) as discount';
				}
				if ( $billing_deatils ) {
					$order .= ", IFNULL(MAX( CASE WHEN pm.meta_key = '_billing_address_index' AND p.id = pm.order_id THEN pm.meta_value END ), 'Billing Details Not Set' ) as billing_details";
				}
				if ( $shipping_details ) {
					$order .= ", IFNULL(MAX( CASE WHEN pm.meta_key = '_shipping_address_index' AND p.id = pm.order_id THEN pm.meta_value END ), 'Shipping Details Not Set' ) as shipping_details";
				}
				if ( $order_date ) {
					$order .= ', p.date_created_gmt';
				}
				if ( $payment_method ) {
					$order .= ", IFNULL(p.payment_method_title, 'No Method Selected' ) as method_title";
				}
				if ( $customer_note ) {
					$order .= ", IFNULL(p.customer_note, 'No notes from customer' ) as customer_note";
				}
				if ( $who_place_order ) {
					$order .= ", IFNULL(
						CASE 
							WHEN MAX(p.customer_id) = 0 THEN 'Anonymous user'
							ELSE (SELECT u.user_login FROM {$wpdb->prefix}users u WHERE u.ID = p.customer_id)
						END, 
						'Anonymous user'
					) as who_place_order";
				}
				if ( $order_url ) {
					$order .= ', ';
					$order .= "CONCAT('" . $order_urls . "','post=',pm.order_id,'&action=edit')";
					$order .= ' as order_urls';
				}
				if ( $show_custom_meta_fields && $show_custom_fields ) {
					$custom_fields = osgsw_get_option( 'show_custom_fields' );
					foreach ( $custom_fields as $value ) {
						$item_extis = osgsw_divided_prefix($value, '(Itemmeta)');
						if ($item_extis) {
							$value2 = $item_extis['before'];
							$custom_field = $wpdb->prepare(
								"(
									SELECT IFNULL(
										GROUP_CONCAT(CONCAT(order_item_name, '(ssgsw_itemmeta_value:', c.meta_value, 'ssgsw_itemmeta_end)') SEPARATOR 'ssgsw_sep, '), 
										'** No Item Found **'
									) 
									FROM {$wpdb->prefix}woocommerce_order_items AS oi 
									LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS c 
									ON oi.order_item_id = c.order_item_id 
									AND c.meta_key = %s 
									WHERE oi.order_id = p.id
								) AS %s",
								$value2, $value
							);
							$order .= ', ' . $custom_field;
						} else {
							$custom_field = $wpdb->prepare("IFNULL(MAX( CASE WHEN pm.meta_key = %s AND p.id = pm.order_id THEN pm.meta_value END ), '' ) as %s", $value, $value);
							$order .= ', ' . $custom_field;
						}
						
					}
				}
			}
			$order .= " FROM 
				{$wpdb->prefix}wc_orders p 
				LEFT JOIN {$wpdb->prefix}wc_orders_meta pm ON p.id = pm.order_id
				LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.id = oi.order_id
				LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta b ON oi.order_item_id = b.order_item_id 
				LEFT JOIN {$wpdb->prefix}wc_order_operational_data cf ON p.id = cf.order_id 
				WHERE ";
			if ( $order_id ) {
				$order .= "p.id = $order_id AND ";
			}
			$order .= "p.type = 'shop_order' ";
			if ( ! $trash ) {
				$order .= "AND p.status NOT IN ('trash', 'auto-draft') ";
			}
			$order .= 'GROUP BY p.id';
			$posts   = $wpdb->get_results( $order, ARRAY_A ); //phpcs:ignore
			return $posts;
		}

		/**
		 * Find out row key form google sheets info
		 *
		 * @param array $data sheet information.
		 * @param mixed $id product id.
		 *
		 * @return mixed
		 */
		public function find_out_range_row( $data, $id ) {
			$new_index = null;
			foreach ( $data as $row => $row_data ) {
				if ( is_array($row_data) && array_key_exists( 0, $row_data ) ) {
					if ( $row_data[0] == $id ) { //phpcs:ignore
						$new_index = $row + 1;
						break;
					}
				}
			}
			return $new_index;
		}
		/**
		 * Get first sheets data and compare id exits and resturn range
		 *
		 * @param mixed $id product id.
		 * @param mixed $data first sheets data.
		 *
		 * @return mixed
		 */
		public function find_out_range( $id, $data ) {
			$matching_row_index = null;
			if ( is_array( $data ) && ! empty( $data ) ) {
				$matching_row_index = $this->find_out_range_row($data, $id );
			}
			return $matching_row_index;
		}
		/**
		 * Update orders.
		 *
		 * @param array $orders Orders.
		 *
		 * @return mixed
		 */
		public function bulk_update( $orders = [] ) {
			$column = new Column();

			$get_bulk_edit_option = wp_validate_boolean(get_option( 'osgsw_bulk_edit_option2', 1));
			$sheet = new Sheet();
			$sheets_info = [];
			if ( is_array($orders) && ! array_key_exists( 'index_number', $orders['0'] ) ) {
				$sheets_info = $sheet->get_first_columns();
			}
			/**
			 * Check if plugin is ready to use
			 */

			foreach ( $orders as $key => $order ) {
				$this->update_hpos_order_status($order['order_status'], $order['order_Id']);
				$this->update_post_table_order_status($order['order_status'], $order['order_Id']);

				if ( isset($order['order_Id']) && ! empty($order['order_Id']) ) {
					if ( ! empty($sheets_info) ) {
						$this->batch_update_delete_and_append($order['order_Id'], 'update', null,$sheets_info);
					} else {
						$find_out_range = $this->find_out_child_ranges($order['product_names'], $order['index_number'] );
						$start_index = isset($find_out_range[0]) ? $find_out_range[0] : null;
						$end_index = isset($find_out_range[1]) ? $find_out_range[1] : null;
						$this->batch_update_delete_and_append($order['order_Id'], 'update', $start_index, [], $end_index);
					}
				}
				if ( ! $get_bulk_edit_option ) {
					return false;
				}
			}
		}
		/**
		 *  Find out child and parent ranges from the text.
		 *
		 * @return string
		 */
		public function find_out_child_ranges($input_text, $index_number) {
			$order_items = get_option('osgsw_multiple_itmes', false );
			if ($order_items) {
				if (preg_match('/\[(\d+) Products\]\s*$/', $input_text, $matches)) {
					// Match [X Products] at the end of the string.
					$value = isset($matches[1]) ? intval($matches[1]) : null;
					if ($value) {
						return [
							$index_number,
							$index_number + $value
						];
					}
				}
				if (preg_match('/\[Product (\d+) of (\d+)\]\s*$/', $input_text, $matches)) {
					// Match [Product Y of Z] at the end of the string.
					if (!empty($matches)) {
						$first = isset($matches[1]) ? intval($matches[1]) : 1;
						$end = isset($matches[2]) ? intval($matches[2]) : 1;
						$first_index = $index_number - $first;
						$last_index = $first_index + $end;
						return [
							$first_index,
							$last_index
						];
					}
				}
			}
			
			// Default return if no matches.
			return [
				$index_number,
				$index_number
			];

		}
		/**
		 * Get Single order information
		 *
		 * @param mixed $order_id order id.
		 *
		 * @return mixed
		 */
		public function get_single_order( $order_id ) {
			$order_data_storage_option = get_option( 'woocommerce_custom_orders_table_enabled' );
			if ( 'yes' === $order_data_storage_option ) {
				$data = $this->get_all_hpos_orders($order_id, true);
			} else {
				$data = $this->get_all_orders($order_id, true);
			}

			$data = $this->duplicate_based_on_product_names($data);

			return $data;
		}
		/**
		 * Update delete and append order in google sheets check by id
		 *
		 * @param mixed  $order_id product id.
		 * @param string $type update type.
		 * @param string $range sheet index.
		 * @param array  $sheets sheets data.
		 *
		 * @return boolean
		 */
		public function batch_update_delete_and_append( $order_id, $type = 'update', $start_range = null, $sheets = [], $end_range = null) {
			if ( ! $this->app->is_plugin_ready() ) {
				return __('Plugin is not ready to use.', 'stock-sync-with-google-sheet-for-woocommerce');
			}

			$sheet = new Sheet();
			$values = $this->get_single_order($order_id);
			$values_count = is_array($values) ? count($values) : 0;
			if ( $start_range !== null ) { //phpcs:ignore
				if ( ! empty($values) ) {
					$sheet->update_single_row_values($start_range, $values, null, $end_range );
				}
			} else {
				if ( 'append' === $type ) {
					if ( ! empty($values) ) {
						$sheet->append_new_row($values);
					}
				} else {
					if ( 'update' === $type ) {
						$range = $this->find_out_range($order_id, $sheets);
						if ( $range !== null ) { //phpcs:ignore
							if($values_count > 1 ) {
								$end_range = $range + $values_count;
								$max = $end_range - 1;
							} else {
								$max = $range;
							}
							$sheet->update_single_row_values($range, $values, null , $max);
						} else {
							$sheet->append_new_row($values);
						}
					} else if ( 'trash' === $type ) {
						$range = $this->find_out_range($order_id, $sheets);
						if ( $range !== null ) { //phpcs:ignore
							if($values_count > 1 ) {
								$end_range = $range + $values_count;
								$max = $end_range - 1;
							} else {
								$max = $range;
							}
							$sheet->delete_single_row($range, $max);
						}
					} else if ( 'untrash' === $type ) {
						$sheet->append_new_row($values, 'untrash');
					}
				}
			}
			return true;
		}
		/**
		 * Update Hpos order status
		 *
		 * @param string $order_status order status.
		 * @param int    $order_id order id.
		 *
		 * @return mixed
		 */
		public function update_hpos_order_status( $order_status = '', $order_id = '' ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'wc_orders';
			$update_status = $wpdb->update(
				$table_name,
				[ 'status' => $order_status ],
				[ 'id' => $order_id ],
			);
			if ( $update_status ) {
				return true;
			} else {
				return false;
			}
		}
		/**
		 * Update post table order status.
		 *
		 * @param string $order_status order status.
		 * @param int    $order_id order id.
		 *
		 * @return mixed
		 */
		public function update_post_table_order_status( $order_status = '', $order_id = '' ) {
			$order_data = array(
				'ID'          => $order_id,
				'post_status' => $order_status,
			);
			$update_post = wp_update_post( $order_data );
			if ( $update_post ) {
				return true;
			} else {
				return false;
			}
		}
	}
}
