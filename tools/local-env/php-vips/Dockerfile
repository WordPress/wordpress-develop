ARG LOCAL_PHP=latest
FROM wordpressdevelop/php:${LOCAL_PHP}

RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends libvips42 libvips-dev; \
	rm -rf /var/lib/apt/lists/*

# Install and enable FFI PHP extension
RUN set -eux; \
	docker-php-ext-install ffi; \
	docker-php-ext-enable ffi

# Configure FFI and stack size for VIPS
RUN set -eux; \
	{ \
		echo 'ffi.enable=1'; \
		echo 'zend.max_allowed_stack_size=-1'; \
	} > /usr/local/etc/php/conf.d/vips-ffi.ini
