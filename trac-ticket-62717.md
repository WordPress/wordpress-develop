# Trac Ticket: Skip server image support check when not generating sub-sizes

## Summary

REST API: Skip server-side image editor support check when `generate_sub_sizes` is `false`

## Description

When uploading images via the REST API with `generate_sub_sizes` set to `false`, the server still checks whether the image editor supports the uploaded image format. This causes uploads of formats like AVIF to fail with `rest_upload_image_type_not_supported`, even though the client is handling all image processing and the server doesn't need to generate any sub-sizes.

The `generate_sub_sizes` parameter was introduced to allow clients (such as the block editor with client-side media processing) to handle image resizing themselves. When this parameter is `false`, the server-side image editor support check is unnecessary and should be skipped.

### Steps to Reproduce

1. Enable client-side media processing in the block editor (or use the REST API directly with `generate_sub_sizes=false`)
2. Attempt to upload an AVIF image on a server where the image editor does not support AVIF
3. The upload fails with `rest_upload_image_type_not_supported`

### Expected Result

The upload should succeed because the client is handling all image processing.

### Actual Result

The upload fails with a 400 error: "The web server cannot generate responsive image sizes for this image."

## Proposed Fix

In `WP_REST_Attachments_Controller::create_item_permissions_check()`, after the `wp_prevent_unsupported_mime_type_uploads` filter is applied, check if `generate_sub_sizes` is explicitly set to `false`. If so, set `$prevent_unsupported_uploads` to `false` to bypass the server-side image editor support check.

## Props

adamsilverstein

## Component

Media, REST API

## Version

trunk

## Keywords

has-patch, has-unit-tests

## Related

- Gutenberg PR: https://github.com/WordPress/gutenberg/pull/76371
- Gutenberg Issue: https://github.com/WordPress/gutenberg/issues/76369
