ARG LOCAL_PHP=latest
FROM wordpressdevelop/php:${LOCAL_PHP}

# libvips42 is the library the binding loads. libffi-dev is required to compile the FFI
# extension that performs the loading, and is otherwise only pulled in transitively by
# the libvips development packages.
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends libvips42 libffi-dev; \
	rm -rf /var/lib/apt/lists/*

# Install and enable the FFI extension.
RUN set -eux; \
	docker-php-ext-install ffi; \
	docker-php-ext-enable ffi

# Enable FFI, and disable the stack size check that libvips' native call depth can trip.
RUN set -eux; \
	{ \
		echo 'ffi.enable=1'; \
		echo 'zend.max_allowed_stack_size=-1'; \
	} > /usr/local/etc/php/conf.d/vips-ffi.ini
