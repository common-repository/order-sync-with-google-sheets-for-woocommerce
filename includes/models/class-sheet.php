<?php
/**
 * Class Sheet
 * includes/model/Sheet.php
 *
 * @package OrderSyncWithGoogleSheetForWooCommerce
 */

namespace OrderSyncWithGoogleSheetForWooCommerce;

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

if ( ! class_exists( '\OrderSyncWithGoogleSheetForWooCommerce\Sheet' ) ) {
	/**
	 * Class Sheet
	 */
	class Sheet extends Base {
		/**
		 * Sheet credentials
		 *
		 * @var null
		 */
		protected $credentials = null;
		/**
		 * Sheet ID
		 *
		 * @var null
		 */
		protected $sheet_id = null;
		/**
		 * Spreadsheet ID
		 *
		 * @var string
		 */
		protected $spreadsheet_id;
		/**
		 * Sheet Sheet Tab
		 *
		 * @var string
		 */
		protected $sheet_tab = null;
		/**
		 * Constructor.
		 *
		 * @param string $sheet_id Spreadsheet ID.
		 * @param string $sheet_tab Spreadsheet Tab.
		 * @throws \Exception If plugin is not ready to use.
		 */
		public function __construct( $sheet_id = null, $sheet_tab = null ) {
			/**
			 * Check if plugin is ready to use
			 */

			if ( osgsw()->is_plugin_ready() === false ) {
				throw new \Exception( 'Plugin is not ready to use.' );
				return false;
			}
			/**
			 * Default credentials
			 */
			$this->credentials = osgsw_get_option( 'credentials' );
			/**
			 * The Spreadsheet
			 */
			$this->spreadsheet_id = $sheet_id ?? osgsw_get_option( 'spreadsheet_id' );
			/**
			 * Single Sheet
			 */
			$this->sheet_tab = $sheet_tab ?? osgsw_get_option( 'sheet_tab' );
			$this->sheet_id  = osgsw_get_option( 'sheet_id', '0' );
		}

		/**
		 * Set Sheet ID.
		 *
		 * @param string $spreadsheet_id Spreadsheet ID.
		 * @return $this
		 */
		public function setID( $spreadsheet_id = null ) {
			if ( $spreadsheet_id ) {
				$this->spreadsheet_id = $spreadsheet_id;
			}
			return $this;
		}
		/**
		 * Set Sheet Tab Name.
		 *
		 * @param string $sheet_tab Spreadsheet Tab.
		 * @return $this
		 */
		public function setTab( $sheet_tab = null ) {
			if ( $sheet_tab ) {
				$this->sheet_tab = $sheet_tab;
			}
			return $this;
		}
		/**
		 * Generate access token for google sheet access.
		 *
		 * @return mixed
		 */
		protected function generate_access_token() {
			try {
				$credentials = $this->credentials;
				if ( ! is_array( $credentials ) ) {
					return false;
				}
				if ( ! array_key_exists( 'private_key', $credentials ) ) {
					return false;
				}
				$client_email = $credentials['client_email'];
				$private_key = $credentials['private_key'];
				$now = time();
				$exp = $now + 3600;
				$payload = json_encode(
					[
						'iss' => $client_email,
						'aud' => 'https://oauth2.googleapis.com/token',
						'iat' => $now,
						'exp' => $exp,
						'scope' => 'https://www.googleapis.com/auth/spreadsheets',
					]
				);

				$header = json_encode(
					[
						'alg' => 'RS256',
						'typ' => 'JWT',
					]
				);

				$base64_url_header = str_replace( [ '+', '/', '=' ], [ '-', '_', '' ], base64_encode( $header ) );
				$base64_url_payload = str_replace( [ '+', '/', '=' ], [ '-', '_', '' ], base64_encode( $payload ) );

				$signature = '';
				openssl_sign( $base64_url_header . '.' . $base64_url_payload, $signature, $private_key, 'SHA256' );
				$base64_url_signature = str_replace( [ '+', '/', '=' ], [ '-', '_', '' ], base64_encode( $signature ) );

				$jwt = $base64_url_header . '.' . $base64_url_payload . '.' . $base64_url_signature;

				$token_url = 'https://oauth2.googleapis.com/token';
				$body = [
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion' => $jwt,
				];

				$response = wp_remote_post(
					$token_url,
					[
						'body' => $body,
					]
				);

				$response_body = wp_remote_retrieve_body( $response );
				$token_data = json_decode( $response_body, true );
				if ( is_array($token_data) ) {
					if ( array_key_exists( 'access_token', $token_data ) ) {
						$access_token = $token_data['access_token'];
						return $access_token;
					} else {
						return false;
					}
				} else {
					return false;
				}
			} catch ( \Exception $e ) {
				return false;
			}
		}
		/**
		 * Generate token every 55minute
		 *
		 * @return string
		 */
		public function get_token() {
			$new_token = $this->generate_access_token();
			if ($new_token) {
				return $new_token;
			} else {
				return $this->generate_access_token();
			}
		}
		/**
		 * Get first column's value from Google Sheet using wp_remote_request.
		 *
		 * @return array|false An array of values or false if there's an error.
		 */
		public function get_first_columns() {
			$url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheet_id . '/values/' . urlencode($this->sheet_tab . '!A:A');
			$args = [
				'method' => 'GET',
				'headers' => [
					'Authorization' => 'Bearer ' . $this->get_token(),
				],
				'timeout' => 300,
			];
			$response = wp_remote_request($url, $args);
			if ( is_wp_error( $response ) ) {
				return [];
			}
			$response_body = wp_remote_retrieve_body($response);
			$response_data = json_decode($response_body, true);
			if ( isset( $response_data['values'] ) ) {
				return $response_data['values'];
			}
			return [];
		}
		/**
		 * Get values from google sheet by range.
		 *
		 * @param string $range Range.
		 * @param string $dimension Dimension.
		 * @param string $sheet_tab Sheet Tab.
		 * @return array|bool
		 */
		public function get_values( $range = null, $dimension = 'ROWS', $sheet_tab = null ) {
			if ( ! $range ) {
				return false;
			}

			if ( ! $sheet_tab ) {
				$sheet_tab = $this->sheet_tab;
			} else {
				$this->sheet_tab = $sheet_tab;
			}

			$url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheet_id . '/values/' . urlencode( $sheet_tab . '!' . $range );
			$args = [
				'method' => 'GET',
				'headers' => [
					'Authorization' => 'Bearer ' . $this->get_token(),
				],
				'timeout' => 300,
			];

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				return [];
			}
			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body, true );
			if ( isset( $response_data['values'] ) ) {
				return $response_data['values'];
			}
			return [];
		}
		/**
		 * Get rows from google sheet by range.
		 *
		 * @param string $range Range.
		 * @param string $sheet_tab Sheet Tab.
		 */
		public function get_rows( $range = null, $sheet_tab = null ) {
			if ( ! $range ) {
				return false;
			}
			return $this->get_values( $range, 'ROWS', $sheet_tab );
		}
		/**
		 * Get columns from google sheet by range.
		 *
		 * @param string $range Range.
		 * @param string $sheet_tab Sheet Tab.
		 * @return array|bool
		 */
		public function get_columns( $range = null, $sheet_tab = null ) {
			if ( ! $range ) {
				return false;
			}
			return $this->get_values( $range, 'COLUMNS', $sheet_tab );
		}
		/**
		 * Updates values in google sheet by range.
		 *
		 * @param string $range Range.
		 * @param array  $values Values.
		 * @param string $dimension Dimension.
		 * @return mixed
		 */
		public function update_values( $range = null, $values = null, $dimension = null, $title = false ) {
			if ( ! $range || ! $values ) {
				return false;
			}
			try {
				$access_token = $this->get_token();
				$this->reset_sheet2( $access_token, $title );
				$spreadsheet_id     = $this->spreadsheet_id;
				$sheet_name = $this->sheet_tab;
				$api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$sheet_name}!A1:append?valueInputOption=USER_ENTERED";
		
				$data = [
					'values' => $values,
				];
				$request_data = [
					'majorDimension' => 'ROWS',
					'values' => $data['values'],
				];
				$headers = [
					'Authorization' => "Bearer {$access_token}",
					'Content-Type' => 'application/json',
				];
				$response = wp_remote_post(
					$api_url,
					[
						'headers' => $headers,
						'body' => json_encode( $request_data ),
						'timeout' => 300,
					]
				);
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				
				if ( isset( $response_data['updates']['updatedRows'] ) ) {
					   return true;
				} else {
					return false;
				}
			} catch ( \Throwable $error ) {
				return false;
			}
		}

		/**
		 * Updates values in Google Sheet by range using wp_remote_post.
		 *
		 * @param string $row_number Row number.
		 * @param array  $values Values.
		 * @param string $dimension Dimension.
		 * @return bool True if the update was successful, false otherwise.
		 */
		public function update_single_row_values( $row_number = null, $values = null, $dimension = null, $end_number = null) {
			if ( ! $row_number || ! $values || !$end_number ) {
				return false;
			}
			$url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheet_id . '/values/' . urlencode($this->sheet_tab . '!' . $row_number . ':' . $end_number) . '?valueInputOption=USER_ENTERED';

			$args = array(
				'method' => 'PUT',
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->get_token(),
					'Content-Type' => 'application/json',
				),
				'body' => json_encode(array(
					'values' => $values,
				)),
				'timeout' => 300,
			);

			$response = wp_remote_request($url, $args);

			if ( is_wp_error($response) ) {
				return false;
			}

			$response_body = wp_remote_retrieve_body($response);
			
			$response_data = json_decode($response_body, true);
			if ( isset($response_data['updates']['updatedRows']) && $response_data['updates']['updatedRows'] > 0 ) {
				return true;
			} else {
				return false;
			}
		}
		/**
		 * Insert values in google sheet by range.
		 *
		 * @param string $range Range.
		 * @param array  $data Values.
		 * @param string $dimension Dimension.
		 * @return mixed
		 */
		public function insert_new_value( $range = null, $data = null, $dimension = null ) {
			if ( ! $data ) {
				return false;
			}
			try {
				$access_token = $this->get_token();
				$spreadsheet_id     = $this->spreadsheet_id;
				$sheet_name = $this->sheet_tab;
				$api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$sheet_name}:append?valueInputOption=USER_ENTERED";
				$data = [
					'values' => [ $data ],
				];
				$request_data = [
					'majorDimension' => 'ROWS',
					'values' => $data['values'],
				];
				$headers = [
					'Authorization' => "Bearer {$access_token}",
					'Content-Type' => 'application/json',
				];
				$response = wp_remote_post(
					$api_url,
					[
						'headers' => $headers,
						'body' => json_encode( $request_data ),
						'timeout' => 300,
					]
				);
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				if ( isset( $response_data['updates']['updatedRows'] ) ) {
					return true;
				} else {
					return false;
				}
			} catch ( \Throwable $error ) {
				return false;
			}
		}

		/**
		 * Append new row to Google Sheets using wp_remote_post.
		 *
		 * @param array  $data Data to append as a new row.
		 * @param string $type Type of append (e.g., 'test' or 'deleted_product').
		 * @return bool True if successful, false on failure.
		 */
		public function append_new_row( $data, $type = 'simple' ) {
			if ( ! $data ) {
				return false;
			}
			try {
				$access_token = $this->get_token();
				$spreadsheet_id     = $this->spreadsheet_id;
				$sheet_name = $this->sheet_tab;
				$api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$sheet_name}:append?valueInputOption=USER_ENTERED";
				$data = array(
					'values' => $data,
				);
				$request_data = array(
					'majorDimension' => 'ROWS',
					'values' => $data['values'],
				);
				$headers = array(
					'Authorization' => "Bearer {$access_token}",
					'Content-Type' => 'application/json',
				);
				$response = wp_remote_post(
					$api_url, array(
						'headers' => $headers,
						'body' => json_encode($request_data),
						'timeout' => 300,
					)
				);
				$response_body = wp_remote_retrieve_body($response);
				$response_data = json_decode($response_body, true);
				if ( isset($response_data['updates']['updatedRows']) ) {
					if ( 'untrash' === $type ) {
						$this->sort_google_sheet_data_wp_remote($spreadsheet_id, $access_token );
					}
					return true;
				} else {
					return false;
				}
			} catch ( \Throwable $error ) {
				return false;
			}
		}
		/**
		 * Sort Google Sheet data based on the first column using wp_remote_post.
		 *
		 * @param string $spreadsheet_id The ID of the Google Spreadsheet.
		 * @param string $access_token The access token for authorization.
		 *
		 * @return bool True if successful, false on failure.
		 */
		public function sort_google_sheet_data_wp_remote( $spreadsheet_id, $access_token ) {
			try {
				$sort_range = array(
					'sheetId' => $this->sheet_id,
					'startRowIndex' => 1,
					'endRowIndex' => 0,
					'startColumnIndex' => 0,
					'endColumnIndex' => null,
				);

				$sort_spec = array(
					'dimensionIndex' => 0,
					'sortOrder' => 'ASCENDING',
				);

				$sort_range_request = array(
					'sortRange' => array(
						'range' => $sort_range,
						'sortSpecs' => array( $sort_spec ),
					),
				);

				$batch_update_request = array(
					'requests' => array( $sort_range_request ),
				);
				$sort_range['endRowIndex'] = null;
				$sort_range_request['sortRange']['range'] = $sort_range;
				$batch_update_request['requests'] = array( $sort_range_request );
				// Build the URL for the batch update.
				$url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $spreadsheet_id . ':batchUpdate';

				// Prepare the request arguments.
				$headers = array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type' => 'application/json',
				);

				$body = json_encode($batch_update_request);

				$args = array(
					'body' => $body,
					'headers' => $headers,
					'method' => 'POST',
					'timeout' => 300,
				);
				$response = wp_remote_request($url, $args);
				if ( is_wp_error($response) ) {
					return false;
				}
				$response_code = wp_remote_retrieve_response_code($response);
				if ( 200 === $response_code ) {
					return true;
				} else {
					return false;
				}
			} catch ( \Exception $e ) {
				return false;
			}
		}
		/**
		 * Updates rows in google sheet by range.
		 *
		 * @param string $range Range.
		 * @param array  $values Values.
		 * @return mixed
		 */
		public function update_row_values( $range = null, $values = null, $title = false ) {
			if ( ! $range || ! $values ) {
				return false;
			}
			return $this->update_values( $range, $values, 'ROWS', $title );
		}
		/**
		 * Updates columns in google sheet by range.
		 *
		 * @param string $range Range.
		 * @param array  $values Values.
		 * @return mixed
		 */
		public function update_row_columns( $range = null, $values = null ) {
			if ( ! $range || ! $values ) {
				return false;
			}
			return $this->update_values( $range, $values, 'COLUMNS' );
		}
		/**
		 * Initializes the Google Sheets API service.
		 *
		 * @throws \Exception If the API client library is not found.
		 * @return mixed
		 */
		public function initialize() {
			try {
				$sheets = $this->get_sheet_tab();
				if (empty($sheets)) {
					$sheets = $this->get_sheet_tab();
				}
				$sheet = array_filter(
					$sheets,
					function ( $sheet ) {
						return $sheet['properties']['title'] === $this->sheet_tab;
					}
				);
				/**
				 * Getting Sheet ID of working sheet
				 */
				if ( ! $sheet ) {
					   // if no sheet title matched, create new one with the title of the sheet.
					   $response = $this->create_sheet_tab( $this->sheet_tab );
					   
					   $sheet = isset($response['replies'][0]['addSheet']) ? $response['replies'][0]['addSheet'] : [];
				} else {
					$sheet = array_values( $sheet )[0];
				}
				/**
				 * Save working Sheet ID to database for later use.
				 */
				$sheet_id = isset( $sheet['properties']['sheetId']) ? $sheet['properties']['sheetId'] : 0;
				$sheet_title = isset( $sheet['properties']['title']) ? $sheet['properties']['title'] : 'Sheet1';
				osgsw_update_option( 'sheet_id', $sheet_id );
				$updated = $this->sync_sheet_headers($sheet_title);
				$dropdown_values = $this->update_google_sheet_dropdowns($sheet_id);
				if ( ! $dropdown_values ) {
					$this->update_google_sheet_dropdowns($sheet_id);
				}
				return $updated;
			} catch ( \Exception $e ) {
				throw new \Exception( esc_html__( 'Unable to access Google Sheet. Please check required permissions.', 'stock-sync-with-google-sheet-for-woocommerce' ) );
			}
		}
		/**
		 * Creates a new sheet tab.
		 *
		 * @param mixed $sheet_name Sheet Name.
		 */
		public function create_sheet_tab( $sheet_name = null ) {
			if ( ! $sheet_name ) {
				$sheet_name = $this->sheet_tab;
			}
			try {
				$access_token = $this->get_token();
				$spreadsheet_id     = $this->spreadsheet_id;
				$api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}:batchUpdate";
				$headers = [
					'Authorization' => "Bearer {$access_token}",
					'Content-Type' => 'application/json',
				];
				$request_body = json_encode(
					[
						'requests' => [
							[
								'addSheet' => [
									'properties' => [
										'title' => $sheet_name,
									],
								],
							],
						],
					]
				);
				$response = wp_remote_post(
					$api_url,
					[
						'headers' => $headers,
						'body' => $request_body,
						'timeout' => 300,

					]
				);
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				return $response_data;
			} catch ( \Exception $e ) {
				return false;
			}
		}
		/**
		 * Delete single row value using wp_remote_post.
		 *
		 * @param int $row_number Row number to delete (starting from 1).
		 * @return bool
		 */
		public function delete_single_row( $row_number = 2, $max = 2 ) {
			if ( ! $row_number || ! $max  ) {
				return false;
			}

			$url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheet_id . ':batchUpdate';

			$request = array(
				'deleteDimension' => array(
					'range' => array(
						'sheetId' => $this->sheet_id,
						'dimension' => 'ROWS',
						'startIndex' => $row_number - 1,
						'endIndex' => $max,
					),
				),
			);

			$args = array(
				'method' => 'POST',
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->get_token(),
					'Content-Type' => 'application/json',
				),
				'body' => json_encode(array(
					'requests' => array( $request ),
				)),
				'timeout' => 300,
			);

			$response = wp_remote_request($url, $args);

			if ( is_wp_error($response) ) {
				return false;
			}

			$response_code = wp_remote_retrieve_response_code($response);

			if ( 200 === $response_code ) {
				return true;
			} else {
				return false;
			}
		}
		/**
		 * Syncs sheet headers
		 *
		 * @return mixed
		 */
		public function sync_sheet_headers($title = false) {
			try {
				$column   = new Column();
				$keys     = $column->get_column_names();
				$response = $this->update_row_values( 'A1', [ $keys ], $title );
				return $response;
			} catch ( \Exception $e ) {
				return false;
			}
		}
		/**
		 * Reset sheet
		 *
		 * @param mixed $access_token Access Token.
		 */
		public function reset_sheet( $access_token ) {
			try {
				$spreadsheet_id = $this->spreadsheet_id;
				$sheet_name     = $this->sheet_tab;
				$api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$sheet_name}:clear";
				if ( empty( $access_token ) ) {
					$access_token = $this->get_token();
				}
				$headers = [
					'Authorization' => "Bearer {$access_token}",
				];
				$response = wp_remote_post(
					$api_url,
					[
						'headers' => $headers,
					]
				);
				$response_code = wp_remote_retrieve_body( $response );
				if ( 204 === $response_code ) {
					   return true;
				} else {
					return false;
				}
			} catch ( \Exception $e ) {
				return $e;
			}
		}
		/**
		 * Reset sheet
		 *
		 * @param mixed $access_token Access Token.
		 */
		public function reset_sheet2( $access_token, $title = false ) {
			try {
				$spreadsheet_id = $this->spreadsheet_id;
				if ($title) {
					$sheet_name     = $title;
				} else {
					$sheet_name = $this->sheet_tab;
				}

				$api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$sheet_name}:clear";
				if ( empty( $access_token ) ) {
					$access_token = $this->get_token();
				}
				$headers = [
					'Authorization' => "Bearer {$access_token}",
				];
				$response = wp_remote_post(
					$api_url,
					[
						'headers' => $headers,
					]
				);
				$response_code = wp_remote_retrieve_body( $response );
				if ( 204 === $response_code ) {
					   return true;
				} else {
					return false;
				}
			} catch ( \Exception $e ) {
				return $e;
			}
		}
		/**
		 * Freezes headers.
		 *
		 * @param boolean $freeze Freeze.
		 * @return mixed
		 */
		public function freeze_headers( $freeze = true ) {
			try {
				$frozen_row_count = $freeze ? 1 : 0;
				$frozen_column_count = $freeze ? 1 : 0;
				// Build the batch update request to freeze/unfreeze headers.
				$batch_update_request = [
					'requests' => [
						[
							'updateSheetProperties' => [
								'properties' => [
									'sheetId' => $this->sheet_id,
									'gridProperties' => [
										'frozenRowCount' => $frozen_row_count,
										'frozenColumnCount' => $frozen_column_count,
									],
								],
								'fields' => 'gridProperties.frozenRowCount,gridProperties.frozenColumnCount',
							],
						],
					],
				];

				// Build the URL.
				$url = 'https://sheets.googleapis.com/v4/spreadsheets/' . $this->spreadsheet_id . ':batchUpdate';

				// Prepare the request arguments.
				$args = [
					'method' => 'POST',
					'headers' => [
						'Authorization' => 'Bearer ' . $this->get_token(),
						'Content-Type' => 'application/json',
					],
					'body' => json_encode( $batch_update_request ),
					'timeout' => 300,
				];

				// Send the POST request.
				$response = wp_remote_post( $url, $args );

				if ( is_wp_error( $response ) ) {
					return false;
				}

				$response_code = wp_remote_retrieve_response_code( $response );

				if ( 200 === $response_code ) {
					return true;
				} else {
					return false;
				}
			} catch ( \Exception $e ) {
				return false;
			}
		}
		/**
		 * Get sheet all tab
		 *
		 * @return array
		 */
		public function get_sheet_tab() {
			$access_token = $this->get_token();
			$spreadsheet_id = $this->spreadsheet_id;
			$api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}?access_token={$access_token}";
			$headers = [
				'Authorization' => "Bearer {$access_token}",
			];
			$response = wp_remote_get( $api_url, [ 'headers' => $headers ] );

			if ( is_wp_error( $response ) ) {
				return [];
			} else {
				$response_body = wp_remote_retrieve_body( $response );
				$data = json_decode( $response_body, true );
				if ( isset( $data['sheets'] ) ) {
					return $data['sheets'];
				} 
				return [];
			}
		}
		/**
		 * Update values in Google Sheet column C2:C with dropdown format.
		 *
		 * @param array  $dropdown_values Array of values to populate as dropdown options.
		 * @param string $last_row     The last number of the row.
		 * @return bool True if the update was successful, false otherwise.
		 */
		public function update_google_sheet_dropdowns($sheet_id = false) {
			$accessToken = $this->get_token(); 
			$spreadsheetId = $this->spreadsheet_id;
			$sheetId = $sheet_id;
			if ( !$sheetId ) {
				$sheetId = $this->sheet_id;
			}
			$dropdownOptions = ossgsw_get_order_statuses();
			
			$dataValidationRule = [
				'setDataValidation' => [
					'range' => [
						'sheetId' => $sheetId,
						'startRowIndex' => 1,
						'startColumnIndex' => 2,
						'endColumnIndex' => 3,
					],
					'rule' => [
						'condition' => [
							'type' => 'ONE_OF_LIST',
							'values' => array_map(function($option) {
								return ['userEnteredValue' => $option];
							}, $dropdownOptions)
						],
						'showCustomUi' => true,
						'strict' => true,
					],
				],
			];
		
			// Prepare the batchUpdate request body
			$body = json_encode(['requests' => [$dataValidationRule]]);
		
			// Make the API call
			$response = wp_remote_post(
				"https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId:batchUpdate",
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $accessToken,
						'Content-Type' => 'application/json',
					],
					'body' => $body,
				]
			);
			
		
			if (is_wp_error($response)) {
				return false;
			} else {
				return true;
			}
		}
		
	}
}
