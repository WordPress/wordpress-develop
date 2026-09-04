/**
 * Minimal typings for the admin upload globals: the bundled plupload library,
 * the helpers plupload-handlers.js exposes, and the flags the client-side
 * media upload scripts set on `window`.
 *
 * Only the surface those scripts rely on is described.
 */

declare namespace plupload {
	/**
	 * A file queued in a plupload uploader.
	 */
	interface File {
		id: string;
		name: string;
		type: string;
		size: number;
		loaded: number;
		percent: number;
		status: number;

		/**
		 * The underlying native File, or null when the active runtime (html4,
		 * say) cannot expose one.
		 */
		getNative(): globalThis.File | null;
	}

	/**
	 * A plupload uploader instance.
	 */
	interface Uploader {
		settings?: {
			multipart_params?: Record< string, string >;
			[ setting: string ]: unknown;
		};

		bind(
			name: 'FilesAdded',
			callback: ( up: Uploader, files: File[] ) => unknown,
			context?: unknown,
			priority?: number
		): void;
		bind(
			name: string,
			callback: ( ...args: any[] ) => unknown,
			context?: unknown,
			priority?: number
		): void;
		removeFile( file: File ): void;
		refresh(): void;

		/**
		 * Admin upload scripts flag an uploader they have already intercepted.
		 */
		[ property: string ]: unknown;
	}

	/**
	 * Status of a file that could not be queued.
	 */
	const FAILED: number;
}

declare var pluploadL10n: Record< string, string >;

/**
 * The uploader plupload-handlers.js creates on wp-admin/media-new.php.
 *
 * Undefined when the browser uploader was not initialized.
 */
declare var uploader: plupload.Uploader | undefined;

declare function fileQueued( file: plupload.File ): void;
declare function uploadStart(): void;
declare function uploadSuccess( file: plupload.File, serverId: string ): void;
declare function uploadComplete(): void;

interface Window {
	/**
	 * Settings printed for the media-upload-pipeline script.
	 */
	_wpMediaUploadPipelineSettings?: {
		maxUploadFileSize?: number;
		allowedMimeTypes?: Record< string, string > | null;
		allImageSizes?: Record< string, unknown >;
		bigImageSizeThreshold?: number | false;
		imageStripMeta?: boolean;
		imageMaxBitDepth?: number;
	};

	/**
	 * Set once the media-upload-pipeline script has configured the store, and
	 * read by the media-utils package.
	 */
	__clientSideMediaProcessing?: boolean;

	/**
	 * Guards against a screen upload script running twice.
	 */
	__wpMediaLibraryUpload?: boolean;
	__wpMediaNewUpload?: boolean;
}
