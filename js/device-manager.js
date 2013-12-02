/**
 * JavaScript query a device for platform specific details
 *
 * @author Marc Fallows, 2013
 * @since 2.0.0
 * @package TestSwarm
 */
(function ( $, SWARM, window, require ) {

	var pluginTypes = {
		PLUGIN_SCRIPT: "script",
		PLUGIN_OBJECT: "object"
	}

	var deviceTypes = {
		DEVICE_TYPE_TV: "TV",
		DEVICE_TYPE_STB: "STB",
		DEVICE_TYPE_DESKTOP: "Desktop",
		DEVICE_TYPE_UNKNOWN: "Unknown"
	}

	var defaultDetails = {
		device_key: SWARM.client_ip,
		model: '',
		device_type: deviceTypes.DEVICE_TYPE_UNKNOWN
	};

	var devices = {
		smarttv2: {
			userAgentRegex: /Maple/i,
			getDetails: function(){
				var webDeviceApi = window.webapis;

				function getDeviceType() {
					var product = webDeviceApi.tv.info.getProduct();
					switch (product) {
						case webDeviceApi.tv.info.PRODUCT_TYPE_TV:
							return deviceTypes.DEVICE_TYPE_TV;
						case webDeviceApi.tv.info.PRODUCT_TYPE_BD:
						case webDeviceApi.tv.info.PRODUCT_TYPE_MONITOR:
							return deviceTypes.DEVICE_TYPE_STB;
					}
				}

				var deviceInfo = $.extend( defaultDetails, {
					device_key: webDeviceApi.tv.info.getDeviceID(),
					device_type: getDeviceType(),
					model: webDeviceApi.tv.info.getModel(),

					firmware_version: webDeviceApi.tv.info.getFirmware()
				});

				return deviceInfo;
			},
			plugins: [
				{
					pluginType: pluginTypes.PLUGIN_SCRIPT,
					src: 'http://localhost/$MANAGER_WIDGET/Common/webapi/1.0/webapis.js'
				},
				{
					pluginType: pluginTypes.PLUGIN_SCRIPT,
					src: 'http://localhost/$MANAGER_WIDGET/Common/API/Plugin.js'
				},
				{
					pluginType: pluginTypes.PLUGIN_SCRIPT,
					src: 'http://localhost/$MANAGER_WIDGET/Common/API/TVKeyValue.js'
				},
				{
					pluginType: pluginTypes.PLUGIN_SCRIPT,
					src: 'http://localhost/$MANAGER_WIDGET/Common/API/Widget.js'
				}
			]
		},
		netcast: {
			userAgentRegex: /LG.NetCast/i,
			getDetails: function(){

				var device = $('#netcastInfo')[0];

				function getDeviceType() {
					var platformInfo = /LG[\s_\.]?Netcast\.(\w+)-(\d+)/ig.exec(window.navigator.userAgent),
						deviceType = platformInfo[1];

					switch(deviceType.toUpperCase()) {
						case "TV":
							return deviceTypes.DEVICE_TYPE_TV;
						case "MEDIA":
							return deviceTypes.DEVICE_TYPE_STB;
					}
					return deviceType.toUpperCase();
				}

				var deviceInfo = $.extend( defaultDetails, {
					device_key: device.serialNumber,
					device_type: getDeviceType(),
					model: device.modelName,

					manufacturer: device.manufacturer,
					firmware_version: device.swVersion,
					hardware_version: device.hwVersion,
					device_version: device.version
				});

				return deviceInfo;
			},
			plugins: [
				{
					pluginType: pluginTypes.PLUGIN_OBJECT,
					id: 'netcastInfo',
					type: 'application/x-netcast-info'
				}
			]
		},
		philips: {
			userAgentRegex: /Philips/i,
			getDetails: function(){

				var drmAgent = $('#drmAgent')[0],
					netTvMatch = window.navigator.appVersion.match(/NETTV\/(.*?) /),
					netTvVersion = netTvMatch[1];

				function getDeviceType() {
					var platformInfo = window.navigator.userAgent.match(/PHILIPS-(AVM)/i),
						deviceType = platformInfo[1];

					switch(deviceType.toUpperCase()) {
						case "AVM":
							return deviceTypes.DEVICE_TYPE_STB;
						default:
							return deviceTypes.DEVICE_TYPE_TV;
					}
				}

				var deviceInfo = $.extend( defaultDetails, {
					device_key: drmAgent.DRMDeviceID,
					device_type: getDeviceType(),

					nettv_version: netTvVersion
				});

				return deviceInfo;
			},
			plugins: [
				{
					pluginType: pluginTypes.PLUGIN_OBJECT,
					id: 'drmAgent',
					type: 'application/drmagent'
				}
			]
		},
		// TODO: Gather Toshiba device information.
		toshiba: {
			userAgentRegex: /Toshiba; DTV/i,
			getDetails: function(){

				var deviceInfo = $.extend( defaultDetails, {
					device_key: window.toshibaPlaces.systemInfo.serialNumber,
					device_type: deviceTypes.DEVICE_TYPE_TV
				});

				return deviceInfo;
			}
		},
		panasonic: {
			userAgentRegex: /viera/i,
			getDetails: function(){

				var platformInfo = window.navigator.appVersion.match(/Viera\/(.*?) /),
					model = "Panasonic " + platformInfo[0].trim(),
					vieraVersion = platformInfo[1];

				var deviceInfo = $.extend( defaultDetails, {
					model: model,
					device_key: window.PanasonicDevice.configuration.localSystem.networkInterfaces.item(0).macAddress,
					device_type: deviceTypes.DEVICE_TYPE_TV,

					viera_version: vieraVersion,
					api_version: window.PanasonicDevice.apiVersion
				});

				return deviceInfo;
			}
		},
		technika_avtrex: {
			userAgentRegex: /Technika Media Streamer/i,
			getDetails: function(){

				var deviceInfo = $.extend( defaultDetails, {
					device_key: window.BlinkBoxDevice.getSerialNumber(),
					device_type: deviceTypes.DEVICE_TYPE_STB,
					model: window.BlinkBoxDevice.getModelName(),

					manufacturer: window.BlinkBoxDevice.getManufacturer(),
					version: window.BlinkBoxDevice.getVersion()
				});

				return deviceInfo;
			}
		},
		// TODO: Gather Technika MStar device information.
		technika_mstar: {
			userAgentRegex: /MStar/i,
			getDetails: function(){

				var deviceInfo = $.extend( defaultDetails, {
					device_key: window.systemInfo.serialNumber
				});

				return deviceInfo;
			}
		},
		chrome: {
			userAgentRegex: /Chrome/i,
			getDetails: function(){

				var browserVersion = window.navigator.appVersion.match(/Chrome\/(.*?) /)[1];

				var deviceInfo = $.extend( defaultDetails, {
					device_type: deviceTypes.DEVICE_TYPE_DESKTOP,

					browser_version: browserVersion
				});

				return deviceInfo;
			}
		},
		phantomJS: {
			userAgentRegex: /PhantomJS/i,
			getDetails: function(){

				var browserVersion = window.navigator.appVersion.match(/PhantomJS\/(.*?) /)[1];

				var deviceInfo = $.extend( defaultDetails, {
					device_type: deviceTypes.DEVICE_TYPE_DESKTOP,

					browser_version: browserVersion
				});

				return deviceInfo;
			}
		}
	};

	var deviceManager = function(){

		function identify() {

			if (typeof window.navigator === 'undefined') {
				return device(null);
			}

			var matchingDevice = null;

			var userAgent = window.navigator.userAgent.toString()
			$.each(devices, function(deviceKey, device) {
				if (device.userAgentRegex.test(userAgent)) {
					matchingDevice = device;

					// Break out of the jQuery each.
					return false;
				}
			});

			return device(matchingDevice);
		}

		return {
			identify: identify
		};
	}();

	var device = function(device){

		var deferred = $.Deferred()

		if(!device){
			deferred.resolve(defaultDetails);
		}

		if ( device && device.plugins && device.plugins.length > 0 ) {
			var dependencies = [];

			$.each(device.plugins, function(i, plugin) {

				switch (plugin.pluginType){
					case pluginTypes.PLUGIN_OBJECT:

						var objectElement = document.createElement('object');
						objectElement.type = plugin.type;
						objectElement.id = plugin.id;
						objectElement.height = objectElement.width = objectElement.border = '0';
						$( "body" ).append(objectElement);

						break;

					case pluginTypes.PLUGIN_SCRIPT:

						dependencies.push(plugin.src)
						break;
				}
			});

			if (dependencies.length > 0) {
				require(dependencies, function() {
					deferred.resolve(device.getDetails());
				}, function(err) {
					deferred.reject({
						error: true,
						message: "Could not resolve plugin dependencies."
					});
				});
				return deferred.promise();
			}
		}

		deferred.resolve(device.getDetails());
		return deferred.promise();
	};

	window.deviceManager = deviceManager;

}( jQuery, SWARM, window, require ) );
