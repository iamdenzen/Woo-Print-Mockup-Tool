# Woo Print Mockup Tool

A WooCommerce plugin for generating product mockups by placing customer artwork onto configurable product images.

Woo Print Mockup Tool allows store administrators to define printable or decoratable areas directly on WooCommerce products and generate visual previews using uploaded artwork.

The plugin supports interactive customer previews as well as authenticated batch rendering for external systems such as CRM platforms, automation tools, and custom integrations.

## Features

### Product Mockup Configuration

Adds a dedicated **Print Mockup** tab to the WooCommerce product editor.

Administrators can:

* Enable or disable mockup rendering per product.
* Select a mockup image using the WordPress Media Library.
* Define a rectangular artwork placement area.
* Define a four-point perspective placement area.
* Select a color or engraving render mode.
* Edit and reset placement areas visually.

Placement coordinates are stored as normalized values, allowing the renderer to work independently of the editor display size.

### Fabric.js Placement Editor

The admin interface includes a Fabric.js-based visual placement editor.

Supported placement modes:

#### Rectangle

Define a standard rectangular print area.

```json
{
    "type": "rectangle",
    "left": 0.15,
    "top": 0.21,
    "width": 0.30,
    "height": 0.18
}
```

#### Four-Point Perspective

Define four independent destination points for perspective transformation.

```json
{
    "type": "perspective",
    "points": [
        {
            "x": 0.21,
            "y": 0.28
        },
        {
            "x": 0.63,
            "y": 0.26
        },
        {
            "x": 0.66,
            "y": 0.54
        },
        {
            "x": 0.19,
            "y": 0.56
        }
    ]
}
```

Perspective rendering uses a standard 2D perspective transformation rather than 3D surface detection.

## Image Rendering

The local rendering engine uses PHP Imagick.

Current rendering capabilities include:

* PNG artwork.
* JPEG artwork.
* Automatic near-white background removal.
* Aspect-ratio-preserving artwork resizing.
* Centered artwork placement.
* Rectangle placement.
* Four-point perspective distortion.
* Color rendering.
* Grayscale engraving-style rendering.
* Transparent artwork support.
* PNG preview output.

Artwork inputs are validated before being processed.

Current artwork limits:

* Maximum file size: 10 MB.
* Maximum image width: 8,000 pixels.
* Maximum image height: 8,000 pixels.
* Maximum total image size: 20 megapixels.

These limits apply to both uploaded and remotely downloaded artwork.

## Customer Product Previews

Configured WooCommerce products display an artwork uploader on the product page.

Customers can:

1. Upload artwork.
2. Generate a product preview.
3. Navigate between configured products.
4. See the same artwork automatically applied to other products.
5. Refresh or revisit product pages without uploading the artwork again.

Customer artwork is associated with a dedicated browser session.

The session works for both:

* Guest visitors.
* Logged-in WordPress or WooCommerce customers.

The artwork session is independent of the WooCommerce login state. Logging in or out does not automatically discard the active artwork.

### Persistent Artwork Sessions

Customer artwork is stored temporarily in an artwork session.

The session tracks:

* A hashed browser session key.
* The active artwork path.
* The artwork SHA-256 hash.
* The expiration time.

Generated product previews are cached separately.

For example:

```text
Artwork Session
    |
    +-- Product 100 Preview
    +-- Product 200 Preview
    +-- Product 300 Preview
```

When a customer visits another configured product:

* If a valid preview already exists, the cached result is returned.
* If no preview exists, the current artwork is rendered on the product.
* If no active artwork exists, the frontend silently waits for an upload.

Uploading new artwork replaces the current artwork and invalidates the previous session previews.

## REST API

REST namespace:

```text
wpmt/v1
```

### Render Job

```text
POST /wp-json/wpmt/v1/render-job
```

The render-job endpoint allows external systems to generate mockups for multiple WooCommerce products.

Typical integrations include:

* Make.com
* HubSpot workflows
* CRM systems
* Offer and quotation systems
* Internal automation tools
* Custom applications

### Authentication

The render API uses Bearer token authentication.

```text
Authorization: Bearer YOUR_API_KEY
```

The API key is configured from the plugin settings page.

API keys are not intended to be exposed in browser-side JavaScript.

### Product Identifiers

The API accepts:

* WooCommerce product IDs.
* WooCommerce SKUs.
* Mixed IDs and SKUs.

Example:

```json
{
    "products": [
        123,
        "MUG-BLACK-01",
        "PEN-405"
    ]
}
```

For string identifiers, SKU resolution is attempted first. This allows numeric WooCommerce SKUs to work correctly.

The legacy `product_ids` input is also accepted for backwards compatibility.

Invalid product identifiers are explicitly returned as errors rather than silently ignored.

## Artwork Input

The API accepts artwork in two ways.

### File Upload

Send a multipart request using:

```text
artwork_file
```

The legacy alias is also accepted:

```text
logo_file
```

### Public Artwork URL

Artwork can be downloaded from a public HTTP or HTTPS URL.

```json
{
    "artwork_url": "https://cdn.example.com/customer/logo.png"
}
```

The legacy alias is also accepted:

```text
logo_url
```

Remote artwork is downloaded to temporary storage, validated, and normalized into the same local artwork workflow used for uploaded files.

If both a file and URL are supplied, the uploaded file takes precedence.

Remote artwork handling includes:

* Public URL validation.
* WordPress HTTP API downloading.
* Temporary file cleanup.
* Download timeout.
* File size validation.
* MIME validation based on file content.
* Image dimension validation.
* Pixel-count validation.

## API Request Example

```json
{
    "job_id": "offer-93821",
    "products": [
        "MUG-001",
        "PEN-004",
        48375
    ],
    "artwork_url": "https://cdn.example.com/customer/logo.png",
    "webhook_url": "https://automation.example.com/mockup-webhook"
}
```

Request headers:

```text
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

## API Response

Example successful response:

```json
{
    "success": true,
    "job_id": "offer-93821",
    "status": "success",
    "results": [
        {
            "success": true,
            "product_id": 123,
            "sku": "MUG-001",
            "image_url": "https://example.com/wp-content/uploads/woo-print-mockup-tool/results/jobs/offer-93821/123-preview.png"
        },
        {
            "success": true,
            "product_id": 456,
            "sku": "PEN-004",
            "image_url": "https://example.com/wp-content/uploads/woo-print-mockup-tool/results/jobs/offer-93821/456-preview.png"
        }
    ]
}
```

Both the WooCommerce product ID and SKU are returned when available.

## Job Idempotency

`job_id` is used as an idempotency key.

Submitting the same job ID multiple times does not intentionally generate duplicate render jobs.

If the job is already processing, the API returns the existing job state.

Example:

```json
{
    "success": true,
    "idempotent": true,
    "job_id": "offer-93821",
    "status": "processing",
    "results": []
}
```

If the job has already finished, its stored results are returned.

The database also enforces a unique job ID to protect against concurrent duplicate requests.

A failed job should be retried using a new job ID.

For example:

```text
offer-93821-retry-1
```

## Webhooks

A render request may provide an optional webhook URL.

```json
{
    "webhook_url": "https://automation.example.com/mockup-webhook"
}
```

After the render job is finalized, the plugin sends a JSON payload containing the job status and results.

Example:

```json
{
    "job_id": "offer-93821",
    "status": "success",
    "results": [
        {
            "product_id": 123,
            "sku": "MUG-001",
            "success": true,
            "image_url": "https://example.com/generated-preview.png"
        }
    ]
}
```

Webhook delivery is tracked independently from rendering status.

A job may therefore be:

```text
render status: completed
webhook status: failed
```

This means the images were generated successfully but the callback could not be delivered.

Webhook delivery supports up to three attempts using WordPress scheduled events.

Webhook state includes:

* Delivery status.
* Number of attempts.
* Last delivery error.
* Delivery timestamp.
* Next retry timestamp.

## Customer Preview Endpoint

```text
POST /wp-json/wpmt/v1/preview
```

The customer preview endpoint is used internally by the product-page frontend.

It supports:

* Product preview generation.
* Artwork session restoration.
* Cached preview restoration.
* Cross-product artwork reuse.

The endpoint is protected using a WordPress REST nonce.

Remote artwork URLs are intentionally not accepted by the public customer preview endpoint.

## Preview Rate Limiting

New customer artwork uploads are rate limited.

Rate limiting is applied using:

* Client IP.
* Customer artwork session.

Cached preview restoration and session restoration do not consume render rate limits.

This prevents normal page refreshes and product navigation from unnecessarily exhausting the preview allowance.

## Temporary Storage

Plugin-generated files are stored under:

```text
wp-content/uploads/woo-print-mockup-tool/
```

Directory structure:

```text
woo-print-mockup-tool/
├── artwork/
└── results/
    ├── jobs/
    └── sessions/
```

### Job Results

```text
results/jobs/{job_id}/
```

Used by authenticated API render jobs.

### Session Results

```text
results/sessions/{session_key}/
```

Used by customer product previews.

## Automatic Cleanup

The plugin registers an hourly cleanup task.

Temporary resources have configurable expiration times.

Cleanup removes:

* Expired API artwork.
* Expired customer artwork.
* Expired render results.
* Expired render jobs.
* Expired artwork sessions.
* Generated preview files.
* Empty result directories.

Product mockup configuration is permanent and is not removed by temporary result cleanup.

Because WordPress WP-Cron is traffic-driven, production installations may benefit from triggering `wp-cron.php` through a real system cron.

## Database Architecture

The plugin uses dedicated database tables rather than storing structured rendering data in `wp_postmeta`.

### `wp_wpmt_product_configs`

Stores permanent WooCommerce product mockup configuration.

Typical fields include:

```text
product_id
enabled
mockup_image_id
placement_type
placement_data
render_mode
```

### `wp_wpmt_artwork_sessions`

Stores active temporary customer artwork.

Typical fields include:

```text
session_key
artwork_path
artwork_hash
expires_at
```

### `wp_wpmt_render_jobs`

Stores authenticated external API jobs.

Typical fields include:

```text
job_id
source
status
artwork_path
webhook_url
webhook_status
webhook_attempts
webhook_last_error
webhook_delivered_at
webhook_next_attempt_at
expires_at
```

Customer product previews do not create render jobs.

### `wp_wpmt_render_results`

Stores generated render outputs for both API jobs and customer sessions.

API result:

```text
job_id      = offer-93821
session_key = NULL
```

Customer preview result:

```text
job_id      = NULL
session_key = <hashed session key>
```

The results table acts as shared generated-output storage while API jobs and customer artwork sessions maintain separate lifecycle state.

## Architecture

The plugin uses a service-oriented, object-oriented architecture.

Main namespaces:

```text
WooPrintMockupTool\Core
WooPrintMockupTool\Admin
WooPrintMockupTool\Frontend
WooPrintMockupTool\Api
WooPrintMockupTool\Assets
WooPrintMockupTool\Database
WooPrintMockupTool\Renderer
WooPrintMockupTool\Services
WooPrintMockupTool\Storage
```

The main plugin class is responsible for bootstrapping service providers rather than containing feature logic.

Core responsibilities are separated into services such as:

```text
ProductConfigRepository
ArtworkUploadService
ArtworkSessionRepository
CustomerSessionService
RenderJobRepository
RenderPipeline
ProductIdentifierResolver
PreviewRateLimiter
WebhookService
WebhookRetryService
CleanupService
```

Rendering is abstracted through:

```text
RendererInterface
    |
    +-- ImagickRenderer
    |
    +-- RemoteRenderer
```

`RemoteRenderer` currently provides the architectural extension point for a future external rendering service.

## Rendering Flows

### Customer Preview

```text
Product Page
    |
    v
CustomerPreviewController
    |
    v
CustomerSessionService
    |
    v
RenderPipeline::run_customer_preview()
    |
    +--> ArtworkSessionRepository
    |
    +--> ProductConfigRepository
    |
    +--> RendererFactory
    |
    v
ImagickRenderer
    |
    v
Session Render Result
```

### External API Job

```text
External System
    |
    v
RenderJobController
    |
    v
ApiKeyAuthenticator
    |
    v
ProductIdentifierResolver
    |
    v
RenderPipeline::run_api_job()
    |
    +--> RenderJobRepository
    |
    +--> ProductConfigRepository
    |
    +--> RendererFactory
    |
    v
ImagickRenderer
    |
    v
Job Render Results
    |
    v
WebhookService
```

## Requirements

* WordPress
* WooCommerce
* PHP 8.0 or newer
* PHP Imagick extension
* Writable WordPress uploads directory
* WordPress REST API

A modern version of Chrome, Firefox, Safari, or Edge is recommended for the admin placement editor.

## Installation

1. Download or clone the repository.
2. Place the plugin directory in:

```text
wp-content/plugins/woo-print-mockup-tool/
```

3. Ensure the PHP Imagick extension is installed and enabled.
4. Activate **Woo Print Mockup Tool** from WordPress Plugins.
5. Open the plugin settings page and configure the renderer and API settings.
6. Edit a WooCommerce product.
7. Open the **Print Mockup** product-data tab.
8. Enable mockup rendering.
9. Select a mockup image.
10. Define the artwork placement area.
11. Select the render mode.
12. Save the product.

The artwork uploader will appear on configured product pages.

## Development Status

Woo Print Mockup Tool is currently under active development.

Implemented core functionality includes:

* WooCommerce product configuration.
* Fabric.js placement editor.
* Rectangle placement.
* Four-point perspective placement.
* Imagick rendering.
* White background removal.
* Engraving mode.
* Customer artwork uploads.
* Persistent guest and logged-in artwork sessions.
* Cross-product artwork reuse.
* Cached customer previews.
* REST nonce protection.
* Preview rate limiting.
* Bearer API authentication.
* Product ID and SKU resolution.
* Remote artwork URL input.
* API job idempotency.
* Webhook delivery.
* Webhook retries.
* Automatic temporary-file cleanup.

The plugin should currently be considered development software and reviewed carefully before use on a production store.

## Planned Improvements

Potential future improvements include:

* SVG artwork support.
* PDF artwork support.
* Advanced print blend modes.
* More realistic engraving simulation.
* Artwork positioning controls for customers.
* Artwork rotation and scaling.
* Multiple placement areas per product.
* Multiple product mockup views.
* WooCommerce cart preview integration.
* Checkout preview integration.
* Order item preview storage.
* Background job queues.
* Remote rendering services.
* Automated tests.
* Additional server diagnostics.
* Expanded API documentation.

## Terminology

The plugin intentionally uses the term **artwork** instead of **logo** internally.

Artwork may represent:

* Logos.
* Graphics.
* Patterns.
* Labels.
* QR codes.
* Stickers.
* Textures.
* Other printable or decoratable visual assets.

This keeps the rendering architecture general-purpose and avoids coupling the engine to logo-only workflows.

## License

License information has not yet been finalized.
