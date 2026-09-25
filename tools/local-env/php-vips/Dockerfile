ARG LOCAL_PHP=latest
FROM wordpressdevelop/php:${LOCAL_PHP}

# Everything is installed in a single layer on purpose. The published base image sits
# at the overlayfs layering limit, so a second layer of our own is enough to make the
# build fail with "max depth exceeded".
#
# libvips42 is the library the binding loads. libffi-dev is required to compile the FFI
# extension that performs the loading, and is otherwise only pulled in transitively by
# the libvips development packages.
#
# FFI is enabled, and the stack size check is disabled because libvips' native call
# depth can trip it.
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends libvips42 libffi-dev; \
	rm -rf /var/lib/apt/lists/*; \
	docker-php-ext-install ffi; \
	docker-php-ext-enable ffi; \
	{ \
		echo 'ffi.enable=1'; \
		echo 'zend.max_allowed_stack_size=-1'; \
	} > /usr/local/etc/php/conf.d/vips-ffi.ini
