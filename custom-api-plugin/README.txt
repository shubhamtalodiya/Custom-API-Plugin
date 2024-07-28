=== Custom API Plugin ===
Contributors: Shubham Talodiya
Tags: REST API, custom post type, authentication, JSON, mobiles
Requires at least: 5.0
Tested up to: 6.2
Stable tag: 1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin to create custom namespace REST APIs with authentication and manage custom post type "Mobiles".

== Description ==

This plugin provides the following features:
- Custom REST API endpoints for submitting, fetching, and retrieving posts by email.
- Basic Authentication for API endpoints.
- Custom post type "Mobiles" with support for title, editor, author, thumbnail, and custom fields.

== Installation ==

1. Download the plugin files.
2. Upload the files to the `/wp-content/plugins/custom-api-plugin` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= How do I submit data via the API? =

Use the following endpoint to submit data:
- **URL:** `/wp-json/custom/v1/submit`
- **Method:** POST
- **Authentication:** Basic Auth
- **Parameters:**
  [
    {
      "author_email": "author@example.com",   // Required, valid email format
      "title": "Post Title",                  // Required, max 50 characters
      "content": "Post Content",              // Required
      "name": "Author Name",                  // Optional
      "status": "publish",                    // Optional, default is 'publish', allowed values: 'publish', 'draft', 'pending'
      "tags": "tag1,tag2",                    // Optional
      "categories": "category1,category2",    // Optional
      "featured_image": "http://example.com/image.jpg", // Optional, valid URL
      "custom_fields": {
        "field1": "value1",
        "field2": "value2"
      }
    }
  ]
  - **Validations:**
    - `author_email` is required and must be a valid email format.
    - `title` is required, must be unique, and should not exceed 50 characters.
    - `content` is required.
    - `status` is optional but must be one of the following: 'publish', 'draft', 'pending'.
    - `featured_image` URL must be valid if provided.

= How do I fetch data via the API? =

Use the following endpoint to fetch data:
- **URL:** `/wp-json/custom/v1/fetch`
- **Method:** GET
- **Authentication:** Basic Auth
- **Parameters:**
  - `page` (optional, default: 1)
  - `per_page` (optional, default: 10)

= How do I fetch data by email via the API? =

Use the following endpoint to fetch data by email:
- **URL:** `/wp-json/custom/v1/fetch-by-email/{email}`
- **Method:** GET
- **Authentication:** Basic Auth
- **Parameters:**
  - `{email}` (required, valid email format)

== Changelog ==

= 1.0 =
* Initial release of the Custom API Plugin.

== Upgrade Notice ==

= 1.0 =
* Initial release.

== Example Requests ==

**Submit Data:**
Request:
curl -X POST 'https://your-wordpress-site.com/wp-json/custom/v1/submit' \\
--header 'Authorization: Basic YOUR_ENCODED_CREDENTIALS' \\
--header 'Content-Type: application/json' \\
--data-raw '[
  {
    "author_email": "author@example.com",
    "title": "Sample Post",
    "content": "This is a sample post content.",
    "name": "Author Name",
    "status": "publish",
    "tags": "tag1,tag2",
    "categories": "category1,category2",
    "featured_image": "http://example.com/image.jpg",
    "custom_fields": {
      "field1": "value1",
      "field2": "value2"
    }
  }
]'

Response:
[
  {
    "index": 0,
    "post_id": 123,
    "status": "success"
  }
]

**Fetch Data:**
Request:
curl -X GET 'https://your-wordpress-site.com/wp-json/custom/v1/fetch?page=1&per_page=10' \\
--header 'Authorization: Basic YOUR_ENCODED_CREDENTIALS'

Response:
[
  {
    "id": 123,
    "title": "Sample Post",
    "email": "author@example.com",
    "created_at": "2023-01-01 00:00:00",
    "tags": ["tag1", "tag2"],
    "categories": ["category1", "category2"],
    "featured_image": "http://example.com/image.jpg",
    "custom_fields": {
      "field1": "value1",
      "field2": "value2"
    }
  }
]

**Fetch Data by Email:**
Request:
curl -X GET 'https://your-wordpress-site.com/wp-json/custom/v1/fetch-by-email/author@example.com' \\
--header 'Authorization: Basic YOUR_ENCODED_CREDENTIALS'

Response:
[
  {
    "id": 123,
    "title": "Sample Post",
    "email": "author@example.com",
    "created_at": "2023-01-01 00:00:00",
    "tags": ["tag1", "tag2"],
    "categories": ["category1", "category2"],
    "featured_image": "http://example.com/image.jpg",
    "custom_fields": {
      "field1": "value1",
      "field2": "value2"
    }
  }
]
