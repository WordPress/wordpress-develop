/**
 * Typings for the client-side media upload scripts in wp-admin: the bundled
 * plupload surface they use, the helpers plupload-handlers.js exposes, the
 * flags they set on `window`, and the shapes the scripts share with each
 * other.
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

/**
 * The parts of a wp.media.model.Attachment this script relies on.
 */
interface WPAttachment extends Backbone.Model {}

/**
 * The parts of a wp.Uploader instance the Media Library grid script relies
 * on.
 */
interface WPUploader {
	/**
	 * The plupload uploader it wraps.
	 */
	uploader: plupload.Uploader;

	/**
	 * Runs when a file is queued.
	 */
	added( model: WPAttachment ): void;

	/**
	 * Runs when an upload finished.
	 */
	success( model: WPAttachment ): void;

	/**
	 * Runs when an upload failed.
	 */
	error(
		message: string,
		data: Record< string, unknown >,
		file: { name: string }
	): void;
}

/**
 * The placeholder attributes wp-plupload.js builds for an uploading tile.
 */
interface PlaceholderAttributes {
	/**
	 * The file being uploaded.
	 */
	file: plupload.File;

	/**
	 * Always true while the upload runs.
	 */
	uploading: boolean;

	/**
	 * When the upload started.
	 */
	date: Date;
	filename: string;
	menuOrder: number;

	/**
	 * The post the upload is attached to.
	 */
	uploadedTo: number;

	/**
	 * Bytes uploaded so far.
	 */
	loaded: number;

	/**
	 * Size of the file in bytes.
	 */
	size: number;

	/**
	 * Progress percentage.
	 */
	percent: number;

	/**
	 * Mime type guessed from the file name.
	 */
	type?: string;

	/**
	 * Mime subtype guessed from the file name.
	 */
	subtype?: string;
}

/**
 * An item in the @wordpress/upload-media queue.
 */
interface QueueItem {
	id: string;

	/**
	 * Set on the sub-size children of an upload.
	 */
	parentId?: string;

	/**
	 * The file being processed.
	 */
	sourceFile?: File;

	/**
	 * Progress percentage, when the store reports one.
	 */
	progress?: number;

	/**
	 * The operation being run.
	 */
	currentOperation?: string;

	/**
	 * The operations left to run.
	 */
	operations?: unknown[];

	/**
	 * The sub-sizes sideloaded so far.
	 */
	subSizes?: unknown[];
}

/**
 * The pipeline's bookkeeping for one queued upload.
 */
interface UploadEntry {
	/**
	 * Identity key of the file.
	 */
	key: string;

	/**
	 * ID of the queue item it matched.
	 */
	itemId: string | null;

	/**
	 * Progress callback.
	 */
	onProgress?: ( percent: number ) => void;

	/**
	 * Last reported percentage.
	 */
	lastPercent: number;

	/**
	 * Operation counts.
	 */
	totals: { total: number; remaining: number } | null;

	/**
	 * Whether it finished.
	 */
	released: boolean;
}

/**
 * A finalized attachment as returned by the client-side pipeline.
 */
interface PipelineAttachment {
	id: number;
}

/**
 * Why an upload failed.
 *
 * A rejected apiFetch is not always an Error: a REST failure arrives as a
 * plain { code, message } object.
 */
interface UploadError {
	/**
	 * Error code, when the pipeline supplied one.
	 */
	code?: string;

	/**
	 * Human-readable reason.
	 */
	message?: string;
}

/**
 * Lifecycle callbacks for a file queued with wp.mediaUploadPipeline.
 */
interface QueueFileCallbacks {
	/**
	 * Called with the finalized attachment.
	 */
	onSuccess?: ( attachment: PipelineAttachment ) => void;

	/**
	 * Called with the upload error.
	 */
	onError?: ( error: UploadError ) => void;

	/**
	 * Called with an integer percentage (0-99) whenever it changes.
	 */
	onProgress?: ( percent: number ) => void;
}

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
