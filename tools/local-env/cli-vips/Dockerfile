ARG LOCAL_PHP=latest
FROM wordpressdevelop/cli:${LOCAL_PHP}

# Everything is installed in a single layer on purpose. The published base image sits at
# the overlayfs layering limit, so a second layer of our own is enough to make the build
# fail with "max depth exceeded".
#
# This base image does not compile FFI in, unlike the php image, so libffi-dev is needed
# to build the extension here.
#
# libvips42 is the library the binding loads. The libheif plugins supply the HEIF/AVIF
# codecs: libvips can decode both formats out of the box, but it cannot encode either
# without an explicit encoder, so x265 (HEIC) and aomenc (AVIF) are required for saving.
#
# FFI is enabled, and the stack size check is disabled because libvips' native call depth
# can trip it.
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libvips42 \
		libffi-dev \
		libheif-plugin-libde265 \
		libheif-plugin-dav1d \
		libheif-plugin-x265 \
		libheif-plugin-aomenc; \
	rm -rf /var/lib/apt/lists/*; \
	docker-php-ext-install ffi; \
	docker-php-ext-enable ffi; \
	{ \
		echo 'ffi.enable=1'; \
		echo 'zend.max_allowed_stack_size=-1'; \
	} > /usr/local/etc/php/conf.d/vips-ffi.ini
