<?php
/*
Plugin Name: Casa Blaca Plugin
Plugin URI: http://krkn.solutions/
Description: KRKN Solutions plugin for WP.
Version: 1.1.0
Author: KRKN SOLUTIONS
Author URI: http://krkn.solutions/
License: GPLv2 or later
Text Domain: krkn.solutions
*/

/**
 * Displays the map
 *
 * @access      private
 * @since       1.0
 * @return      void
*/
class CasaBlanca_Shortcode {
	static $add_script;

	static function init() {
		add_shortcode('casablanca_sc', array(__CLASS__, 'handle_shortcode'));
		add_action( 'wp_head', array(__CLASS__, 'pw_map_css') );
		add_action( 'init', array(__CLASS__, 'register_script') );
		add_action( 'wp_footer', array(__CLASS__, 'print_script') );
	}

	static function handle_shortcode($atts) {
		self::$add_script = true;

		// actual shortcode handling here
		$atts = shortcode_atts(
			array(
				'address'           => false,
				'width'             => '100%',
				'height'            => '400px',
				'enablescrollwheel' => 'true',
				'zoom'              => 15,
				'disablecontrols'   => 'false',
				'mlat'              => false,
				'mlng'              => false
			),
			$atts);

		$address = $atts['address'];
		$mlat = $atts['mlat'];
		$mlng = $atts['mlng'];
		
		if( ( $address )  && wp_script_is( 'googlemap', 'registered' ) ) :

			wp_print_scripts( 'googlemap' );

			$coordinates = array( 
							'lat' => $mlat,
							'lng' => $mlng,
							);//self::pw_map_get_coordinates( $address );

			if( !is_array( $coordinates ) )
				return;

			$map_id = uniqid( 'pw_map_' );

			ob_start(); ?>
			<div class="pw_map_canvas" id="map" style="height: <?= esc_attr( $atts['height'] ); ?>; width: <?php echo esc_attr( $atts['width'] ); ?>"></div>
			<div class="pw_map_console">
				<div>
					<label class="label" for="map_latlng">Destino (lat,lng)</label>
					<input type="text" name="map_latlng" id="map_latlng" value="<?= $coordinates['lat'] ?>,<?= $coordinates['lng']; ?>" disabled="disabled">
				</div>	    			    	

				<div>
					<label class="label" for="map_ubicacion">Ubicación (lat,lng)</label>
					<input type="text" name="map_ubicacion" id="map_ubicacion" placeholder="Detectando ubicación..." disabled="disabled">
				</div>

				<div>
					<label class="label" for="map_ubicacion_dir">Dirección Origen</label>
					<input type="text" name="map_ubicacion_dir" id="map_ubicacion_dir" placeholder="Detectando ubicación...">
				</div>	
				
				<p>&nbsp;</p>
				<button id="map_geolocate">Calcula Ruta</button>
				
				<hr/>
				<div id="distance"></div>
				<hr/>
				<div id="steps"></div>
				<hr/>
			</div>
			<script type="text/javascript">
				var directionDisplay;
				var directionsService;
				var map;
				var converted_map;

				function pw_run_map_<?php echo $map_id ; ?>(){
					directionsDisplay = new google.maps.DirectionsRenderer();
					directionsService = new google.maps.DirectionsService();
					var location = new google.maps.LatLng("<?php echo $coordinates['lat']; ?>", "<?php echo $coordinates['lng']; ?>");
					//var image = 'http://www.metro303apartments.com/themes/millcreek/prop/metro303/default/images/markers/main-pin.png';

					var map_options = {
						zoom: <?php echo $atts['zoom']; ?>,
						center: location,
						scrollwheel: <?php echo 'true' === strtolower( $atts['enablescrollwheel'] ) ? '1' : '0'; ?>,
						disableDefaultUI: <?php echo 'true' === strtolower( $atts['disablecontrols'] ) ? '1' : '0'; ?>,
						mapTypeId: google.maps.MapTypeId.ROADMAP
					}
					
					map = new google.maps.Map(document.getElementById("map"), map_options);

					directionsDisplay.setMap(map);
					
					var marker = new google.maps.Marker({
						draggable: true,
						position: location,
						map: map,
						//icon: image
					});

					//map  = map_<?php echo $map_id ; ?>;

					var geocoder = new google.maps.Geocoder;
					var infoWindow = new google.maps.InfoWindow({
						map: map
					});

					//Try HTML5 geolocation.
					if (navigator.geolocation) {
						navigator.geolocation.getCurrentPosition(function(position) {
							var pos = {
								lat: position.coords.latitude,
								lng: position.coords.longitude
							};

							infoWindow.setPosition(pos);			                
							infoWindow.setContent( 'Ubicación detectada.<br/>' + pos.lat + ',' + pos.lng );
							document.getElementById('map_ubicacion').value = pos.lat + ',' + pos.lng;
							geocodeLatLng(geocoder, map, infoWindow, document.getElementById('map_ubicacion').value);

							map.setCenter(pos);
						}, function() {
							handleLocationError(true, infoWindow, map.getCenter());
						});
					} else {
						// Browser doesn't support Geolocation
						handleLocationError(false, infoWindow, map.getCenter());
					}

					document.getElementById('map_geolocate').addEventListener('click', function() {
						
						calcRoute(
							document.getElementById('map_ubicacion').value,
							document.getElementById('map_latlng').value
						);

					});
				}

				function geocodeLatLng(geocoder, map, infowindow, input) {
				  var input = input;
				  console.log('input',input);
				  var latlngStr = input.split(',', 2);
				  var latlng = {lat: parseFloat(latlngStr[0]), lng: parseFloat(latlngStr[1])};
				  geocoder.geocode({'location': latlng}, function(results, status) {
					if (status === google.maps.GeocoderStatus.OK) {
					  if (results[1]) {
						map.setZoom(11);
						var marker = new google.maps.Marker({
							draggable: true,
							position: latlng,
							map: map
						});

						google.maps.event.addListener(marker,'drag',function(event) {
							//document.getElementById('lat').value = event.latLng.lat();
							//document.getElementById('lng').value = event.latLng.lng();
						});

						google.maps.event.addListener(marker,'dragend',function(event) {
							//document.getElementById('lat').value = event.latLng.lat();
							//document.getElementById('lng').value = event.latLng.lng();
							document.getElementById('map_ubicacion').value = event.latLng.lat() + "," + event.latLng.lng();
							document.getElementById('map_ubicacion_dir').value = results[1].formatted_address;
						});

						infowindow.setContent(results[1].formatted_address);
						infowindow.open(map, marker);

						document.getElementById('map_ubicacion_dir').value = results[1].formatted_address;

					  } else {
						window.alert('No results found');
					  }
					} else {
					  window.alert('Geocoder failed due to: ' + status);
					}
				  });
				}


				function calcRoute(from, to) {
					var start = from;
					var end = to;
					var request = {
						origin: start,
						destination: end,
						provideRouteAlternatives: true,
						travelMode: google.maps.DirectionsTravelMode.DRIVING,
						unitSystem: google.maps.DirectionsUnitSystem.METRIC //IMPERIAL     //METRIC
					};
					// function to round the decimal digits eg: round(123.456,2); gives 123.45
					function round(number, X) {
						X = (!X ? 2 : X);
						return Math.round(number * Math.pow(10, X)) / Math.pow(10, X);
					}
					directionsService.route(request, function(response, status) {
						if (status == google.maps.DirectionsStatus.OK) {
							directionsDisplay.setDirections(response);
							var distance = response.routes[0].legs[0].distance.text;
							var time_taken = response.routes[0].legs[0].duration.text;

							var calc_distance = response.routes[0].legs[0].distance.value;			            	
							var amount_to_pay = 1;

							function roundNumber(numbr, decimalPlaces) {
								var placeSetter = Math.pow(10, decimalPlaces);
								numbr = Math.round(numbr * placeSetter) / placeSetter;
								return numbr;
							}
							var mi = calc_distance / 1.609;
							var mi = mi / 1000;
							var mi = roundNumber(mi, 2); //Sets value to 2 decimal places.

							var rounded_amount_to_pay = round(amount_to_pay / 1000, 2);
							document.getElementById('distance').innerHTML = 
								'<div class="distance-inner">' + "Distancia entre <em>" + from + "</em> and <em>" + to + "</em>: <strong>" + distance + " / " + mi + " mi</strong>\n\
								<br/>\n\
								Tiempo estimado de viaje: <strong>" + time_taken + "</strong><br/>\n\
								<br/>\n\
								<br/>\n\
						<div style='color: #8F4C4C; font-size: 11px;'><em>Charge rate: <5kms: " + "curr_format" + "less_five" + ", >5kms: " + "curr_format" + "more_five" + ", <span>Travel Time: " + "travel_time" + "</span></em></div></div>";

							var steps = "<ul>";
							var myRoute = response.routes[0].legs[0];
							for (var i = 0; i < myRoute.steps.length; i++) {
								steps += "<li>" + myRoute.steps[i].instructions + "</li>";
							}
							steps += "</ul>";
							document.getElementById('steps').innerHTML = '<div class="steps-inner"><h2>Direcciónes a destino ' + response.routes[0].legs[0].end_address + '</h2>' + steps + '</div>';
						} else {
							document.getElementById('distance').innerHTML = '<span class="gdc-error">Google Map could not be created for the entered parameters. Please be specific while providing the destination location.</span>';
						}
					});
				}


				pw_run_map_<?php echo $map_id ; ?>();
			</script>
			<?php
			return ob_get_clean();
		else :
			return __( 'This Google Map cannot be loaded because the maps API does not appear to be loaded', 'pw-maps' );
		endif;

	}

	static function register_script() {
		wp_register_script( 'googlemap', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyDK1L5bVyXN6LvHOV-Hpo0YucggsEDXp9o' );
		wp_register_script('casablanca_sc-script', plugins_url('js/simple-map.min.js', __FILE__), array('jquery'), '1.0', true);

	}

	static function print_script() {
		if ( ! self::$add_script )
			return;
		//wp_print_scripts('googlemap');
		wp_print_scripts('casablanca_sc-script');
	}

	static function pw_map_get_coordinates( $address, $force_refresh = false ) {

		$address_hash = md5( $address );

		$coordinates = get_transient( $address_hash );

		if ($force_refresh || $coordinates === false) {

			$args       = array( 'address' => urlencode( $address ), 'sensor' => 'false' );
			$url        = add_query_arg( $args, 'http://maps.googleapis.com/maps/api/geocode/json' );
			$response 	= wp_remote_get( $url );

			if( is_wp_error( $response ) )
				return;

			$data = wp_remote_retrieve_body( $response );

			if( is_wp_error( $data ) )
				return;

			if ( $response['response']['code'] == 200 ) {

				$data = json_decode( $data );

				if ( $data->status === 'OK' ) {

					$coordinates = $data->results[0]->geometry->location;

					$cache_value['lat'] 	= $coordinates->lat;
					$cache_value['lng'] 	= $coordinates->lng;
					$cache_value['address'] = (string) $data->results[0]->formatted_address;

					// cache coordinates for 3 months
					set_transient($address_hash, $cache_value, 3600*24*30*3);
					$data = $cache_value;

				} elseif ( $data->status === 'ZERO_RESULTS' ) {
					return __( 'No location found for the entered address.', 'pw-maps' );
				} elseif( $data->status === 'INVALID_REQUEST' ) {
					return __( 'Invalid request. Did you enter an address?', 'pw-maps' );
				} else {
					return __( 'Something went wrong while retrieving your map, please ensure you have entered the short code correctly.', 'pw-maps' );
				}

			} else {
				return __( 'Unable to contact Google API service.', 'pw-maps' );
			}

		} else {
		   // return cached results
		   $data = $coordinates;
		}

		return $data;
	}
	//key=AIzaSyDK1L5bVyXN6LvHOV-Hpo0YucggsEDXp9o

	static function pw_map_css() {
		echo '<style type="text/css">/* =Responsive Map fix
		-------------------------------------------------------------- */
		.pw_map_canvas img {
			max-width: none;
		}</style>';

	}


	
}

CasaBlanca_Shortcode::init();