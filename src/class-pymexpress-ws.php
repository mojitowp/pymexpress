<?php
/**
 * Correos de Costa Rica Webservice client
 *
 * @link       https://github.com/nomanualdev/correos-de-costa-rica-pymexpress-ws-client
 * @since      1.0.0
 * @package    CCR/Pymexpress
 * @subpackage CCR/Pymexpress/ws
 * @author     Mojito Team <support@mojitowp.com>
 */

namespace Pymexpress;

/**
 * Web Service connector class
 * Updated to 2022
 */
class Pymexpress_WSC {

	/**
	 * Constructor for webservice client
	 */
	public function __construct( string $username, string $password, string $user_id, string $service_id, string $client_code, string $environment = 'sandbox' )
	{

		/**
		 * Set debug = true to log errors.
		 * $pymespress->debug = true;
		 */
		$this->debug = false;

		$this->credentials = array(
			'Username'    => $username,
			'Password'    => $password,
			'User_id'     => $user_id,
			'Service_id'  => $service_id,
			'Client_code' => $client_code,
		);

		$this->system = 'PYMEXPRESS';

		if ( 'sandbox' === $environment ) {
			$this->environment['auth_port']    = 442;
			$this->environment['auth_url']     = 'https://servicios.correos.go.cr:442/Token/authenticate';
			$this->environment['process_url']  = 'http://amistad.correos.go.cr:84/wsAppCorreos.wsAppCorreos.svc?WSDL';
			$this->environment['process_port'] = 84;

		} elseif ( 'production' === $environment ) {
			$this->environment['auth_port']    = 447;
			$this->environment['auth_url']     = 'https://servicios.correos.go.cr:447/Token/authenticate';
			$this->environment['process_url']  = 'https://amistadpro.correos.go.cr:444/wsAppCorreos.wsAppCorreos.svc?WSDL';
			$this->environment['process_port'] = 444;
		}

		$this->methods = array(
			'ccrCodProvincia',
			'ccrCodCanton',
			'ccrCodDistrito',
			'ccrCodBarrio',
			'ccrCodPostal',
			'ccrTarifa',
			'ccrGenerarGuia',
			'ccrRegistroEnvio',
			'ccrMovilTracking',
		);

		if ( session_status() === PHP_SESSION_NONE ) {
			session_start();
		}
	}

	private function log( $message )
	{
		if ( $this?->debug === true ) {
			error_log( print_r( $message, true ) );
		}
		return print_r( $message, true );
	}

	/**
	 * Authentication method
	 */
	private function auth(): string|bool 
	{

		if ( empty( $this->credentials['Username'] ) || empty( $this->credentials['Password'] ) ) {
			return false;
		}

		if ( empty( $this->environment['auth_port'] ) || empty( $this->environment['auth_url'] ) ) {
			return false;
		}

		$body = array(
			'Username' => $this->credentials['Username'],
			'Password' => $this->credentials['Password'],
			'Sistema'  => $this->system,
		);

		$curl = curl_init();

		$parameters = array(
			CURLOPT_PORT           => $this->environment['auth_port'],
			CURLOPT_URL            => $this->environment['auth_url'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode( $body ),
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
			),
			CURLOPT_CONNECTTIMEOUT => 10,
		);
		$parameters = $this->set_proxy_settings( $parameters );

		curl_setopt_array( $curl, $parameters );

		$response = curl_exec( $curl );
		$this->log( $response );
		$err      = curl_error( $curl );

		curl_close( $curl );

		if ( $err ) {
			$this->log( $err );
			return false;

		} else {

			$this->token           = $response;
			$this->token_timestamp = time();

			$_SESSION['ccr_token'] = array(
				'token' => $this->token,
				'time'  => $this->token_timestamp,
			);

			return $this->token;
		}
	}

	/**
	 * Get token
	 */
	public function get_token(): string
	{

		// Max token lifetime is 5 min. Due the connection timeout is 5 - 30 seconds we calculate 4 min 30 s.
		if ( empty ( $_SESSION['ccr_token']['time'] ) ) {
			return $this->auth();
		}

		$current_token_time = $_SESSION['ccr_token']['time'];

		if ( ( time() - $current_token_time ) < 270 ) {
			return $_SESSION['ccr_token']['token'];
		} else {
			return $this->auth();
		}
	}


	/**
	 * Get provincias from CCR WS
	 */
	public function get_provincias(): array 
	{

		$provincias = array();
		$response   = $this->request( 'ccrCodProvincia' );

		foreach ( $response?->aProvincias?->accrItemGeografico as $key => $obj ) {
			$data                  = (array) $obj;
			$codigo                = (string) $data['aCodigo'];
			$descripcion           = $data['aDescripcion'];
			$provincias[ $codigo ] = $descripcion;
		}
		return $provincias;
	}


	/**
	 * Get cantones from a Provincia
	 */
	public function get_cantones( string $province_code ): array 
	{

		$cantones     = array();
		$replacements = array(
			'%CodProvincia%' => $province_code,
		);
		$data_types = array(
			'%CodProvincia%' => array(
				'type'   => 'string',
				'length' => 1,
			),
		);

		if ( $this->check_parameters( $replacements, $data_types, __FUNCTION__ ) ) {

			$response = $this->request( 'ccrCodCanton', $replacements );

			foreach ( $response?->aCantones?->accrItemGeografico as $key => $obj ) {
				$data                = (array) $obj;
				$codigo              = (string) $data['aCodigo'];
				$descripcion         = $data['aDescripcion'];
				$cantones[ $codigo ] = $descripcion;
			}
		}
		return $cantones;
	}


	/**
	 * Get distritos from a Provincia and Canton
	 */
	public function get_distritos( string $province_code, string $canton_code ): array 
	{

		$distritos    = array();
		$replacements = array(
			'%CodProvincia%' => $province_code,
			'%CodCanton%'    => $canton_code,
		);
		$data_types = array(
			'%CodProvincia%' => array(
				'type'   => 'string',
				'length' => 1,
			),
			'%CodCanton%'    => array(
				'type'   => 'string',
				'length' => 2,
			),
		);

		if ( $this->check_parameters( $replacements, $data_types, __FUNCTION__ ) ) {

			$response = $this->request( 'ccrCodDistrito', $replacements );

			foreach ( $response?->aDistritos?->accrItemGeografico as $key => $obj ) {
				$data                 = (array) $obj;
				$codigo               = (string) $data['aCodigo'];
				$descripcion          = $data['aDescripcion'];
				$distritos[ $codigo ] = $descripcion;
			}
		}

		return $distritos;
	}


	/**
	 * Get barrios from a Provincia, Canton and Distrito
	 */
	public function get_barrios( string $province_code, string $canton_code, string $district_code ): array 
	{

		$barrios      = array();
		$replacements = array(
			'%CodProvincia%' => $province_code,
			'%CodCanton%'    => $canton_code,
			'%CodDistrito%'  => $district_code,
		);
		$data_types   = array(
			'%CodProvincia%' => array(
				'type'   => 'string',
				'length' => 1,
			),
			'%CodCanton%'    => array(
				'type'   => 'string',
				'length' => 2,
			),
			'%CodDistrito%'  => array(
				'type'   => 'string',
				'length' => 2,
			),
		);

		if ( $this->check_parameters( $replacements, $data_types, __FUNCTION__ ) ) {

			$response = $this->request( 'ccrCodBarrio', $replacements );

			foreach ( $response?->aBarrios?->accrBarrio as $key => $obj ) {
				$data      = (array) $obj;
				$codigo    = (string) $data['aCodBarrio'];
				$sucursal  = (string) $data['aCodSucursal'];
				$nombre    = $data['aNombre'];
				$barrios[] = array(
					'codigo'   => $codigo,
					'nombre'   => $nombre,
					'sucursal' => $sucursal,
				);
			}
		}

		return $barrios;
	}


	/**
	 * Get Zip code from a Provincia, Canton and Distrito
	 */
	public function get_codigo_postal( string $province_code, string $canton_code, string $district_code ): string 
	{

		$zip = '';

		$replacements = array(
			'%CodProvincia%' => $province_code,
			'%CodCanton%'    => $canton_code,
			'%CodDistrito%'  => $district_code,
		);
		$data_types   = array(
			'%CodProvincia%' => array(
				'type'   => 'string',
				'length' => 1,
			),
			'%CodCanton%'    => array(
				'type'   => 'string',
				'length' => 2,
			),
			'%CodDistrito%'  => array(
				'type'   => 'string',
				'length' => 2,
			),
		);

		if ( $this->check_parameters( $replacements, $data_types, __FUNCTION__ ) ) {
			$response = $this->request( 'ccrCodPostal', $replacements );
			$data     = (array) $response->aCodPostal;
			$zip      = $data[0];
		}
		return $zip;
	}


	/**
	 * Get Tarifa
	 */
	public function get_tarifa( string $provincia_origen, string $canton_origen, string $provincia_destino, string $canton_destino, int $peso ): array 
	{
		if ( $peso < 1000 ) {
			$peso = 1000;
		}
		$rate         = array();
		$replacements = array(
			'%ProvinciaOrigen%'  => $provincia_origen,
			'%CantonOrigen%'     => $canton_origen,
			'%ProvinciaDestino%' => $provincia_destino,
			'%CantonDestino%'    => $canton_destino,
			'%Servicio%'         => $this->credentials['Service_id'],
			'%Peso%'             => $peso,
		);
		$data_types   = array(
			'%ProvinciaOrigen%'  => array(
				'type'   => 'string',
				'length' => 1,
			),
			'%CantonOrigen%'     => array(
				'type'   => 'string',
				'length' => 2,
			),
			'%ProvinciaDestino%' => array(
				'type'   => 'string',
				'length' => 1,
			),
			'%CantonDestino%'    => array(
				'type'   => 'string',
				'length' => 2,
			),
			'%Servicio%'         => array(
				'type'   => 'string',
				'length' => 5,
			),
			'%Peso%'             => array(
				'type' => 'numeric',
			),
		);

		if ( $this->check_parameters( $replacements, $data_types, __FUNCTION__ ) ) {

			$response = $this->request( 'ccrTarifa', $replacements );
			$data     = (array) $response;

			if ( ! empty( $data['aMontoTarifa'] ) ) {
				$rate['tarifa'] = $data['aMontoTarifa'];
			}

			if ( ! empty( $data['aDescuento'] ) ) {
				$rate['descuento'] = $data['aDescuento'];
			}

			if ( ! empty( $data['aImpuesto'] ) ) {
				$rate['impuesto'] = $data['aImpuesto'];
			}

		}
		return $rate;
	}


	/**
	 * Generar Guía
	 *
	 * @return string
	 */
	public function generar_guia(): string 
	{
		$response = $this->request( 'ccrGenerarGuia' );
		$data     = (array) $response;
		$this->log( $data );
		$guide    = $data['aNumeroEnvio'] ?? '';
		$this->log( $guide );
		return $guide;
	}


	/**
	 * Registrar envío.
	 */
	public function registro_envio( string $order_id, array $params, array $sender ): array 
	{

		$response     = array(
			'status'   => '',
			'response' => array(),
			'log'    => '',
		);
		$replacements = array(
			'%Cliente%'        => $this->credentials['Client_code'],
			'%COD_CLIENTE%'    => $this->credentials['Client_code'],
			'%DEST_APARTADO%'  => ( ! empty( $params['DEST_APARTADO'] ) ) ? $params['DEST_APARTADO'] : '',
			'%DEST_DIRECCION%' => ( ! empty( $params['DEST_DIRECCION'] ) ) ? $params['DEST_DIRECCION'] : '',
			'%DEST_NOMBRE%'    => ( ! empty( $params['DEST_NOMBRE'] ) ) ? $params['DEST_NOMBRE'] : '',
			'%DEST_TELEFONO%'  => ( ! empty( $params['DEST_TELEFONO'] ) ) ? $params['DEST_TELEFONO'] : '',
			'%DEST_ZIP%'       => ( ! empty( $params['DEST_ZIP'] ) ) ? $params['DEST_ZIP'] : '',
			'%ENVIO_ID%'       => ( ! empty( $params['ENVIO_ID'] ) ) ? $params['ENVIO_ID'] : '',
			'%FECHA_ENVIO%'    => gmdate( 'Y-m-d\TH:i:s' ),
			'%MONTO_FLETE%'    => ( ! empty( $params['MONTO_FLETE'] ) ) ? $params['MONTO_FLETE'] : '',
			'%OBSERVACIONES%'  => ( ! empty( $params['OBSERVACIONES'] ) ) ? $params['OBSERVACIONES'] : '',
			'%PESO%'           => ( ! empty( $params['PESO'] ) ) ? $params['PESO'] : '',
			'%SEND_DIRECCION%' => $sender['direction'],
			'%SEND_NOMBRE%'    => $sender['name'],
			'%SEND_TELEFONO%'  => $sender['phone'],
			'%SEND_ZIP%'       => $sender['zip'],
			'%SERVICIO%'       => $this->credentials['Service_id'],
			'%USUARIO_ID%'     => intval( $this->credentials['User_id'] ),
			'%VARIABLE_1%'     => ( ! empty( $params['VARIABLE_1'] ) ) ? $params['VARIABLE_1'] : '',
			'%VARIABLE_3%'     => ( ! empty( $params['VARIABLE_3'] ) ) ? $params['VARIABLE_3'] : '',
			'%VARIABLE_4%'     => ( ! empty( $params['VARIABLE_4'] ) ) ? $params['VARIABLE_4'] : '',
			'%VARIABLE_5%'     => ( ! empty( $params['VARIABLE_5'] ) ) ? $params['VARIABLE_5'] : '',
			'%VARIABLE_6%'     => ( ! empty( $params['VARIABLE_6'] ) ) ? $params['VARIABLE_6'] : '',
			'%VARIABLE_7%'     => ( ! empty( $params['VARIABLE_7'] ) ) ? $params['VARIABLE_7'] : '',
			'%VARIABLE_8%'     => ( ! empty( $params['VARIABLE_8'] ) ) ? $params['VARIABLE_8'] : '',
			'%VARIABLE_9%'     => ( ! empty( $params['VARIABLE_9'] ) ) ? $params['VARIABLE_9'] : '',
			'%VARIABLE_10%'    => ( ! empty( $params['VARIABLE_10'] ) ) ? $params['VARIABLE_10'] : '',
			'%VARIABLE_11%'    => ( ! empty( $params['VARIABLE_11'] ) ) ? $params['VARIABLE_11'] : '',
			'%VARIABLE_12%'    => ( ! empty( $params['VARIABLE_12'] ) ) ? $params['VARIABLE_12'] : '',
			'%VARIABLE_13%'    => ( ! empty( $params['VARIABLE_13'] ) ) ? $params['VARIABLE_13'] : '',
			'%VARIABLE_14%'    => ( ! empty( $params['VARIABLE_14'] ) ) ? $params['VARIABLE_14'] : '',
			'%VARIABLE_15%'    => ( ! empty( $params['VARIABLE_15'] ) ) ? $params['VARIABLE_15'] : '',
			'%VARIABLE_16%'    => ( ! empty( $params['VARIABLE_16'] ) ) ? $params['VARIABLE_16'] : '',
		);
		$data_types   = array(
			'%Cliente%'        => array( 'type' => 'string', 'length' => 10 ),
			'%COD_CLIENTE%'    => array( 'type' => 'string', 'length' => 20 ),
			'%FECHA_ENVIO%'    => array( 'type' => 'datetime' ),
			'%ENVIO_ID%'       => array( 'type' => 'string', 'length' => 25 ),
			'%SERVICIO%'       => array( 'type' => 'string', 'length' => 5 ),
			'%MONTO_FLETE%'    => array( 'type' => 'numeric' ),
			'%DEST_NOMBRE%'    => array( 'type' => 'string', 'length' => 200 ),
			'%DEST_DIRECCION%' => array( 'type' => 'string', 'length' => 500 ),
			'%DEST_TELEFONO%'  => array( 'type' => 'string', 'length' => 15 ),
			'%DEST_APARTADO%'  => array( 'type' => 'string', 'length' => 20 ),
			'%DEST_ZIP%'       => array( 'type' => 'string', 'length' => 8 ),
			'%SEND_NOMBRE%'    => array( 'type' => 'string', 'length' => 200 ),
			'%SEND_DIRECCION%' => array( 'type' => 'string', 'length' => 500 ),
			'%SEND_ZIP%'       => array( 'type' => 'string', 'length' => 8 ),
			'%SEND_TELEFONO%'  => array( 'type' => 'string', 'length' => 15 ),
			'%OBSERVACIONES%'  => array( 'type' => 'string', 'length' => 200 ),
			'%USUARIO_ID%'     => array( 'type' => 'numeric' ),
			'%PESO%'           => array( 'type' => 'numeric' ),
			'%VARIABLE_1%'     => array( 'type' => 'string', 'length' => 10, 'optional' => true ),
			'%VARIABLE_3%'     => array( 'type' => 'string', 'length' => 1, 'optional' => true ),
			'%VARIABLE_4%'     => array( 'type' => 'string', 'length' => 100, 'optional' => true ),
			'%VARIABLE_5%'     => array( 'type' => 'numeric', 'optional' => true ),
			'%VARIABLE_6%'     => array( 'type' => 'string', 'length' => 2, 'optional' => true ),
			'%VARIABLE_7%'     => array( 'type' => 'string', 'length' => 1, 'optional' => true ),
			'%VARIABLE_8%'     => array( 'type' => 'string', 'length' => 10, 'optional' => true ),
			'%VARIABLE_9%'     => array( 'type' => 'string', 'length' => 1, 'optional' => true ),
			'%VARIABLE_10%'    => array( 'type' => 'string', 'length' => 1, 'optional' => true ),
			'%VARIABLE_11%'    => array( 'type' => 'string', 'length' => 1, 'optional' => true ),
			'%VARIABLE_12%'    => array( 'type' => 'numeric', 'optional' => true ),
			'%VARIABLE_13%'    => array( 'type' => 'string', 'length' => 50, 'optional' => true ),
			'%VARIABLE_14%'    => array( 'type' => 'string', 'length' => 50, 'optional' => true ),
			'%VARIABLE_15%'    => array( 'type' => 'string', 'length' => 50, 'optional' => true ),
			'%VARIABLE_16%'    => array( 'type' => 'string', 'length' => 10, 'optional' => true ),
		);

		if ( $this->check_parameters( $replacements, $data_types, __FUNCTION__ ) ) {

			$this->log( '1' );

			$request_response = $this->request( 'ccrRegistroEnvio', $replacements );
			$response['log'] .= $this->log( $this );
			
			if ( is_object( $request_response ) && isset( $request_response->aCodRespuesta ) ) {

				$this->log( '1.1' );
				$this->log( '1.1' );
				
				if ( $request_response->aCodRespuesta == '00') {
					$this->log( '1.1.1' );
					$response['status'] = 'ok';
				} else {
					$this->log( '1.1.2' );
					$response['status'] = 'error';
				}
				
				$response['log'] .= $this->log( sprintf( 'Guide number: %s, Order id: %s, CodRespuesta: %s: %s', $params['ENVIO_ID'], $order_id, $request_response?->aCodRespuesta, $request_response?->aMensajeRespuesta ) );
				$response['log'] .= $this->log( sprintf( 'Args: %s', print_r( $this->clean_soap_fields_to_parameters( $replacements ), 1 ) ) );
				
			} else{

				$this->log( '1.2' );
				
				$response['status'] = 'error';
				$response['log'] .= $this->log( sprintf( 'Args: %s', print_r( $this->clean_soap_fields_to_parameters( $replacements ), 1 ) ) );
			}
			
			$response['response'] = (array) $request_response;

		} else {
			$this->log( '2' );
			$response['status'] = 'error';
			$response['log'] .= $this->log( 'ccrRegistroEnvio aborted.' );
		}

		return $response;

	}


	/**
	 * Get tracking movil
	 */
	public function get_tracking( string $guide_number ): string 
	{

		$data         = array();
		$replacements = array(
			'%NumeroEnvio%' => $guide_number,
		);
		$data_types = array(
			'%NumeroEnvio%' => array(
				'type'   => 'string',
				'length' => 50,
			),
		);

		if ( $this->check_parameters( $replacements, $data_types, __FUNCTION__ ) ) {

			$response           = $this->request( 'ccrMovilTracking', $replacements );
			$encabezado         = (array) $response->aEncabezado;
			$data['encabezado'] = array(
				'estado'          => $encabezado['aEstado'],
				'fecha-recepcion' => $encabezado['aFechaRecepcion'],
				'destinatario'    => $encabezado['aNombreDestinatario'],
			);

			foreach ( $response->aEventos->accrEvento as $key => $obj ) {
				$item              = (array) $obj;
				$data['eventos'][] = array(
					'evento'     => $item['aEvento'],
					'fecha-hora' => $item['aFechaHora'],
					'unidad'     => $item['aUnidad'],
				);
			}
		}
		return json_encode( $data );
	}

	/**
	 * Check the parameters before sent to CCR WS
	 * @return bool
	 */
	private function check_parameters( array $replacements, array $data_types, string $method = '' ): bool 
	{

		$try_register = true;
		$replacements = $this->clean_soap_fields_to_parameters( $replacements );
		$data_types   = $this->clean_soap_fields_to_parameters( $data_types );

		foreach ( $replacements as $field => $field_value ) {

			$field_params = $data_types[ $field ];

			$this->log( sprintf( 'Checking "%1$s" called from "%2$s".', $field, $method ) );

			/**
			 * Check if field is empty and if should be
			 */
			if ( empty( $field_value ) ) {
				if ( ! empty( $field_params['optional'] ) && true === $field_params['optional'] ) {
					continue;
				} else {
					// translators: Param lenght and param data.
					$this->log( sprintf( 'Empty parameter "%1$s" called from "%2$s".', $field, $method ) );
					$try_register = false;
				}
			}

			/**
			 * Check strings
			 */
			if ( 'string' === $field_params['type'] ) {
				$max_length = $data_types[ $field ]['length'];
				$param_len  = strlen( $field_value );
				if ( $param_len > $max_length ) {
					// translators: Param lenght and param data.
					$this->log( sprintf( '"%1$s" cannot exceed %2$s characters. Given: %3$s, "%4$s" called from "%5$s"', $field, $max_length, $param_len, $field_value, $method ) );
					$try_register = false;
				}
			}

			/**
			 * Check numbers
			 */
			if ( 'numeric' === $field_params['type'] ) {
				if ( ! is_numeric( $field_value ) ) {
					$this->log( sprintf( 'Bad "%1$s" Given: "%2$s" called from "%3$s"', $field, $field_value, $method ) );
					$try_register = false;
				}
			}
		}

		return $try_register;
	}

	/**
	 * Remove the '%' of replacements array
	 */
	private function clean_soap_fields_to_parameters( array $replacements ): array 
	{
		$data = array();
		foreach ( $replacements as $field => $field_value ) {
			$field_name          = str_replace( '%', '', $field );
			$data[ $field_name ] = $field_value;
		}
		return $data;
	}


	/**
	 * Web Service Request
	 */
	private function request( string $method, array $replacements = array() ): object|bool 
	{

		if ( ! in_array( $method, $this->methods, true ) ) {
			return false;
		}

		$curl       = curl_init();
		$parameters = array(
			CURLOPT_PORT           => $this->environment['process_port'],
			CURLOPT_URL            => $this->environment['process_url'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => $this->get_soap_fields( $method, $replacements ),
			CURLOPT_FAILONERROR    => false,
			CURLOPT_HTTPHEADER     => array(
				'Authorization: ' . $this->get_token(),
				'Content-Type: text/xml; charset=utf-8',
				'SOAPAction: http://tempuri.org/IwsAppCorreos/' . $method,
			),
		);

		$parameters = $this->set_proxy_settings( $parameters );

		curl_setopt_array( $curl, $parameters );

		$response = curl_exec( $curl );
		$err      = curl_error( $curl );

		if ( $this?->debug ) {
			if ( $err ) {
				$this->log( sprintf( 'Error in service query: %s', $err ) );
			}
			$this->log( sprintf( 'Response: %s', $response ) );
		}

		// SimpleXML seems to have problems with the colon ":" in the <xxx:yyy> response tags, so take them out.
		$xml           = preg_replace( '/(<\/?)(\w+):([^>]*>)/', '$1$2$3', $response );
		$xml           = simplexml_load_string( $xml );
		$str_response = $method . 'Response';
		$str_result   = $method . 'Result';

		return $xml?->sBody?->$str_response?->$str_result;
	}


	/**
	 * Set proxy settings
	 */
	public function set_proxy( array $params ): void 
	{
		$this->proxy_settings['hostname'] = $params['hostname'];
		$this->proxy_settings['username'] = $params['username'];
		$this->proxy_settings['password'] = $params['password'];
		$this->proxy_settings['port']     = $params['port'];
	}

	/**
	 * Set proxy settings in the curl options
	 */
	private function set_proxy_settings( array $parameters ): array 
	{

		/**
		 * Proxy settings
		 */
		if ( empty ( $this->proxy_settings) ) {
			return $parameters;
		}

		$proxy_hostname = $this->proxy_settings['hostname'];
		$proxy_username = $this->proxy_settings['username'];
		$proxy_password = $this->proxy_settings['password'];
		$proxy_port     = $this->proxy_settings['port'];

		$parameters[ CURLOPT_PROXY ]        = "$proxy_hostname:$proxy_port";
		$parameters[ CURLOPT_PROXYUSERPWD ] = "$proxy_username:$proxy_password";

		return $parameters;
	}


	/**
	 * Prepare soap string before request.
	 */
	private function get_soap_fields( string $method, array $replacements = array() ): string 
	{

		$fields = array(

			// No replacements.
			'ccrCodProvincia'  => "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\">\r\n   <soapenv:Header/>\r\n   <soapenv:Body>\r\n      <tem:ccrCodProvincia/>\r\n   </soapenv:Body>\r\n</soapenv:Envelope>",

			// %CodProvincia%
			'ccrCodCanton'     => "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\">\r\n   <soapenv:Header/>\r\n   <soapenv:Body>\r\n      <tem:ccrCodCanton>\r\n         <tem:CodProvincia>%CodProvincia%</tem:CodProvincia>\r\n      </tem:ccrCodCanton>\r\n   </soapenv:Body>\r\n</soapenv:Envelope>",

			// %CodProvincia%, %CodCanton%
			'ccrCodDistrito'   => "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\">\r\n   <soapenv:Header/>\r\n   <soapenv:Body>\r\n      <tem:ccrCodDistrito>\r\n         <tem:CodProvincia>%CodProvincia%</tem:CodProvincia>\r\n         <tem:CodCanton>%CodCanton%</tem:CodCanton>\r\n      </tem:ccrCodDistrito>\r\n   </soapenv:Body>\r\n</soapenv:Envelope>",

			// %CodProvincia%, %CodCanton%, %CodDistrito%
			'ccrCodBarrio'     => "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\">\r\n   <soapenv:Header/>\r\n   <soapenv:Body>\r\n      <tem:ccrCodBarrio>\r\n         <tem:CodProvincia>%CodProvincia%</tem:CodProvincia>\r\n         <tem:CodCanton>%CodCanton%</tem:CodCanton>\r\n         <tem:CodDistrito>%CodDistrito%</tem:CodDistrito>\r\n      </tem:ccrCodBarrio>\r\n   </soapenv:Body>\r\n</soapenv:Envelope>",

			// %CodProvincia%, %CodCanton%, %CodDistrito%
			'ccrCodPostal'     => "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\">\r\n   <soapenv:Header/>\r\n   <soapenv:Body>\r\n      <tem:ccrCodPostal>\r\n         <tem:CodProvincia>%CodProvincia%</tem:CodProvincia>\r\n         <tem:CodCanton>%CodCanton%</tem:CodCanton>\r\n         <tem:CodDistrito>%CodDistrito%</tem:CodDistrito>\r\n      </tem:ccrCodPostal>\r\n   </soapenv:Body>\r\n</soapenv:Envelope>",

			// %CantonDestino%, %CantonOrigen%, %Peso%, %ProvinciaDestino%, %ProvinciaOrigen%, %Servicio%
			'ccrTarifa'        => "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\" xmlns:wsap=\"http://schemas.datacontract.org/2004/07/wsAppCorreos\">\r\n   <soapenv:Header/>\r\n   <soapenv:Body>\r\n      <tem:ccrTarifa>\r\n         <tem:reqTarifa>\r\n            <wsap:CantonDestino>%CantonDestino%</wsap:CantonDestino>\r\n            <wsap:CantonOrigen>%CantonOrigen%</wsap:CantonOrigen>\r\n            <wsap:Peso>%Peso%</wsap:Peso>\r\n            <wsap:ProvinciaDestino>%ProvinciaDestino%</wsap:ProvinciaDestino>\r\n            <wsap:ProvinciaOrigen>%ProvinciaOrigen%</wsap:ProvinciaOrigen>\r\n            <wsap:Servicio>%Servicio%</wsap:Servicio>\r\n         </tem:reqTarifa>\r\n      </tem:ccrTarifa>\r\n   </soapenv:Body>\r\n</soapenv:Envelope>",

			// No replacements.
			'ccrGenerarGuia'   => "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\">\r\n   <soapenv:Header/>\r\n   <soapenv:Body>\r\n      <tem:ccrGenerarGuia/>\r\n   </soapenv:Body>\r\n</soapenv:Envelope>",

			/**
			 * %Cliente%, %COD_CLIENTE%,
			 * %DEST_APARTADO%, %DEST_DIRECCION%, %DEST_NOMBRE%, %DEST_TELEFONO%, %DEST_ZIP%
			 * %ENVIO_ID%, %FECHA_ENVIO%, %MONTO_FLETE%, %OBSERVACIONES%
			 * %PESO%, %SEND_DIRECCION%, %SEND_NOMBRE%, %SEND_TELEFONO%, %SEND_ZIP%
			 * %SERVICIO%, %USUARIO_ID%
			 * %VARIABLE_1%, %VARIABLE_3%, %VARIABLE_4%, %VARIABLE_5%
			 * %VARIABLE_6%, %VARIABLE_7%, %VARIABLE_8%, %VARIABLE_9%
			 * %VARIABLE_10%, %VARIABLE_11%, %VARIABLE_12%, %VARIABLE_13%
			 * %VARIABLE_14%, %VARIABLE_15%, %VARIABLE_16%
			 */
			'ccrRegistroEnvio' => "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\" xmlns:wsap=\"http://schemas.datacontract.org/2004/07/wsAppCorreos\">\r\n   <soapenv:Header/>\r\n   <soapenv:Body>\r\n      <tem:ccrRegistroEnvio>\r\n         <tem:ccrReqEnvio>\r\n            <wsap:Cliente>%Cliente%</wsap:Cliente>\r\n            <wsap:Envio>\r\n               <wsap:COD_CLIENTE>%COD_CLIENTE%</wsap:COD_CLIENTE>\r\n               <wsap:DEST_APARTADO>%DEST_APARTADO%</wsap:DEST_APARTADO>\r\n               <wsap:DEST_DIRECCION>%DEST_DIRECCION%</wsap:DEST_DIRECCION>\r\n               <wsap:DEST_NOMBRE>%DEST_NOMBRE%</wsap:DEST_NOMBRE>\r\n               <wsap:DEST_TELEFONO>%DEST_TELEFONO%</wsap:DEST_TELEFONO>\r\n               <wsap:DEST_ZIP>%DEST_ZIP%</wsap:DEST_ZIP>\r\n               <wsap:ENVIO_ID>%ENVIO_ID%</wsap:ENVIO_ID>\r\n               <wsap:FECHA_ENVIO>%FECHA_ENVIO%</wsap:FECHA_ENVIO>\r\n               <wsap:MONTO_FLETE>%MONTO_FLETE%</wsap:MONTO_FLETE>\r\n               <wsap:OBSERVACIONES>%OBSERVACIONES%</wsap:OBSERVACIONES>\r\n               <wsap:PESO>%PESO%</wsap:PESO>\r\n               <wsap:SEND_DIRECCION>%SEND_DIRECCION%</wsap:SEND_DIRECCION>\r\n               <wsap:SEND_NOMBRE>%SEND_NOMBRE%</wsap:SEND_NOMBRE>\r\n               <wsap:SEND_TELEFONO>%SEND_TELEFONO%</wsap:SEND_TELEFONO>\r\n               <wsap:SEND_ZIP>%SEND_ZIP%</wsap:SEND_ZIP>\r\n               <wsap:SERVICIO>%SERVICIO%</wsap:SERVICIO>\r\n               <wsap:USUARIO_ID>%USUARIO_ID%</wsap:USUARIO_ID>\r\n               <wsap:VARIABLE_1>%VARIABLE_1%</wsap:VARIABLE_1>\r\n               <wsap:VARIABLE_10>%VARIABLE_10%</wsap:VARIABLE_10>\r\n               <wsap:VARIABLE_11>%VARIABLE_11%</wsap:VARIABLE_11>\r\n               <wsap:VARIABLE_12>%VARIABLE_12%</wsap:VARIABLE_12>\r\n               <wsap:VARIABLE_13>%VARIABLE_13%</wsap:VARIABLE_13>\r\n               <wsap:VARIABLE_14>%VARIABLE_14%</wsap:VARIABLE_14>\r\n               <wsap:VARIABLE_15>%VARIABLE_15%</wsap:VARIABLE_15>\r\n               <wsap:VARIABLE_16>%VARIABLE_16%</wsap:VARIABLE_16>\r\n               <wsap:VARIABLE_3>%VARIABLE_3%</wsap:VARIABLE_3>\r\n               <wsap:VARIABLE_4>%VARIABLE_4%</wsap:VARIABLE_4>\r\n               <wsap:VARIABLE_5>%VARIABLE_5%</wsap:VARIABLE_5>\r\n               <wsap:VARIABLE_6>%VARIABLE_6%</wsap:VARIABLE_6>\r\n               <wsap:VARIABLE_7>%VARIABLE_7%</wsap:VARIABLE_7>\r\n               <wsap:VARIABLE_8>%VARIABLE_8%</wsap:VARIABLE_8>\r\n               <wsap:VARIABLE_9>%VARIABLE_9%</wsap:VARIABLE_9>\r\n            </wsap:Envio>\r\n         </tem:ccrReqEnvio>\r\n      </tem:ccrRegistroEnvio>\r\n   </soapenv:Body>\r\n</soapenv:Envelope>",

			// NumeroEnvio.
			'ccrMovilTracking' => "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\">\r\n   <soapenv:Header/>\r\n   <soapenv:Body>\r\n      <tem:ccrMovilTracking>\r\n         <tem:NumeroEnvio>%NumeroEnvio%</tem:NumeroEnvio>\r\n      </tem:ccrMovilTracking>\r\n   </soapenv:Body>\r\n</soapenv:Envelope>",
		);

		$field = $fields[ $method ];
		foreach ( $replacements as $key => $value ) {
			/**	
			 * Correos de Costa Rica no  soporta ampersand (&) en el parámetro SEND_NOMBRE y posiblemente otros
			 */
			$value = str_replace( '&', '', $value );
			$field = str_replace( $key, $value, $field );

		}

		// Remove empty replacements.
		$field = preg_replace( '/(%[0-9a-zA-z_]+%)/', '', $field );

		return $field;
	}
}
