<?php
if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('WPTG_Public')) {
    class WPTG_Public
    {
        public function __construct()
        {
        }

        public function wptg_public_enqueue_scripts()
        {
            wp_enqueue_script('wptgpc-public-script', plugin_dir_url(__FILE__) . 'assets/js/wptg-public.js', ['jquery'], '1.0.0', true);
            wp_enqueue_script('comment-reply');
            wp_localize_script('wptgpc-public-script', 'wptgPopupCommentsPublic', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
            ));
        }

        /**
         * templates for comments to be displayed in popup
         * same as of defauld comments_template
         */
        public function wptg_public_comments_template($post_id = null, $file = '/comments.php', $separate_comments = false)
        {
            global $wp_query, $withcomments, $post, $wpdb, $id, $comment, $user_login, $user_identity, $overridden_cpage;

            if (empty($file)) {
                $file = '/comments.php';
            }

            $req = get_option('require_name_email');

            /*
                * Comment author information fetched from the comment cookies.
            */
            $commenter = wp_get_current_commenter();

            /*
                * The name of the current comment author escaped for use in attributes.
                * Escaped by sanitize_comment_cookies().
            */
            $comment_author = $commenter['comment_author'];

            /*
                * The email address of the current comment author escaped for use in attributes.
                * Escaped by sanitize_comment_cookies().
            */
            $comment_author_email = $commenter['comment_author_email'];

            /*
                * The URL of the current comment author escaped for use in attributes.
            */
            $comment_author_url = esc_url($commenter['comment_author_url']);

            $comment_args = array(
                'orderby'                   => 'comment_date_gmt',
                'order'                     => 'ASC',
                'status'                    => 'approve',
                'post_id'                   => $post->ID,
                'no_found_rows'             => false,
                'update_comment_meta_cache' => false, // We lazy-load comment meta for performance.
            );

            if (get_option('thread_comments')) {
                $comment_args['hierarchical'] = 'threaded';
            } else {
                $comment_args['hierarchical'] = false;
            }

            if (is_user_logged_in()) {
                $comment_args['include_unapproved'] = array(get_current_user_id());
            } else {
                $unapproved_email = wp_get_unapproved_comment_author_email();

                if ($unapproved_email) {
                    $comment_args['include_unapproved'] = array($unapproved_email);
                }
            }

            $per_page = 0;
            if (get_option('page_comments')) {
                $per_page = (int) get_query_var('comments_per_page');
                if (0 === $per_page) {
                    $per_page = (int) get_option('comments_per_page');
                }

                $comment_args['number'] = $per_page;
                $page                   = (int) get_query_var('cpage');

                if ($page) {
                    $comment_args['offset'] = ($page - 1) * $per_page;
                } elseif ('oldest' === get_option('default_comments_page')) {
                    $comment_args['offset'] = 0;
                } else {
                    // If fetching the first page of 'newest', we need a top-level comment count.
                    $top_level_query = new WP_Comment_Query();
                    $top_level_args  = array(
                        'count'   => true,
                        'orderby' => false,
                        'post_id' => $post->ID,
                        'status'  => 'approve',
                    );

                    if ($comment_args['hierarchical']) {
                        $top_level_args['parent'] = 0;
                    }

                    if (isset($comment_args['include_unapproved'])) {
                        $top_level_args['include_unapproved'] = $comment_args['include_unapproved'];
                    }

                    /**
                     * Filters the arguments used in the top level comments query.
                     *
                     * @since 5.6.0
                     *
                     * @see WP_Comment_Query::__construct()
                     *
                     * @param array $top_level_args {
                     *     The top level query arguments for the comments template.
                     *
                     *     @type bool         $count   Whether to return a comment count.
                     *     @type string|array $orderby The field(s) to order by.
                     *     @type int          $post_id The post ID.
                     *     @type string|array $status  The comment status to limit results by.
                     * }
                     */
                    $top_level_args = apply_filters('comments_template_top_level_query_args', $top_level_args);

                    $top_level_count = $top_level_query->query($top_level_args);

                    $comment_args['offset'] = (ceil($top_level_count / $per_page) - 1) * $per_page;
                }
            }

            /**
             * Filters the arguments used to query comments in comments_template().
             *
             * @since 4.5.0
             *
             * @see WP_Comment_Query::__construct()
             *
             * @param array $comment_args {
             *     Array of WP_Comment_Query arguments.
             *
             *     @type string|array $orderby                   Field(s) to order by.
             *     @type string       $order                     Order of results. Accepts 'ASC' or 'DESC'.
             *     @type string       $status                    Comment status.
             *     @type array        $include_unapproved        Array of IDs or email addresses whose unapproved comments
             *                                                   will be included in results.
             *     @type int          $post_id                   ID of the post.
             *     @type bool         $no_found_rows             Whether to refrain from querying for found rows.
             *     @type bool         $update_comment_meta_cache Whether to prime cache for comment meta.
             *     @type bool|string  $hierarchical              Whether to query for comments hierarchically.
             *     @type int          $offset                    Comment offset.
             *     @type int          $number                    Number of comments to fetch.
             * }
             */
            $comment_args = apply_filters('comments_template_query_args', $comment_args);

            $comment_query = new WP_Comment_Query($comment_args);
            $_comments     = $comment_query->comments;

            // Trees must be flattened before they're passed to the walker.
            if ($comment_args['hierarchical']) {
                $comments_flat = array();
                foreach ($_comments as $_comment) {
                    $comments_flat[]  = $_comment;
                    $comment_children = $_comment->get_children(
                        array(
                            'format'  => 'flat',
                            'status'  => $comment_args['status'],
                            'orderby' => $comment_args['orderby'],
                        )
                    );

                    foreach ($comment_children as $comment_child) {
                        $comments_flat[] = $comment_child;
                    }
                }
            } else {
                $comments_flat = $_comments;
            }

            /**
             * Filters the comments array.
             *
             * @since 2.1.0
             *
             * @param array $comments Array of comments supplied to the comments template.
             * @param int   $post_ID  Post ID.
             */
            $wp_query->comments = apply_filters('comments_array', $comments_flat, $post->ID);

            $comments                        = &$wp_query->comments;
            $wp_query->comment_count         = count($wp_query->comments);
            $wp_query->max_num_comment_pages = $comment_query->max_num_pages;

            if ($separate_comments) {
                $wp_query->comments_by_type = separate_comments($comments);
                $comments_by_type           = &$wp_query->comments_by_type;
            } else {
                $wp_query->comments_by_type = array();
            }

            $overridden_cpage = false;

            if ('' == get_query_var('cpage') && $wp_query->max_num_comment_pages > 1) {
                set_query_var('cpage', 'newest' === get_option('default_comments_page') ? get_comment_pages_count() : 1);
                $overridden_cpage = true;
            }

            if (!defined('COMMENTS_TEMPLATE')) {
                define('COMMENTS_TEMPLATE', true);
            }

            $theme_template = STYLESHEETPATH . $file;

            /**
             * Filters the path to the theme template file used for the comments template.
             *
             * @since 1.5.1
             *
             * @param string $theme_template The path to the theme template file.
             */
            $include = apply_filters('comments_template', $theme_template);

            if (file_exists($include)) {
                require $include;
            } elseif (file_exists(TEMPLATEPATH . $file)) {
                require TEMPLATEPATH . $file;
            } else { // Backward compat code will be removed in a future release.
                require ABSPATH . WPINC . '/theme-compat/comments.php';
            }
        }

        public function wptg_wptg_comment_wrapper()
        {
            // echo 'here';
            global $post;
            // print_r($post);

            $pid  = intval(sanitize_text_field($_POST['pid']));
            $post = get_post($pid, OBJECT);
            setup_postdata($post);

            //Do something


            $this->wptg_public_comments_template(get_the_ID());
            wp_reset_postdata();

            die;
        }

        /**
         * AJAX comment submit
         */
        public function wptg_public_submit_ajax_comment()
        {
            $comment = wp_handle_comment_submission(wp_unslash($_POST));
            if (is_wp_error($comment)) {
                $error_data = intval($comment->get_error_data());
                if (!empty($error_data)) {
                    wp_die(
                        '<p>' . $comment->get_error_message() . '</p>',
                        __('Comment Submission Failure'),
                        array(
                            'response'  => $error_data,
                            'back_link' => true,
                        )
                    );
                } else {
                    wp_die('Unknown error');
                }
            }

            /*
             * Set Cookies
             */
            $user = wp_get_current_user();
            do_action('set_comment_cookies', $comment, $user);

            /*
             * If you do not like this loop, pass the comment depth from JavaScript code
             */
            $comment_depth  = 1;
            $comment_parent = $comment->comment_parent;
            while ($comment_parent) {
                $comment_depth++;
                $parent_comment = get_comment($comment_parent);
                $comment_parent = $parent_comment->comment_parent;
            }

            /*
              * Set the globals, so our comment functions below will work correctly
              */
            $GLOBALS['comment']       = $comment;
            $GLOBALS['comment_depth'] = $comment_depth;

            /*
             * Here is the comment template, you can configure it for your website
             * or you can try to find a ready function in your theme files
             */
            $comment_html = '<li ' . comment_class('', null, null, false) . ' id="comment-' . get_comment_ID() . '">
            <article class="comment-body" id="div-comment-' . get_comment_ID() . '">
                <footer class="comment-meta">
                    <div class="comment-author vcard">
                        ' . get_avatar($comment, 100) . '
                        <b class="fn">' . get_comment_author_link() . '</b> <span class="says">says:</span>
                    </div>
                    <div class="comment-metadata">
                        <a href="' . esc_url(get_comment_link($comment->comment_ID)) . '">' . sprintf('%1$s at %2$s', get_comment_date(), get_comment_time()) . '</a>';

            if ($edit_link = get_edit_comment_link()) {
                $comment_html .= '<span class="edit-link"><a class="comment-edit-link" href="' . $edit_link . '">Edit</a></span>';
            }

            $comment_html .= '</div>';
            if ($comment->comment_approved == '0') {
                $comment_html .= '<p class="comment-awaiting-moderation">Your comment is awaiting moderation.</p>';
            }

            $comment_html .= '</footer>
                        <div class="comment-content">' . apply_filters('comment_text', get_comment_text($comment), $comment) . '</div>
                    </article>
                </li>';
            echo $comment_html;

            die();
        }

        /**
         * popup wrapper for comments
         */
        public function wptg_footer_popup_wrap()
        {
            echo '<div class="wptg_popup_wrap">
                <a class="close_popup"> X </a>
                <div class="wptg_comments_html"></div>
            </div>';
        }

        public function wptg_popup_shortcode($atts)
        {
            global $post;
            $atts = shortcode_atts(
                array(
                    'post_id' => $post->post_id,
                    'class'   => '',
                    'label'   => __('Show', 'wptg-popup-comments')
                ),
                $atts
            );
            ob_start();
?>

            <a href="#" class="wptg_show_comments <?php echo esc_attr($atts['class']); ?>" data-id="<?php echo esc_attr($atts['post_id']); ?>"><?php echo esc_html($atts['label']); ?></a>
<?php return ob_get_clean();
        }
    }
}
