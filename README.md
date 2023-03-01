# Actualización planeada para Enero 2023

Por favor revisar rama php8+

Cualquier PR es bienvenido.



# correos-de-costa-rica-pymexpress-ws-client

PHP WS Client Based on Mojito Shipping

Clase de conexión para el nuevo Web Service de Correos de Costa Rica (Pymexpress). Requiere de datos de conexión, si usted no cuenta con estos datos puede solicitarlos al correo jmora {arroba} correos.go.cr

Se usa Curl en lugar de SoapClient, esto permite controlar el timeout cuando la IP no está autorizada en el firewall de Correos de Costa Rica.

Pymexpress y otros son marcas propias de Correos de Costa Rica.

**Instalación**

Composer
```
composer require nomanualdev/correos-de-costa-rica-pymexpress-ws-client
```



**Inicializar**

$environment acepta "sandbox" o "production" (Pendiente: URLs de producción. Actualmente la conexión se hace hacia sandbox / pruebas ).

```
$pymexpress = new Pymexpress\Pymexpress_WSC( $username, $password, $user_id, $service_id, $client_code, $environment );
```



**Asignar Proxy [ Opcional ]**
```
$pymexpress->set_proxy( array(
	'hostname' => 'My Host',
	'username' => 'My Username',
	'password' => 'My Password',
	'port'     => 'My Host port',
));
```



**Obtener número de guía**
```
$guia = $pymexpress->generar_guia();
```



**Obtener provincias**
```
$provincias = $pymexpress->get_provincias();
```



**Obtener cantones de una provincia**
- 1 = San José
```
$cantones = $pymexpress->get_cantones( '1' );
```



**Obtener distritos de un cantón**
- 1 = San José
- 01 = San José
```
$distritos = $pymexpress->get_distritos( '1', '01' );
```



**Obtener barrios de un distrito**
- 1 = San José
- 01 = San José
- 01 = Carmen
```
$barrios = $pymexpress->get_barrios( '1', '01', '01' );
```



**Obtener código postal**
- 1 = San José
- 01 = San José
- 01 = Carmen
```
$codigo_postal = $pymexpress->get_codigo_postal( '1', '01', '01' );
```



**Obtener tarifa**
- Envío desde San José, Carmen a San José, Carmen 1 kg de peso
```
$tarifa = $pymexpress->get_tarifa( '1', '01', '1', '01', '1000' );
```


**Registrar envío**

Número de pedido
```
$order_id = '1942';
```

Parámetros de envío
```
$params   = array(
	'DEST_APARTADO'  => '10101', // Código postal destino
	'DEST_DIRECCION' => '100 mts sur del Wallmart', // Dirección
	'DEST_NOMBRE'    => 'Pedro Perez', // Nombre del destinatario
	'DEST_TELEFONO'  => '22334455', // Teléfono del destinatario
	'DEST_ZIP'       => '10101', // Código postal destino
	'ENVIO_ID'       => 'PY000000000CR', // Número de guía
	'MONTO_FLETE'    => '2500', // Costo del envío
	'OBSERVACIONES'  => 'Ropa y otros productos', // Descripción
	'PESO'           => '2000', // peso en gramos
);
```

Sender
```
$sender = array(
	'direction' => 'San José, Pavas',
	'name'      => 'Mi Tienda en línea.com',
	'phone'     => '88776655',
	'zip'       => '20301', // Código postal del remitente
);
```

Enviar a Correos de Costa Rica
```
$envio = $pymexpress->registro_envio( $order_id, $params, $sender );
```
