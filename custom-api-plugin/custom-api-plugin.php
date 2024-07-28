<?php 
/*
Plugin Name: Custom API Plugin
Description: A plugin to create custom namespace REST APIs with authentication.
Version: 1.0
Author: Shubham Talodiya
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Custom_API_Plugin')) {
    class Custom_API_Plugin {
        public function __construct() {
            // Hook into the REST API init action
            add_action('rest_api_init', array($this, 'register_routes'));
            
            // Hook into WordPress init action to create custom post type
            add_action('init', array($this, 'create_custom_post_type'));
        }

        // Register custom API routes
        public function register_routes() {
            register_rest_route('custom/v1', '/submit', array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_form_submission'),
                'permission_callback' => array($this, 'authenticate_request'),
            ));

            register_rest_route('custom/v1', '/fetch', array(
                'methods' => 'GET',
                'callback' => array($this, 'fetch_stored_data'),
                'permission_callback' => array($this, 'authenticate_request'),
            ));

            register_rest_route('custom/v1', '/fetch-by-email/(?P<email>[^/]+)', array(
                'methods' => 'GET',
                'callback' => array($this, 'fetch_data_by_email'),
                'permission_callback' => array($this, 'authenticate_request'),
            ));
        }

        // Authenticate API request using Basic Auth
        public function authenticate_request($request) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $auth = base64_decode(str_replace('Basic ', '', $headers['Authorization']));
                list($user, $pass) = explode(':', $auth);

                $user = wp_authenticate($user, $pass);
                if (!is_wp_error($user)) {
                    return true;
                }
            }
            return new WP_Error('rest_forbidden', esc_html__('You are not authorized to access this resource.'), array('status' => 403));
        }

        // Handle form submission
        public function handle_form_submission($request) {
            // Check if data is submitted as JSON
            $params = $request->get_json_params();
            
            if (empty($params)) {
                // If no JSON data, check for POST data
                $params = $_POST;
            }
        
            // Validate that params is an array
            if (!is_array($params)) {
                return new WP_Error('invalid_data', esc_html__('Data must be an array of posts.'), array('status' => 400));
            }
        
            $results = [];
        
            foreach ($params as $index => $post_data) {
                // Validate required fields
                if (empty($post_data['author_email'])) {
                    $results[] = ['index' => $index, 'error' => 'Author Email is a required field.'];
                    continue;
                }
                if (empty($post_data['title'])) {
                    $results[] = ['index' => $index, 'error' => 'Title is a required field.'];
                    continue;
                }
                if (empty($post_data['content'])) {
                    $results[] = ['index' => $index, 'error' => 'Content is a required field.'];
                    continue;
                }
        
                // Validate email format
                if (!is_email($post_data['author_email'])) {
                    $results[] = ['index' => $index, 'error' => 'Invalid author email format.'];
                    continue;
                }
        
                // Validate title length
                if (!empty($post_data['title']) && strlen($post_data['title']) > 50) {
                    $results[] = ['index' => $index, 'error' => 'Title should not exceed 50 characters.'];
                    continue;
                }

                // Check for unique title
                $existing_post = get_page_by_title($post_data['title'], OBJECT, 'mobiles');
                if ($existing_post) {
                    $results[] = ['index' => $index, 'error' => 'Title already exists.'];
                    continue;
                }
        
                // Validate and set post status
                $post_status = !empty($post_data['status']) ? sanitize_text_field($post_data['status']) : 'publish';
                $allowed_statuses = array('publish', 'draft', 'pending');
                if (!in_array($post_status, $allowed_statuses)) {
                    $results[] = ['index' => $index, 'error' => 'Invalid post status.'];
                    continue;
                }
        
                // Find or create user by email
                $user = get_user_by('email', $post_data['author_email']);
                if (!$user) {
                    // Create new user
                    $user_id = wp_create_user($post_data['author_email'], wp_generate_password(), $post_data['author_email']);
                    if (is_wp_error($user_id)) {
                        $results[] = ['index' => $index, 'error' => 'User could not be created.'];
                        continue;
                    }
                    $user = get_user_by('ID', $user_id);
                }
        
                // Prepare post data
                $post_args = array(
                    'post_title'    => sanitize_text_field($post_data['title']),
                    'post_status'   => $post_status,
                    'post_type'     => 'mobiles',
                    'post_author'   => $user->ID,
                    'post_content'  => sanitize_text_field($post_data['content']),
                );
        
                // Insert the post
                $post_id = wp_insert_post($post_args);
        
                if (is_wp_error($post_id)) {
                    $results[] = ['index' => $index, 'error' => 'Data could not be saved.'];
                    continue;
                }
        
                // Handle tags
                if (!empty($post_data['tags'])) {
                    wp_set_post_tags($post_id, sanitize_text_field($post_data['tags']));
                }
        
                // Handle categories
                if (!empty($post_data['categories'])) {
                    $categories = explode(',', $post_data['categories']);
                    $category_ids = array();
                    foreach ($categories as $category_name) {
                        $category = get_term_by('name', trim($category_name), 'category');
                        if (!$category) {
                            $category = wp_insert_term(trim($category_name), 'category');
                            if (is_wp_error($category)) {
                                continue;
                            }
                            $category_ids[] = $category['term_id'];
                        } else {
                            $category_ids[] = $category->term_id;
                        }
                    }
                    wp_set_post_categories($post_id, $category_ids);
                }
        
                // Handle featured image
                if (!empty($post_data['featured_image'])) {
                    $this->set_featured_image($post_id, esc_url_raw($post_data['featured_image']));
                }
        
                // Handle custom fields
                if (!empty($post_data['custom_fields'])) {
                    foreach ($post_data['custom_fields'] as $key => $value) {
                        update_post_meta($post_id, sanitize_text_field($key), sanitize_text_field($value));
                    }
                }
        
                $results[] = ['index' => $index, 'post_id' => $post_id, 'status' => 'success'];
            }
        
            return new WP_REST_Response($results, 200);
        }
        

        // Fetch stored data with pagination
        public function fetch_stored_data($request) {
            // Get pagination parameters
            $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
            $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 10;
        
            // Prepare query arguments
            $args = array(
                'post_type' => 'mobiles',
                'post_status' => 'any',
                'posts_per_page' => $per_page,
                'paged' => $page,
            );
        
            // Get posts
            $query = new WP_Query($args);
            $posts = $query->posts;
            $total_posts = $query->found_posts;
            $total_pages = $query->max_num_pages;
        
            // Prepare response data
            $data = array();
            foreach ($posts as $post) {
                $data[] = $this->prepare_post_data($post);
            }
        
            // Prepare pagination information
            $response = new WP_REST_Response($data, 200);
            $response->header('X-WP-Total', $total_posts);
            $response->header('X-WP-TotalPages', $total_pages);
        
            return $response;
        }
        
        // Fetch data by email
        public function fetch_data_by_email($request) {
            $email = sanitize_email($request->get_param('email'));
        
            $user = get_user_by('email', $email);
        
            if (!$user) {
                return new WP_Error('not_found', esc_html__('User not found'), array('status' => 404));
            }
        
            $args = array(
                'post_type' => 'mobiles',
                'post_status' => 'any',
                'author' => $user->ID,
                'numberposts' => -1,
            );
        
            $posts = get_posts($args);
        
            if (empty($posts)) {
                return new WP_Error('not_found', esc_html__('No posts found for this user'), array('status' => 404));
            }
        
            $data = [];
            foreach ($posts as $post) {
                $data[] = $this->prepare_post_data($post);
            }

            return new WP_REST_Response($data, 200);
        }

        // Create custom post type 'mobiles'
        public function create_custom_post_type() {
            register_post_type('mobiles', array(
                'labels' => array(
                    'name' => __('Mobiles'),
                    'singular_name' => __('Mobiles'),
                    'menu_name' => __('Mobiles'),
                    'add_new' => __('Add New'),
                    'add_new_item' => __('Add New Mobile'),
                    'edit_item' => __('Edit Mobile'),
                    'new_item' => __('New Mobile'),
                    'view_item' => __('View Mobile'),
                    'search_items' => __('Search Mobile'),
                    'not_found' => __('No Mobile found'),
                    'not_found_in_trash' => __('No Mobile found in Trash')
                ),
                'public' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'hierarchical' => false,
                'menu_position' => 11,
                'menu_icon' => 'dashicons-admin-post',
                'supports' => array('title', 'editor', 'author', 'thumbnail', 'custom-fields'),
                'taxonomies' => array('category', 'post_tag'),
            ));
        }

        // Prepare post data for API response
        private function prepare_post_data($post) {
            $tags = wp_get_post_tags($post->ID, array('fields' => 'names'));
            $categories = wp_get_post_categories($post->ID, array('fields' => 'names'));
            $featured_image = get_the_post_thumbnail_url($post->ID);
            $custom_fields = get_post_meta($post->ID);

            return array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'email' => get_the_author_meta('user_email', $post->post_author),
                'created_at' => $post->post_date,
                'tags' => $tags,
                'categories' => $categories,
                'featured_image' => $featured_image,
                'custom_fields' => $custom_fields,
            );
        }

        // Set featured image for a post
        private function set_featured_image($post_id, $image_url) {
            // Ensure required files are loaded
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        
            // Check if the image URL is valid
            $image_url = esc_url_raw($image_url);
            if (empty($image_url)) {
                return new WP_Error('invalid_image_url', esc_html__('Invalid image URL.'), array('status' => 400));
            }
        
            // Download the image
            $tmp = download_url($image_url);
            if (is_wp_error($tmp)) {
                return new WP_Error('image_download_error', esc_html__('Error downloading image.'), array('status' => 500));
            }
        
            // Upload the image to the media library
            $file_array = array(
                'name' => basename($image_url),
                'tmp_name' => $tmp
            );
        
            $attachment_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($attachment_id)) {
                @unlink($file_array['tmp_name']); // Clean up temporary file
                return new WP_Error('image_upload_error', esc_html__('Error uploading image.'), array('status' => 500));
            }
        
            // Set the featured image
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    // Initialize the plugin
    new Custom_API_Plugin();
}
?>
