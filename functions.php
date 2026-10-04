<?php
/**
 * Manga Theme Functions
 * Version: 6.2
 */

// Theme Setup
function manga_theme_setup() {
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
    
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'manga-theme'),
        'footer' => __('Footer Menu', 'manga-theme'),
    ));
    
    add_image_size('manga-cover', 300, 420, true);
    add_image_size('manga-cover-small', 180, 252, true);
}
add_action('after_setup_theme', 'manga_theme_setup');

// Register Manga Post Type
function register_manga_post_type() {
    $labels = array(
        'name'               => __('Manga', 'manga-theme'),
        'singular_name'      => __('Manga', 'manga-theme'),
        'menu_name'          => __('Manga', 'manga-theme'),
        'add_new'            => __('Add New Manga', 'manga-theme'),
        'add_new_item'       => __('Add New Manga', 'manga-theme'),
        'edit_item'          => __('Edit Manga', 'manga-theme'),
        'new_item'           => __('New Manga', 'manga-theme'),
        'view_item'          => __('View Manga', 'manga-theme'),
        'search_items'       => __('Search Manga', 'manga-theme'),
        'not_found'          => __('No manga found', 'manga-theme'),
        'not_found_in_trash' => __('No manga found in trash', 'manga-theme'),
    );
    
    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'manga', 'with_front' => false),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-book',
        'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
        'show_in_rest'        => true,
    );
    
    register_post_type('manga', $args);
}
add_action('init', 'register_manga_post_type');

// Register Chapter Post Type
function register_chapter_post_type() {
    $labels = array(
        'name'               => __('Chapters', 'manga-theme'),
        'singular_name'      => __('Chapter', 'manga-theme'),
        'menu_name'          => __('Chapters', 'manga-theme'),
        'add_new'            => __('Add New Chapter', 'manga-theme'),
        'add_new_item'       => __('Add New Chapter', 'manga-theme'),
        'edit_item'          => __('Edit Chapter', 'manga-theme'),
        'new_item'           => __('New Chapter', 'manga-theme'),
        'view_item'          => __('View Chapter', 'manga-theme'),
        'search_items'       => __('Search Chapters', 'manga-theme'),
        'not_found'          => __('No chapters found', 'manga-theme'),
        'not_found_in_trash' => __('No chapters found in trash', 'manga-theme'),
        'all_items'          => __('All Chapters', 'manga-theme'),
    );
    
    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'chapter', 'with_front' => false),
        'capability_type'     => 'post',
        'has_archive'         => false,
        'hierarchical'        => false,
        'menu_position'       => 6,
        'menu_icon'           => 'dashicons-media-text',
        'supports'            => array('title', 'editor'),
        'show_in_rest'        => true,
    );
    
    register_post_type('chapter', $args);
}
add_action('init', 'register_chapter_post_type');

// ============================================
// HELPER FUNCTIONS FOR EXTRACTING VOLUME & CHAPTER NUMBERS
// ============================================

// Extract volume number from title
function extract_volume_number($title) {
    $chapter_info = manga_parse_chapter_title($title);
    return $chapter_info['volume'];
}

// Extract chapter number from title
function extract_chapter_number($title) {
    $chapter_info = manga_parse_chapter_title($title);
    return $chapter_info['chapter'];
}

/** Parse supported chapter and volume naming styles from imported folder names. */
function manga_normalize_chapter_text($value) {
    $value = trim((string) $value);
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);
        if (is_string($normalized)) {
            $value = $normalized;
        }
    }

    $digit_sets = array(
        '٠١٢٣٤٥٦٧٨٩', // Arabic-Indic
        '۰۱۲۳۴۵۶۷۸۹', // Eastern Arabic/Persian
        '０１２３４５６７８９', // Full-width
        '०१२३४५६७८९', // Devanagari
        '০১২৩৪৫৬৭৮৯', // Bengali
        '๐๑๒๓๔๕๖๗๘๙', // Thai
    );
    $ascii_digits = str_split('0123456789');
    foreach ($digit_sets as $digit_set) {
        $digits = preg_split('//u', $digit_set, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($digits) && count($digits) === 10) {
            $value = strtr($value, array_combine($digits, $ascii_digits));
        }
    }

    return trim(strtr($value, array(
        '−' => '-',
        '–' => '-',
        '—' => '-',
        '－' => '-',
        '．' => '.',
        '＿' => '_',
        "\u{200B}" => '',
        "\u{FEFF}" => '',
    )));
}

/** Return chapter/volume candidates with confidence and evidence for safe import decisions. */
function manga_parse_chapter_title($title) {
    $title = manga_normalize_chapter_text($title);
    $volume = 0;
    $chapter = 0;
    $confidence = 'none';
    $source = '';
    $ambiguous = false;

    if (preg_match('/(?:^|[\s\[(])(?:Vol(?:ume)?|Tome|Band|V|巻|卷)[._\s-]*(\d+(?:\.\d+)?)/iu', $title, $matches)) {
        $volume = (float) $matches[1];
    }

    // Language labels are hints, not requirements: the numeric-position rules below are language-neutral.
    $chapter_markers = 'Ch(?:apter)?|Ep(?:isode)?|Cap[ií]tulo|Cap(?:itolo)?|Chap(?:itre)?|Chương|Chuong|Bab|Hoofdstuk|Kapitel|Глава|Гл\\.?|فصل|अध्याय|অধ্যায়|ตอน(?:ที่)?|第|제|No\\.?|N°|№';
    if (preg_match('/(?:^|[\s\[(])(?:' . $chapter_markers . ')[\s._:#-]*(\d+(?:\.\d+)?)/iu', $title, $matches)) {
        $chapter = (float) $matches[1];
        $confidence = 'high';
        $source = 'chapter_marker';
    } elseif (preg_match('/(\d+(?:\.\d+)?)\s*(?:화|話|章|回)(?=$|[\s._-]|\(|\[)/u', $title, $matches)) {
        $chapter = (float) $matches[1];
        $confidence = 'high';
        $source = 'chapter_suffix_marker';
    } elseif (preg_match('/^(?:Vol(?:ume)?|Tome|Band|V|巻|卷)[._\s-]*(\d+(?:\.\d+)?)[\s._-]+(?:Ch(?:apter)?[\s._-]*)?(\d+(?:\.\d+)?)$/iu', $title, $matches)) {
        $volume = (float) $matches[1];
        $chapter = (float) $matches[2];
        $confidence = 'high';
        $source = 'volume_and_chapter_numbers';
    } elseif (preg_match('/^\s*(?:\[(?=[^\]]{1,80}\])(?=[^\]]*[^\d])[^\]]+\]\s*)*[\[(]?\s*(\d+(?:\.\d+)?)(?!\s*[-_]\s*\d)\s*[\])]?(?=$|[\s._-])/u', $title, $matches)) {
        $chapter = (float) $matches[1];
        $confidence = 'high';
        $source = 'leading_number';
    } elseif (preg_match('/^\s*(\d+(?:\.\d+)?)\s*[-_]\s*(\d+(?:\.\d+)?)\s*$/u', $title, $matches)) {
        if ((float) $matches[1] === (float) $matches[2]) {
            $chapter = (float) $matches[1];
            $confidence = 'high';
            $source = 'repeated_chapter_number';
        } else {
            $ambiguous = true;
            $source = 'conflicting_number_pair';
        }
    } elseif ($volume > 0 && preg_match('/^(?:Vol(?:ume)?|Tome|Band|V|巻|卷)[._\s-]*\d+(?:\.\d+)?$/iu', $title)) {
        $source = 'volume_only';
    } else {
        $without_release_tags = preg_replace('/^\s*(?:\[[^\]]{1,80}\]\s*)+/', '', $title);
        preg_match_all('/\d+(?:\.\d+)?/u', (string) $without_release_tags, $numeric_tokens);
        if (count($numeric_tokens[0] ?? array()) === 1) {
            $chapter = (float) $numeric_tokens[0][0];
            $confidence = preg_match('/(?:^|[\s._-])[\[(]?\s*' . preg_quote($numeric_tokens[0][0], '/') . '\s*[\])]?\s*$/u', (string) $without_release_tags)
                ? 'medium'
                : 'low';
            $source = $confidence === 'medium' ? 'unique_trailing_number' : 'unique_unlabeled_number';
        } elseif (!empty($numeric_tokens[0])) {
            $ambiguous = true;
            $source = 'multiple_unlabeled_numbers';
        }
    }

    return array(
        'volume' => $volume,
        'chapter' => $chapter,
        'confidence' => $confidence,
        'source' => $source,
        'ambiguous' => $ambiguous,
    );
}

/** Infer a chapter from a consistent image filename batch; never trust one filename alone. */
function manga_infer_chapter_from_filenames($files) {
    $files = array_values((array) $files);
    if (count($files) < 2) {
        return array('chapter' => 0, 'confidence' => 'none', 'source' => '', 'ambiguous' => false);
    }

    $candidates = array();
    foreach ($files as $file) {
        $stem = pathinfo(basename((string) $file), PATHINFO_FILENAME);
        $parsed = manga_parse_chapter_title($stem);
        if ($parsed['chapter'] > 0 && $parsed['confidence'] === 'high') {
            $candidate = array('number' => $parsed['chapter'], 'confidence' => 'high', 'source' => 'filename_marker');
        } else {
            // Typical scan names end in both chapter and page numbers: remove only the page token, then parse again.
            $normalized_stem = manga_normalize_chapter_text($stem);
            $without_page = preg_replace('/(?:^|[\s._-])\d{1,5}\s*$/u', '', $normalized_stem);
            $parsed_without_page = $without_page !== $normalized_stem ? manga_parse_chapter_title($without_page) : array('chapter' => 0);
            if (!empty($parsed_without_page['chapter'])) {
                $candidate = array('number' => $parsed_without_page['chapter'], 'confidence' => ($parsed_without_page['confidence'] ?? 'low') === 'low' ? 'low' : 'medium', 'source' => 'filename_batch_pattern');
            } elseif ($parsed['chapter'] > 0) {
                $candidate = array('number' => $parsed['chapter'], 'confidence' => $parsed['confidence'], 'source' => 'filename_trailing_number');
            } else {
                continue;
            }
        }

        $key = sprintf('%.6F', (float) $candidate['number']);
        if (!isset($candidates[$key])) {
            $candidates[$key] = array('number' => (float) $candidate['number'], 'count' => 0, 'high_count' => 0, 'medium_count' => 0, 'source' => $candidate['source']);
        }
        $candidates[$key]['count']++;
        if ($candidate['confidence'] === 'high') {
            $candidates[$key]['high_count']++;
        } elseif ($candidate['confidence'] === 'medium') {
            $candidates[$key]['medium_count']++;
        }
    }

    if (!$candidates) {
        return array('chapter' => 0, 'confidence' => 'none', 'source' => '', 'ambiguous' => false);
    }
    uasort($candidates, function($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    $best = reset($candidates);
    $required = max(2, (int) ceil(count($files) * 0.9));
    if (!$best || $best['count'] < $required) {
        return array('chapter' => 0, 'confidence' => 'low', 'source' => 'inconsistent_filenames', 'ambiguous' => true);
    }

    return array(
        'chapter' => $best['number'],
        'confidence' => $best['high_count'] === $best['count'] ? 'high' : ($best['medium_count'] + $best['high_count'] === $best['count'] ? 'medium' : 'low'),
        'source' => $best['source'],
        'ambiguous' => false,
    );
}

function manga_chapter_numbers_for_post($chapter_id) {
    $parsed = manga_parse_chapter_title(get_the_title($chapter_id));
    $chapter = (float) get_post_meta($chapter_id, 'chapter_number', true);
    $volume = (float) get_post_meta($chapter_id, 'volume_number', true);
    return array(
        'chapter' => $chapter > 0 ? $chapter : $parsed['chapter'],
        'volume' => $volume > 0 ? $volume : $parsed['volume'],
    );
}

function manga_chapter_display_label($chapter_id) {
    $numbers = manga_chapter_numbers_for_post($chapter_id);
    if ($numbers['volume'] > 0 && $numbers['chapter'] <= 0) {
        return 'Vol. ' . $numbers['volume'];
    }
    if ($numbers['volume'] > 0) {
        return 'Vol. ' . $numbers['volume'] . ' Ch. ' . $numbers['chapter'];
    }
    return 'Ch. ' . $numbers['chapter'];
}

// Get combined sort key (volume * 1000 + chapter for proper ordering)
function get_chapter_sort_key($chapter_id, $title) {
    $volume = extract_volume_number($title);
    $chapter = extract_chapter_number($title);
    
    // If no volume found, just use chapter number
    if ($volume == 0) {
        return $chapter;
    }
    
    // Combine volume and chapter: Vol. 3 Ch. 12 = 3012, Vol. 2 Ch. 10 = 2010
    return ($volume * 1000) + $chapter;
}

// Format chapter display name
function format_chapter_display_name($title) {
    $chapter_info = manga_parse_chapter_title($title);
    $volume = $chapter_info['volume'];
    $chapter = $chapter_info['chapter'];
    
    if ($volume > 0 && $chapter <= 0) {
        return "Vol. {$volume}";
    }
    if ($volume > 0) {
        return "Vol. {$volume} Ch. {$chapter}";
    }
    
    return "Ch. {$chapter}";
}

// Register Genre Taxonomy
function register_genre_taxonomy() {
    $labels = array(
        'name'              => __('Genres', 'manga-theme'),
        'singular_name'     => __('Genre', 'manga-theme'),
        'search_items'      => __('Search Genres', 'manga-theme'),
        'all_items'         => __('All Genres', 'manga-theme'),
        'edit_item'         => __('Edit Genre', 'manga-theme'),
        'update_item'       => __('Update Genre', 'manga-theme'),
        'add_new_item'      => __('Add New Genre', 'manga-theme'),
        'new_item_name'     => __('New Genre Name', 'manga-theme'),
        'menu_name'         => __('Genres', 'manga-theme'),
    );
    
    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'genre'),
        'show_in_rest'      => true,
    );
    
    register_taxonomy('genre', array('manga'), $args);
}
add_action('init', 'register_genre_taxonomy');

// Register Status Taxonomy
function register_status_taxonomy() {
    $labels = array(
        'name'              => __('Status', 'manga-theme'),
        'singular_name'     => __('Status', 'manga-theme'),
        'search_items'      => __('Search Status', 'manga-theme'),
        'all_items'         => __('All Statuses', 'manga-theme'),
        'edit_item'         => __('Edit Status', 'manga-theme'),
        'update_item'       => __('Update Status', 'manga-theme'),
        'add_new_item'      => __('Add New Status', 'manga-theme'),
        'new_item_name'     => __('New Status Name', 'manga-theme'),
        'menu_name'         => __('Status', 'manga-theme'),
    );
    
    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'status'),
        'show_in_rest'      => true,
    );
    
    register_taxonomy('manga_status', array('manga'), $args);
    
    $default_statuses = array('Ongoing', 'Completed', 'Hiatus', 'Cancelled');
    foreach ($default_statuses as $status) {
        if (!term_exists($status, 'manga_status')) {
            wp_insert_term($status, 'manga_status');
        }
    }
}
add_action('init', 'register_status_taxonomy');

// Manga Details Meta Box
function add_manga_details_meta_box() {
    add_meta_box(
        'manga_details',
        __('Manga Details', 'manga-theme'),
        'render_manga_details_meta_box',
        'manga',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_manga_details_meta_box');

function render_manga_details_meta_box($post) {
    wp_nonce_field('manga_details_save', 'manga_details_nonce');
    
    $author = get_post_meta($post->ID, '_manga_author', true);
    $artist = get_post_meta($post->ID, '_manga_artist', true);
    $year = get_post_meta($post->ID, '_manga_year', true);
    ?>
    <div style="margin-bottom: 15px;">
        <label style="display: inline-block; width: 150px; font-weight: 600;">Author:</label>
        <input type="text" name="manga_author" value="<?php echo esc_attr($author); ?>" style="width: 300px; padding: 5px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: inline-block; width: 150px; font-weight: 600;">Artist:</label>
        <input type="text" name="manga_artist" value="<?php echo esc_attr($artist); ?>" style="width: 300px; padding: 5px;">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: inline-block; width: 150px; font-weight: 600;">Year:</label>
        <input type="number" name="manga_year" value="<?php echo esc_attr($year); ?>" style="width: 300px; padding: 5px;">
    </div>
    <?php
}

function save_manga_details_meta_box($post_id) {
    if (!isset($_POST['manga_details_nonce']) || !wp_verify_nonce($_POST['manga_details_nonce'], 'manga_details_save')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['manga_author'])) {
        update_post_meta($post_id, '_manga_author', sanitize_text_field($_POST['manga_author']));
    }
    if (isset($_POST['manga_artist'])) {
        update_post_meta($post_id, '_manga_artist', sanitize_text_field($_POST['manga_artist']));
    }
    if (isset($_POST['manga_year'])) {
        update_post_meta($post_id, '_manga_year', sanitize_text_field($_POST['manga_year']));
    }
}
add_action('save_post_manga', 'save_manga_details_meta_box');

// Chapter Connection Meta Box
function add_chapter_connection_meta_box() {
    add_meta_box(
        'chapter_connection',
        __('Connect to Manga', 'manga-theme'),
        'render_chapter_connection_meta_box',
        'chapter',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'add_chapter_connection_meta_box');

function render_chapter_connection_meta_box($post) {
    wp_nonce_field('chapter_connection_save', 'chapter_connection_nonce');
    
    $connected_manga = get_post_meta($post->ID, 'connected_manga_id', true);
    $chapter_number = get_post_meta($post->ID, 'chapter_number', true);
    $volume_number = get_post_meta($post->ID, 'volume_number', true);
    
    $manga_list = get_posts(array(
        'post_type' => 'manga',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    ?>
    <div style="margin-bottom: 15px;">
        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Select Manga:</label>
        <select name="connected_manga" style="width: 100%; padding: 8px;">
            <option value="">-- Select Manga --</option>
            <?php foreach ($manga_list as $manga): ?>
                <option value="<?php echo $manga->ID; ?>" <?php selected($connected_manga, $manga->ID); ?>>
                    <?php echo esc_html($manga->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Volume Number:</label>
        <input type="number" name="volume_number" value="<?php echo esc_attr($volume_number); ?>" step="1" style="width: 100%; padding: 8px;" placeholder="Leave empty if no volume">
    </div>
    <div style="margin-bottom: 15px;">
        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Chapter Number:</label>
        <input type="number" name="chapter_number" value="<?php echo esc_attr($chapter_number); ?>" step="0.1" style="width: 100%; padding: 8px;">
    </div>
    <?php
}

function save_chapter_connection_meta_box($post_id) {
    if (!isset($_POST['chapter_connection_nonce']) || !wp_verify_nonce($_POST['chapter_connection_nonce'], 'chapter_connection_save')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    $manga_id = isset($_POST['connected_manga']) ? absint($_POST['connected_manga']) : 0;
    $volume_num = isset($_POST['volume_number']) ? (float) $_POST['volume_number'] : 0;
    $chapter_num = isset($_POST['chapter_number']) ? (float) $_POST['chapter_number'] : 0;

    if ($manga_id && get_post_type($manga_id) === 'manga') {
        update_post_meta($post_id, 'connected_manga_id', $manga_id);
    } else {
        delete_post_meta($post_id, 'connected_manga_id');
    }

    if ($volume_num > 0) {
        update_post_meta($post_id, 'volume_number', $volume_num);
    } else {
        delete_post_meta($post_id, 'volume_number');
    }

    if ($chapter_num > 0) {
        update_post_meta($post_id, 'chapter_number', $chapter_num);
    } else {
        delete_post_meta($post_id, 'chapter_number');
    }

    if ($manga_id && get_post_type($manga_id) === 'manga' && $chapter_num > 0) {
        $manga_title = get_the_title($manga_id);
        $new_title = $volume_num > 0
            ? sprintf('%s - Vol. %s Ch. %s', $manga_title, $volume_num, $chapter_num)
            : sprintf('%s - Chapter %s', $manga_title, $chapter_num);

        remove_action('save_post_chapter', 'save_chapter_connection_meta_box');
        wp_update_post(array('ID' => $post_id, 'post_title' => $new_title));
        add_action('save_post_chapter', 'save_chapter_connection_meta_box');
    }
}
add_action('save_post_chapter', 'save_chapter_connection_meta_box');

// Chapter Images Meta Box
function add_chapter_images_meta_box() {
    add_meta_box(
        'chapter_images',
        __('Chapter Images', 'manga-theme'),
        'render_chapter_images_meta_box',
        'chapter',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_chapter_images_meta_box');

function render_chapter_images_meta_box($post) {
    wp_nonce_field('chapter_images_save', 'chapter_images_nonce');
    
    $image_links = get_post_meta($post->ID, 'image_links', true);
    $image_attachment_ids = get_post_meta($post->ID, 'image_attachment_ids', true);
    $image_attachment_ids = is_array($image_attachment_ids) ? array_map('absint', $image_attachment_ids) : array();
    wp_enqueue_media();
    ?>
    <style>
        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            background: #fafafa;
            border-radius: 5px;
        }
        .image-preview-item {
            position: relative;
            width: 120px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
            background: white;
        }
        .image-preview-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        .image-preview-item .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220,53,69,0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            cursor: pointer;
        }
        .image-urls-textarea {
            width: 100%;
            min-height: 200px;
            font-family: monospace;
            font-size: 12px;
            margin-top: 10px;
        }
    </style>
    
    <div>
        <p><strong>Option 1: Upload Images</strong></p>
        <button type="button" id="upload-images-btn" class="button button-primary">Select Images</button>
        <input type="file" id="image-files" multiple accept="image/*" style="display:none">
        <div id="image-preview" class="image-preview-container">
            <?php 
            if (!empty($image_links)) {
                $images = explode("\n", $image_links);
                foreach ($images as $image_url) {
                    $image_url = trim($image_url);
                    if (!empty($image_url)) {
                        $attachment_id = attachment_url_to_postid($image_url);
                        echo '<div class="image-preview-item" data-id="' . esc_attr($attachment_id) . '" data-url="' . esc_attr($image_url) . '">';
                        echo '<img src="' . esc_url($image_url) . '" loading="lazy" decoding="async">';
                        echo '<button type="button" class="remove-image" onclick="removeImage(this)">×</button>';
                        echo '</div>';
                    }
                }
            }
            ?>
        </div>
        
        <p><strong>Option 2: Enter Image URLs</strong></p>
        <textarea id="image-urls" class="image-urls-textarea" placeholder="Enter one image URL per line"><?php echo esc_textarea($image_links); ?></textarea>
        
        <input type="hidden" id="final-image-links" name="final_image_links" value="<?php echo esc_attr($image_links); ?>">
        <input type="hidden" id="final-image-attachment-ids" name="final_image_attachment_ids" value="<?php echo esc_attr(implode(',', $image_attachment_ids)); ?>">
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#upload-images-btn').on('click', function() {
            $('#image-files').trigger('click');
        });
        
        $('#image-files').on('change', function(e) {
            var files = Array.prototype.slice.call(e.target.files || []);
            var batchSize = 8;
            var uploadBatch = function(offset) {
                if (offset >= files.length) {
                    updateImageLinksField();
                    return;
                }
                var formData = new FormData();
                formData.append('action', 'upload_chapter_images');
                formData.append('nonce', '<?php echo wp_create_nonce("chapter_upload_nonce"); ?>');
                formData.append('post_id', '<?php echo absint($post->ID); ?>');
                files.slice(offset, offset + batchSize).forEach(function(file) {
                    formData.append('files[]', file);
                });
                $.ajax({url: ajaxurl, type: 'POST', data: formData, processData: false, contentType: false})
                    .done(function(response) {
                        if (response.success) {
                            $.each(response.data.images || [], function(index, image) {
                                addImageToPreview(image.url, image.id);
                            });
                            if (response.data.failed && response.data.failed.length) {
                                alert('Some files could not be uploaded: ' + response.data.failed.join(', '));
                            }
                            uploadBatch(offset + batchSize);
                        } else {
                            var message = response.data && response.data.message ? response.data.message : 'Unknown error';
                            alert('Upload failed: ' + message);
                            updateImageLinksField();
                        }
                    }).fail(function() {
                        alert('Upload request failed. Please retry the remaining images.');
                        updateImageLinksField();
                    });
            };
            uploadBatch(0);
            $(this).val('');
        });
        
        window.addImageToPreview = function(imageUrl, imageId) {
            var previewHtml = '<div class="image-preview-item" data-id="' + (imageId || '') + '" data-url="' + $('<div>').text(imageUrl).html() + '">' +
                '<img src="' + imageUrl + '" loading="lazy" decoding="async">' +
                '<button type="button" class="remove-image" onclick="removeImage(this)">×</button>' +
                '</div>';
            $('#image-preview').append(previewHtml);
        };
        
        window.removeImage = function(btn) {
            $(btn).closest('.image-preview-item').remove();
            updateImageLinksField();
        };
        
        function updateImageLinksField() {
            var imageUrls = [];
            var imageIds = [];
            $('#image-preview .image-preview-item').each(function() {
                var url = $(this).data('url');
                var id = parseInt($(this).data('id'), 10);
                if (url) {
                    imageUrls.push(url);
                }
                if (id) {
                    imageIds.push(id);
                }
            });
            $('#image-urls').val(imageUrls.join('\n'));
            $('#final-image-links').val(imageUrls.join('\n'));
            $('#final-image-attachment-ids').val(imageIds.join(','));
        }
        
        $('#image-urls').on('change keyup', function() {
            $('#final-image-links').val($(this).val());
        });
        
        $('#publish, #save-post').on('click', function() {
            $('#final-image-links').val($('#image-urls').val());
        });
    });
    </script>
    <?php
}

function save_chapter_images_meta_box($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($post_id)) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)
        || !isset($_POST['chapter_images_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['chapter_images_nonce'])), 'chapter_images_save')) {
        return;
    }

    if (isset($_POST['final_image_links'])) {
        update_post_meta($post_id, 'image_links', sanitize_textarea_field(wp_unslash($_POST['final_image_links'])));
        $ids = isset($_POST['final_image_attachment_ids'])
            ? array_filter(array_map('absint', explode(',', sanitize_text_field(wp_unslash($_POST['final_image_attachment_ids'])))))
            : array();
        update_post_meta($post_id, 'image_attachment_ids', array_values(array_unique($ids)));
    }
}
add_action('save_post_chapter', 'save_chapter_images_meta_box');

// AJAX Upload Handler
function ajax_upload_chapter_images() {
    check_ajax_referer('chapter_upload_nonce', 'nonce');
    
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!current_user_can('upload_files') || ($post_id && !current_user_can('edit_post', $post_id))) {
        wp_send_json_error('Permission denied');
    }

    if (empty($_FILES['files']) || empty($_FILES['files']['tmp_name']) || !is_array($_FILES['files']['tmp_name'])) {
        wp_send_json_error('No files received');
    }
    
    $uploaded_images = array();
    
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    $failed_files = array();
    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
        if (!isset($_FILES['files']['error'][$key]) || (int) $_FILES['files']['error'][$key] !== UPLOAD_ERR_OK) {
            $failed_files[] = sanitize_file_name($_FILES['files']['name'][$key] ?? 'unknown');
            continue;
        }

        if (!is_uploaded_file($tmp_name)) {
            $failed_files[] = sanitize_file_name($_FILES['files']['name'][$key] ?? 'unknown');
            continue;
        }

        $file = array(
                'name' => $_FILES['files']['name'][$key],
                'type' => $_FILES['files']['type'][$key],
                'tmp_name' => $tmp_name,
                'error' => $_FILES['files']['error'][$key],
                'size' => $_FILES['files']['size'][$key]
            );
            
        $upload = wp_handle_upload($file, array('test_form' => false));
        if (!isset($upload['error'])) {
                $attachment = array(
                    'post_mime_type' => $upload['type'],
                    'post_title' => sanitize_file_name($file['name']),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_parent' => $post_id
                );
                
                $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id, true);
                if (is_wp_error($attachment_id)) {
                    @unlink($upload['file']);
                    $failed_files[] = sanitize_file_name($file['name']);
                    continue;
                }

                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                if (is_array($attachment_data)) {
                    wp_update_attachment_metadata($attachment_id, $attachment_data);
                }
                
                $uploaded_images[] = array(
                    'url' => wp_get_attachment_url($attachment_id),
                    'id' => $attachment_id
                );
        } else {
            $failed_files[] = sanitize_file_name($file['name']);
        }
    }
    
    if (!empty($uploaded_images)) {
        wp_send_json_success(array('images' => $uploaded_images, 'failed' => $failed_files));
    } else {
        wp_send_json_error(array('message' => 'No images uploaded', 'failed' => $failed_files));
    }
}
add_action('wp_ajax_upload_chapter_images', 'ajax_upload_chapter_images');

// ============================================
// LOCAL FOLDER IMPORT FEATURE (FIXED - Uses ABSPATH)
// ============================================

// Add Import from Folder menu page
function add_folder_import_page() {
    add_submenu_page(
        'edit.php?post_type=manga',
        __('Import from Folder', 'manga-theme'),
        __('Import from Folder', 'manga-theme'),
        'manage_options',
        'folder-import',
        'render_folder_import_page'
    );
}
add_action('admin_menu', 'add_folder_import_page');

// Define the base manga directory path - FIXED to use ABSPATH (WordPress root)
function get_manga_base_directory() {
    // Use ABSPATH which points to WordPress root directory (where wp-config.php is)
    $base_path = ABSPATH . 'manga/';
    
    // Create directory if it doesn't exist
    if (!file_exists($base_path)) {
        wp_mkdir_p($base_path);
    }
    
    return $base_path;
}

/** Use the public site's scheme for URLs served from this WordPress host. */
function manga_public_url($url) {
    $url = esc_url_raw($url);
    $home_url = home_url('/');
    $url_host = wp_parse_url($url, PHP_URL_HOST);
    $home_host = wp_parse_url($home_url, PHP_URL_HOST);
    $home_scheme = wp_parse_url($home_url, PHP_URL_SCHEME);

    if ($url_host && $home_host && strcasecmp($url_host, $home_host) === 0
        && in_array($home_scheme, array('http', 'https'), true)) {
        return set_url_scheme($url, $home_scheme);
    }

    return $url;
}

// Get the URL for manga images while preserving the WordPress install path.
function get_manga_base_url() {
    return manga_public_url(site_url('/manga/'));
}

// Helper function to mark chapter as imported
function mark_chapter_as_imported($chapter_path) {
    $option_name = 'imported_chapters_' . md5($chapter_path);
    update_option($option_name, array(
        'path' => $chapter_path,
        'imported_at' => current_time('mysql'),
        'status' => 'imported'
    ));
}

// Helper function to check if chapter is already imported
function is_chapter_imported($chapter_path) {
    $option_name = 'imported_chapters_' . md5($chapter_path);
    $imported = get_option($option_name);
    return !empty($imported);
}

// Clear imported chapters cache
function clear_imported_chapters_cache() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'imported_chapters_%'");
}

function manga_resolve_source_folder($submitted_path, $allow_chapter = false) {
    $base_path = realpath(get_manga_base_directory());
    $real_path = realpath((string) $submitted_path);
    if (!$base_path || !$real_path || !is_dir($real_path)) {
        return new WP_Error('invalid_manga_path', 'The selected folder does not exist.');
    }

    $base_prefix = trailingslashit(wp_normalize_path($base_path));
    $normalized_path = wp_normalize_path($real_path);
    if (strpos($normalized_path, $base_prefix) !== 0) {
        return new WP_Error('invalid_manga_path', 'The selected folder is outside the manga library.');
    }

    $relative = trim(substr($normalized_path, strlen($base_prefix)), '/');
    $segments = array_values(array_filter(explode('/', $relative), 'strlen'));
    if ((!$allow_chapter && count($segments) !== 1) ||
        ($allow_chapter && (count($segments) < 2 || count($segments) > 5))) {
        return new WP_Error('invalid_manga_path', 'The selected folder has an invalid location or is nested too deeply.');
    }

    $series_path = $base_prefix . $segments[0];
    $series_real = realpath($series_path);
    if (!$series_real || wp_normalize_path($series_real) !== wp_normalize_path($series_path)) {
        return new WP_Error('invalid_manga_path', 'The selected series folder is invalid.');
    }

    if ($allow_chapter) {
        for ($index = 1; $index < count($segments) - 1; $index++) {
            $ancestor_path = $base_prefix . implode('/', array_slice($segments, 0, $index + 1));
            if (!is_dir($ancestor_path) || manga_supported_image_files($ancestor_path)) {
                return new WP_Error('invalid_manga_path', 'Chapter folders cannot be nested inside another image folder.');
            }
        }
    }

    return array(
        'path' => $real_path,
        'series_path' => $series_real,
        'series_name' => basename($series_real),
        'chapter_name' => basename($real_path),
        'relative_segments' => $segments,
    );
}

function manga_supported_image_files($folder) {
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $files = array();
    foreach ((array) scandir($folder) as $name) {
        $path = trailingslashit($folder) . $name;
        if (is_file($path) && is_readable($path) && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $allowed_extensions, true)) {
            $files[] = $path;
        }
    }
    usort($files, function($a, $b) {
        return strnatcasecmp(basename($a), basename($b));
    });
    return $files;
}

/** A chapter code in a filename such as "c011" or "c015#1". */
function manga_filename_chapter_group($filename) {
    $filename = manga_normalize_chapter_text((string) $filename);
    if (preg_match('/(?:^|[\s._-])(?:c|ch(?:apter)?|ep(?:isode)?|cap(?:itulo)?|第|제)\s*[._:#-]*(\d+(?:\.\d+)?)(?=[\s(_\-.#]|$)/iu', $filename, $matches)) {
        return 'c' . (string) (float) $matches[1];
    }
    if (preg_match('/(?:^|[\s._-])(\d+(?:\.\d+)?)\s*(?:화|話|章|回)(?=[\s(_\-.#]|$)/u', $filename, $matches)) {
        return 'c' . (string) (float) $matches[1];
    }
    return '';
}

/** Limit a shared volume folder to the pages belonging to one chapter. */
function manga_imported_source_files($folder, $source_group = '') {
    $files = manga_supported_image_files($folder);
    if ($source_group === '' || $source_group === 'volume') {
        return $files;
    }
    return array_values(array_filter($files, function($file) use ($source_group) {
        return manga_filename_chapter_group(basename($file)) === $source_group;
    }));
}

/** Convert a file in the manga library to its public URL without copying it. */
function manga_source_file_url($source_path) {
    $base_path = realpath(get_manga_base_directory());
    $real_file = realpath((string) $source_path);
    if (!$base_path || !$real_file || !is_file($real_file) || !is_readable($real_file)) {
        return new WP_Error('unreadable_image', 'Image file cannot be read.');
    }

    $base_prefix = trailingslashit(wp_normalize_path($base_path));
    $normalized_file = wp_normalize_path($real_file);
    if (strpos($normalized_file, $base_prefix) !== 0) {
        return new WP_Error('invalid_manga_file', 'Image file is outside the manga library.');
    }

    $relative = substr($normalized_file, strlen($base_prefix));
    $url_parts = array_map('rawurlencode', explode('/', $relative));
    return trailingslashit(get_manga_base_url()) . implode('/', $url_parts);
}

/** Prefer an existing WordPress thumbnail, then the cover file in the manga folder. */
function manga_get_cover_url($manga_id, $size = 'manga-cover-small') {
    $manga_id = absint($manga_id);
    if (has_post_thumbnail($manga_id)) {
        $thumbnail_url = get_the_post_thumbnail_url($manga_id, $size);
        if ($thumbnail_url) {
            return manga_public_url($thumbnail_url);
        }
    }

    $cover_url = get_post_meta($manga_id, '_manga_cover_url', true);
    if ($cover_url) {
        return manga_public_url($cover_url);
    }

    return 'https://via.placeholder.com/180x252?text=No+Cover';
}

/** Resolve imported chapter pages from their original folder, with old saved URLs as fallback. */
function manga_get_chapter_image_urls($chapter_id) {
    $source_path = get_post_meta(absint($chapter_id), 'imported_from_path', true);
    if ($source_path) {
        $resolved = manga_resolve_source_folder($source_path, true);
        if (!is_wp_error($resolved)) {
            $direct_urls = array();
            $source_group = (string) get_post_meta(absint($chapter_id), '_manga_source_group', true);
            foreach (manga_imported_source_files($resolved['path'], $source_group) as $source_file) {
                $image_url = manga_source_file_url($source_file);
                if (is_wp_error($image_url)) {
                    $direct_urls = array();
                    break;
                }
                $direct_urls[] = $image_url;
            }
            if ($direct_urls) {
                return $direct_urls;
            }
        }
    }

    $saved_links = get_post_meta(absint($chapter_id), 'image_links', true);
    if (is_array($saved_links)) {
        return array_values(array_filter(array_map('manga_public_url', $saved_links)));
    }
    return array_values(array_filter(array_map('manga_public_url', preg_split('/\r\n|\r|\n/', (string) $saved_links))));
}

/** A source folder, not a display title, is the identity of an imported manga. */
function manga_import_identity($value) {
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', trim($value));
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function manga_imported_series_matches($resolved) {
    $posts = get_posts(array(
        'post_type' => 'manga',
        'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'manga_include_merged' => true,
        'suppress_filters' => true,
    ));
    $matches = array();
    $folder_identity = manga_import_identity($resolved['series_name']);
    $source_identity = wp_normalize_path($resolved['series_path']);
    foreach ($posts as $post) {
        $saved_source = get_post_meta($post->ID, '_manga_source_path', true);
        if ($saved_source) {
            if (wp_normalize_path($saved_source) === $source_identity) {
                $matches[] = $post;
            }
            continue;
        }
        $marker = 'Imported from folder: ';
        if (strpos($post->post_content, $marker) !== 0) {
            continue;
        }
        $stored_folder = substr($post->post_content, strlen($marker));
        if (manga_import_identity($stored_folder) === $folder_identity &&
            manga_import_identity($post->post_title) === $folder_identity) {
            $matches[] = $post;
        }
    }
    return $matches;
}

/** Atomic option locks prevent two cron/admin requests creating the same post. */
function manga_acquire_import_lock($key) {
    $option = 'manga_import_lock_' . md5($key);
    $now = time();
    if (add_option($option, $now, '', false)) {
        return $option;
    }
    $old = (int) get_option($option);
    if ($old && $old < $now - 300) {
        delete_option($option);
        if (add_option($option, $now, '', false)) {
            return $option;
        }
    }
    return false;
}

function manga_find_or_create_from_folder($series_path) {
    $resolved = manga_resolve_source_folder($series_path, false);
    if (is_wp_error($resolved)) {
        return $resolved;
    }
    $lock = manga_acquire_import_lock($resolved['series_path']);
    if (!$lock) {
        return new WP_Error('manga_import_busy', 'This manga folder is already being imported. Please retry.');
    }
    try {
        $matches = manga_imported_series_matches($resolved);
        if ($matches) {
            $manga_id = (int) $matches[0]->ID;
        } else {
            $manga_id = wp_insert_post(array(
                'post_title' => $resolved['series_name'],
                'post_type' => 'manga',
                'post_status' => 'publish',
                'post_content' => 'Imported from folder: ' . $resolved['series_name'],
            ), true);
            if (is_wp_error($manga_id) || !$manga_id) {
                return is_wp_error($manga_id) ? $manga_id : new WP_Error('manga_insert_failed', 'Could not create manga.');
            }
        }
        $repair = manga_reconcile_imported_series($resolved, $manga_id);
        if (is_wp_error($repair)) {
            return $repair;
        }
        update_post_meta($manga_id, '_manga_source_path', wp_normalize_path($resolved['series_path']));
        manga_set_cover_from_folder($manga_id, $resolved['series_path']);
        return (int) $manga_id;
    } finally {
        delete_option($lock);
    }
}
/** Merge only records demonstrably imported from the same folder; never delete posts. */
function manga_reconcile_imported_series($resolved, $canonical_id) {
    $matches = manga_imported_series_matches($resolved);
    if (count($matches) < 2) {
        return true;
    }
    $ids = array_map(function($post) { return (int) $post->ID; }, $matches);
    if ((int) $canonical_id !== $ids[0]) {
        return new WP_Error('manga_repair_identity', 'Canonical manga changed during reconciliation.');
    }
    $duplicates = array_slice($ids, 1);
    $backup_key = 'manga_import_repair_' . md5(wp_normalize_path($resolved['series_path']));
    $previous = get_option($backup_key);
    if (is_array($previous) && ($previous['status'] ?? '') === 'pending') {
        if (!manga_rollback_imported_series($previous)) {
            return new WP_Error('manga_repair_rollback_failed', 'A previous repair could not be rolled back; manual review is required.');
        }
        $previous['status'] = 'rolled_back';
        update_option($backup_key, $previous, false);
    }
    if (is_array($previous) && ($previous['status'] ?? '') === 'complete') {
        $already_merged = true;
        foreach ($duplicates as $duplicate_id) {
            if ((int) get_post_meta($duplicate_id, '_manga_duplicate_of', true) !== (int) $canonical_id) {
                $already_merged = false;
                break;
            }
        }
        if ($already_merged) {
            return true;
        }
    }
    $chapters = get_posts(array(
        'post_type' => 'chapter',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'meta_query' => array(array(
            'key' => 'connected_manga_id',
            'value' => $ids,
            'compare' => 'IN',
        )),
        'suppress_filters' => true,
    ));
    $original = array();
    $source_paths = array();
    foreach ($chapters as $chapter) {
        $old_id = (int) get_post_meta($chapter->ID, 'connected_manga_id', true);
        $source_path = get_post_meta($chapter->ID, 'imported_from_path', true);
        if (!in_array($old_id, $ids, true) || !$source_path) {
            return new WP_Error('manga_repair_conflict', 'A chapter has no valid source folder; automatic merge was stopped.');
        }
        $normalized = wp_normalize_path($source_path);
        if (isset($source_paths[$normalized])) {
            return new WP_Error('manga_repair_conflict', 'Two chapters use the same source folder; automatic merge was stopped.');
        }
        $source_paths[$normalized] = true;
        $original[$chapter->ID] = $old_id;
    }

    $backup = array(
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'source' => wp_normalize_path($resolved['series_path']),
        'canonical_id' => $canonical_id,
        'duplicates' => $duplicates,
        'chapters' => $original,
    );
    update_option($backup_key, $backup, false);
    if (get_option($backup_key) !== $backup) {
        return new WP_Error('manga_repair_backup', 'Could not save the repair backup; nothing was changed.');
    }

    foreach ($original as $chapter_id => $old_id) {
        if ($old_id === (int) $canonical_id) {
            continue;
        }
        update_post_meta($chapter_id, 'connected_manga_id', $canonical_id);
        if ((int) get_post_meta($chapter_id, 'connected_manga_id', true) !== (int) $canonical_id) {
            $restored = manga_rollback_imported_series($backup);
            $backup['status'] = $restored ? 'rolled_back' : 'pending';
            update_option($backup_key, $backup, false);
            return new WP_Error('manga_repair_failed', $restored ? 'Chapter update failed; previous links were restored.' : 'Chapter update and rollback failed; manual review is required.');
        }
    }
    foreach ($duplicates as $duplicate_id) {
        update_post_meta($duplicate_id, '_manga_duplicate_of', $canonical_id);
        if ((int) get_post_meta($duplicate_id, '_manga_duplicate_of', true) !== (int) $canonical_id) {
            $restored = manga_rollback_imported_series($backup);
            $backup['status'] = $restored ? 'rolled_back' : 'pending';
            update_option($backup_key, $backup, false);
            return new WP_Error('manga_repair_failed', $restored ? 'Duplicate marking failed; previous links were restored.' : 'Duplicate marking and rollback failed; manual review is required.');
        }
    }
    $backup['status'] = 'complete';
    update_option($backup_key, $backup, false);
    return true;
}

function manga_rollback_imported_series($backup) {
    $restored = true;
    foreach ((array) ($backup['chapters'] ?? array()) as $chapter_id => $old_id) {
        if (!get_post($chapter_id)) {
            $restored = false;
            continue;
        }
        update_post_meta((int) $chapter_id, 'connected_manga_id', (int) $old_id);
        if ((int) get_post_meta((int) $chapter_id, 'connected_manga_id', true) !== (int) $old_id) {
            $restored = false;
        }
    }
    foreach ((array) ($backup['duplicates'] ?? array()) as $duplicate_id) {
        delete_post_meta((int) $duplicate_id, '_manga_duplicate_of');
        if (get_post_meta((int) $duplicate_id, '_manga_duplicate_of', true)) {
            $restored = false;
        }
    }
    return $restored;
}

/** Hide merged cards from public lists while retaining their URLs as redirects. */
function manga_hide_merged_imports($query) {
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }
    if ($query->get('post_type') !== 'manga' || $query->is_singular() ||
        $query->get('manga_include_merged')) {
        return;
    }
    $meta_query = (array) $query->get('meta_query');
    $meta_query[] = array('key' => '_manga_duplicate_of', 'compare' => 'NOT EXISTS');
    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'manga_hide_merged_imports');

function manga_redirect_merged_import() {
    if (!is_singular('manga')) {
        return;
    }
    $canonical_id = (int) get_post_meta(get_queried_object_id(), '_manga_duplicate_of', true);
    if ($canonical_id && get_post_status($canonical_id) === 'publish') {
        wp_safe_redirect(get_permalink($canonical_id), 302);
        exit;
    }
}
add_action('template_redirect', 'manga_redirect_merged_import', 1);
function manga_set_cover_from_folder($manga_id, $series_path) {
    if (get_post_meta($manga_id, '_manga_cover_url', true)) {
        return true;
    }

    foreach (array('cover.jpg', 'cover.jpeg', 'cover.png', 'cover.webp') as $cover_name) {
        $cover_path = trailingslashit($series_path) . $cover_name;
        if (!is_file($cover_path)) {
            continue;
        }
        $cover_url = manga_source_file_url($cover_path);
        if (!is_wp_error($cover_url)) {
            update_post_meta($manga_id, '_manga_cover_url', $cover_url);
            return true;
        }
        return $cover_url;
    }

    return false;
}

function manga_scan_chapter_folders_recursive($directory, $series_path, $depth = 0) {
    $chapters = array();
    $series_real = realpath($series_path);
    $directory_real = realpath($directory);
    if (!$series_real || !$directory_real || !is_dir($directory_real) || !is_readable($directory_real)) {
        return $chapters;
    }

    $series_prefix = trailingslashit(wp_normalize_path($series_real));
    $directory_normalized = wp_normalize_path($directory_real);
    if ($directory_normalized !== wp_normalize_path($series_real) && strpos($directory_normalized, $series_prefix) !== 0) {
        return $chapters;
    }

    foreach ((array) scandir($directory_real) as $entry) {
        if ($entry === '.' || $entry === '..' || strpos($entry, '.') === 0) {
            continue;
        }
        $entry_path = trailingslashit($directory_real) . $entry;
        if (is_link($entry_path) || !is_dir($entry_path)) {
            continue;
        }
        $entry_real = realpath($entry_path);
        if (!$entry_real || strpos(wp_normalize_path($entry_real), $series_prefix) !== 0) {
            continue;
        }

        $direct_images = manga_supported_image_files($entry_real);
        if ($direct_images) {
            $relative = trim(substr(wp_normalize_path($entry_real), strlen($series_prefix)), '/');
            $parts = array_values(array_filter(explode('/', $relative), 'strlen'));
            $label = basename($entry_real);
            for ($index = count($parts) - 2; $index >= 0; $index--) {
                if (preg_match('/^(?:Vol(?:ume)?|Tome|Band|V|巻|卷)[._\s-]*\d+(?:\.\d+)?$/iu', $parts[$index])) {
                    $label = $parts[$index] . ' ' . $label;
                    break;
                }
            }
            $chapters[] = array(
                'name' => $label,
                'path' => $entry_real,
                'image_count' => count($direct_images),
            );
            continue;
        }

        // Bound recursion and skip symlinks above to avoid loops and paths escaping the library.
        if ($depth < 4) {
            $chapters = array_merge($chapters, manga_scan_chapter_folders_recursive($entry_real, $series_real, $depth + 1));
        }
    }

    return $chapters;
}

function manga_scan_chapter_folders($series_path) {
    if (!is_dir($series_path) || !is_readable($series_path)) {
        return array();
    }
    return manga_scan_chapter_folders_recursive($series_path, $series_path, 0);
}

/** Build a stable parsing label using the closest explicit volume ancestor, if present. */
function manga_source_chapter_label($resolved) {
    $segments = (array) ($resolved['relative_segments'] ?? array());
    $chapter_name = (string) ($resolved['chapter_name'] ?? '');
    for ($index = count($segments) - 2; $index >= 1; $index--) {
        if (preg_match('/^(?:Vol(?:ume)?|Tome|Band|V|巻|卷)[._\s-]*\d+(?:\.\d+)?$/iu', $segments[$index])) {
            return $segments[$index] . ' ' . $chapter_name;
        }
    }
    return $chapter_name;
}

/** Discover ordinary chapter folders and chapters stored together in volume folders. */
function manga_scan_auto_chapter_sources($series_path) {
    $chapters = array();
    foreach (manga_scan_chapter_folders($series_path) as $folder) {
        if (!preg_match('/^(?:Vol(?:ume)?|Tome|Band|V|巻|卷)[._\s-]*(\d+(?:\.\d+)?)$/iu', $folder['name'], $volume_match)) {
            $folder['source_group'] = '';
            $chapters[] = $folder;
            continue;
        }

        $files = manga_supported_image_files($folder['path']);
        $groups = array();
        $unmatched = 0;
        foreach ($files as $file) {
            $group = manga_filename_chapter_group(basename($file));
            if ($group === '') {
                $unmatched++;
            } else {
                $groups[$group][] = $file;
            }
        }
        $volume = (float) $volume_match[1];
        if ($groups && $unmatched) {
            $chapters[] = array(
                'name' => $folder['name'],
                'path' => $folder['path'],
                'source_group' => '',
                'scan_error' => 'Some images have no chapter code in ' . $folder['path'],
            );
        } elseif ($groups) {
            foreach ($groups as $group => $group_files) {
                $chapters[] = array(
                    'name' => 'Vol. ' . $volume . ' Ch. ' . substr($group, 1),
                    'path' => $folder['path'],
                    'source_group' => $group,
                    'image_count' => count($group_files),
                );
            }
        } elseif (count($files) > 1) {
            // No chapter boundary can be inferred; preserve this as one complete volume.
            $chapters[] = array(
                'name' => 'Vol. ' . $volume,
                'path' => $folder['path'],
                'source_group' => 'volume',
                'image_count' => count($files),
            );
        }
    }
    usort($chapters, function($a, $b) {
        $a_number = manga_parse_chapter_title($a['name']);
        $b_number = manga_parse_chapter_title($b['name']);
        if ($a_number['volume'] !== $b_number['volume']) {
            return $a_number['volume'] <=> $b_number['volume'];
        }
        if ($a_number['chapter'] !== $b_number['chapter']) {
            return $a_number['chapter'] <=> $b_number['chapter'];
        }
        return strnatcasecmp($a['name'], $b['name']);
    });
    return $chapters;
}

/** Validate a saved chapter before treating a path as imported. */
function manga_imported_chapter_valid($chapter_id, $source_path, $manga_id, $source_group = '') {
    $post = get_post($chapter_id);
    if (!$post || $post->post_type !== 'chapter') {
        return false;
    }
    if (wp_normalize_path((string) get_post_meta($chapter_id, 'imported_from_path', true)) !==
        wp_normalize_path($source_path)) {
        return false;
    }
    if ((int) get_post_meta($chapter_id, 'connected_manga_id', true) !== (int) $manga_id) {
        return false;
    }
    if ((string) get_post_meta($chapter_id, '_manga_source_group', true) !== $source_group) {
        return false;
    }
    $links = get_post_meta($chapter_id, 'image_links', true);
    return is_string($links) && trim($links) !== '';
}
/** Refresh changed pages in an imported chapter with a recoverable metadata backup. */
function manga_sync_imported_chapter_images($chapter_id, $source_path, $source_group = '') {
    $files = manga_imported_source_files($source_path, $source_group);
    if (!$files) {
        return new WP_Error('manga_images_missing', 'The source chapter has no readable images.');
    }
    $manifest = array();
    $urls = array();
    foreach ($files as $file) {
        $url = manga_source_file_url($file);
        if (is_wp_error($url)) {
            return $url;
        }
        $mtime = filemtime($file);
        $manifest[] = array(wp_normalize_path($file), filesize($file), $mtime);
        $urls[] = $url;
    }
    if (manga_imported_source_files($source_path, $source_group) !== $files) {
        return new WP_Error('manga_images_changed', 'Images changed during validation; retry next run.');
    }
    $signature = md5(wp_json_encode($manifest));
    $old_signature = (string) get_post_meta($chapter_id, '_manga_source_image_signature', true);
    $old_links = (string) get_post_meta($chapter_id, 'image_links', true);
    $plain_links = implode("\n", $urls);
    if (!$old_signature && $old_links === $plain_links) {
        update_post_meta($chapter_id, '_manga_source_image_signature', $signature);
        return true;
    }
    if ($old_signature === $signature && $old_links === $plain_links) {
        return true;
    }
    $versioned = array();
    foreach ($urls as $index => $url) {
        $versioned[] = add_query_arg('v', (string) $manifest[$index][2], $url);
    }
    $new_links = implode("\n", $versioned);
    if ($old_signature === $signature && $old_links === $new_links) {
        return true;
    }
    $backup_key = 'manga_chapter_image_backup_' . (int) $chapter_id;
    $previous = get_option($backup_key);
    if (is_array($previous) && ($previous['status'] ?? '') === 'pending') {
        update_post_meta($chapter_id, 'image_links', $previous['links']);
        update_post_meta($chapter_id, '_manga_source_image_signature', $previous['signature']);
    }
    $backup = array(
        'status' => 'pending',
        'created_at' => current_time('mysql'),
        'links' => $old_links,
        'signature' => $old_signature,
    );
    update_option($backup_key, $backup, false);
    if (get_option($backup_key) !== $backup) {
        return new WP_Error('manga_image_backup_failed', 'Could not save an image-list backup.');
    }
    update_post_meta($chapter_id, 'image_links', $new_links);
    update_post_meta($chapter_id, '_manga_source_image_signature', $signature);
    if (get_post_meta($chapter_id, 'image_links', true) !== $new_links ||
        get_post_meta($chapter_id, '_manga_source_image_signature', true) !== $signature) {
        update_post_meta($chapter_id, 'image_links', $old_links);
        update_post_meta($chapter_id, '_manga_source_image_signature', $old_signature);
        $backup['status'] = 'rolled_back';
        update_option($backup_key, $backup, false);
        return new WP_Error('manga_image_update_failed', 'Image list update failed and was rolled back.');
    }
    $backup['status'] = 'complete';
    update_option($backup_key, $backup, false);
    return true;
}
/** Run folder discovery often, while letting WordPress do the work in the background. */
function manga_auto_sync_cron_schedules($schedules) {
    $schedules['manga_every_fifteen_minutes'] = array(
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display' => __('Every 15 minutes', 'manga-theme'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'manga_auto_sync_cron_schedules');

function manga_schedule_auto_sync() {
    if (!wp_next_scheduled('manga_auto_sync_library')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'manga_every_fifteen_minutes', 'manga_auto_sync_library');
    }
    if (get_option('manga_auto_sync_source_groups_version') !== '2' &&
        wp_schedule_single_event(time() + 10, 'manga_auto_sync_source_groups_backfill')) {
        update_option('manga_auto_sync_source_groups_version', '2', false);
    }
}
add_action('init', 'manga_schedule_auto_sync', 20);
function manga_auto_sync_backfill_source_groups() {
    delete_option('manga_auto_sync_cursor');
    manga_auto_sync_library();
}
add_action('manga_auto_sync_source_groups_backfill', 'manga_auto_sync_backfill_source_groups');
add_action('manga_auto_sync_continue', 'manga_auto_sync_library');

/** Discover new manga folders and publish new chapter records without copying images. */
function manga_auto_sync_library() {
    $sync_lock = manga_acquire_import_lock('automatic-library-sync');
    if (!$sync_lock) {
        return;
    }

    $started_at = time();
    $created_manga = 0;
    $created_chapters = 0;
    $scanned_manga = 0;
    $continue_sync = false;
    $sync_errors = array();
    $base_path = get_manga_base_directory();
    $series_folders = is_dir($base_path) ? scandir($base_path) : array();
    $cursor = get_option('manga_auto_sync_cursor', array('series' => 0, 'chapter' => 0));
    $cursor_series = max(0, (int) ($cursor['series'] ?? 0));
    $cursor_chapter = max(0, (int) ($cursor['chapter'] ?? 0));
    $next_cursor = array('series' => 0, 'chapter' => 0);

    foreach ((array) $series_folders as $series_index => $series_folder) {
        if ($series_index < $cursor_series) {
            continue;
        }
        if ($series_folder === '.' || $series_folder === '..' || strpos($series_folder, '.') === 0) {
            continue;
        }
        if (time() - $started_at >= 18 || $created_chapters >= 30) {
            $continue_sync = true;
            $next_cursor = array('series' => $series_index, 'chapter' => 0);
            break;
        }

        $series_path = trailingslashit($base_path) . $series_folder;
        if (!is_dir($series_path)) {
            continue;
        }

        $resolved_series = manga_resolve_source_folder($series_path, false);
        if (is_wp_error($resolved_series)) {
            continue;
        }

        $existing_manga = manga_imported_series_matches($resolved_series);
        $manga_id = manga_find_or_create_from_folder($resolved_series['path']);
        if (is_wp_error($manga_id) || !$manga_id) {
            $sync_errors[] = is_wp_error($manga_id) ? $manga_id->get_error_message() : 'Could not create manga.';
            continue;
        }
        if (!$existing_manga) {
            $created_manga++;
        }
        $scanned_manga++;

        foreach (manga_scan_auto_chapter_sources($resolved_series['path']) as $chapter_index => $chapter_folder) {
            if ($series_index === $cursor_series && $chapter_index < $cursor_chapter) {
                continue;
            }
            if (time() - $started_at >= 18 || $created_chapters >= 30) {
                $continue_sync = true;
                $next_cursor = array('series' => $series_index, 'chapter' => $chapter_index);
                break 2;
            }

            if (!empty($chapter_folder['scan_error'])) {
                $sync_errors[] = $chapter_folder['scan_error'];
                continue;
            }

            $chapter_path = $chapter_folder['path'];
            $source_group = $chapter_folder['source_group'];
            // Cache entries can outlive failed writes; verify the post itself.
            $resolved_chapter = manga_resolve_source_folder($chapter_path, true);
            if (is_wp_error($resolved_chapter)) {
                continue;
            }

            $existing_by_path = get_posts(array(
                'post_type' => 'chapter',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'meta_key' => 'imported_from_path',
                'meta_value' => $resolved_chapter['path'],
            ));
            $existing_id = 0;
            foreach ($existing_by_path as $existing_post) {
                if ((string) get_post_meta($existing_post->ID, '_manga_source_group', true) === $source_group) {
                    $existing_id = (int) $existing_post->ID;
                    break;
                }
            }
            if ($existing_id) {
                if (manga_imported_chapter_valid($existing_id, $resolved_chapter['path'], $manga_id, $source_group)) {
                    if (get_post_status($existing_id) === 'draft') {
                        wp_update_post(array('ID' => $existing_id, 'post_status' => 'publish'));
                    }
                    if (get_post_status($existing_id) === 'publish') {
                        $image_update = manga_sync_imported_chapter_images($existing_id, $resolved_chapter['path'], $source_group);
                        if (is_wp_error($image_update)) {
                            $sync_errors[] = $image_update->get_error_message() . ' ' . $resolved_chapter['path'];
                        } else {
                            mark_chapter_as_imported($resolved_chapter['path']);
                        }
                        continue;
                    }
                }
                // Repair only a verifiable record for this exact path, manga, and source group.
                $existing_post = get_post($existing_id);
                $existing_status = get_post_status($existing_id);
                if ($existing_post && $existing_post->post_type === 'chapter' &&
                    (int) get_post_meta($existing_id, 'connected_manga_id', true) === (int) $manga_id &&
                    in_array($existing_status, array('draft', 'publish'), true)) {
                    $image_repair = manga_sync_imported_chapter_images($existing_id, $resolved_chapter['path'], $source_group);
                    if (!is_wp_error($image_repair) &&
                        manga_imported_chapter_valid($existing_id, $resolved_chapter['path'], $manga_id, $source_group)) {
                        if ($existing_status === 'draft') {
                            $published = wp_update_post(array('ID' => $existing_id, 'post_status' => 'publish'), true);
                            if (is_wp_error($published) || !$published || get_post_status($existing_id) !== 'publish') {
                                $sync_errors[] = 'Chapter images were repaired, but publishing failed: ' . $resolved_chapter['path'];
                                continue;
                            }
                        }
                        mark_chapter_as_imported($resolved_chapter['path']);
                        continue;
                    }
                    $sync_errors[] = (is_wp_error($image_repair) ? $image_repair->get_error_message() : 'Existing chapter still failed validation.') . ' ' . $resolved_chapter['path'];
                    continue;
                }
                $sync_errors[] = 'Incomplete existing chapter record (identity/status mismatch; left unchanged): ' . $resolved_chapter['path'];
                continue;
            }

            $chapter_label = $source_group !== ''
                ? $chapter_folder['name']
                : manga_source_chapter_label($resolved_chapter);
            $numbers = manga_parse_chapter_title($chapter_label);
            if ($numbers['chapter'] <= 0 && !($source_group === 'volume' && $numbers['volume'] > 0)) {
                if ($source_group !== 'volume') {
                    $filename_candidate = manga_infer_chapter_from_filenames(
                        manga_imported_source_files($resolved_chapter['path'], $source_group)
                    );
                    if (!empty($filename_candidate['chapter'])) {
                        $numbers['chapter'] = (float) $filename_candidate['chapter'];
                        $numbers['confidence'] = $filename_candidate['confidence'];
                        $numbers['source'] = $filename_candidate['source'];
                        $chapter_label .= ' - Ch. ' . $numbers['chapter'];
                    } elseif (!empty($filename_candidate['ambiguous']) || !empty($numbers['ambiguous'])) {
                        $sync_errors[] = 'Chapter number is ambiguous and needs review: ' . $resolved_chapter['path'];
                        continue;
                    }
                }
            }
            if ($numbers['chapter'] <= 0 && !($source_group === 'volume' && $numbers['volume'] > 0)) {
                $sync_errors[] = 'Could not determine a chapter number safely: ' . $resolved_chapter['path'];
                continue;
            }
            if (($numbers['confidence'] ?? 'none') !== 'high' && $source_group !== 'volume') {
                $sync_errors[] = 'Chapter number candidate needs manual confirmation (' . $numbers['chapter'] . '): ' . $resolved_chapter['path'];
                continue;
            }

            $chapter_title = $resolved_series['series_name'] . ' - ' . $chapter_label;
            $existing_by_title = get_posts(array(
                'post_type' => 'chapter',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'title' => $chapter_title,
                'meta_query' => array(array('key' => 'connected_manga_id', 'value' => $manga_id, 'compare' => '=')),
            ));
            if ($existing_by_title) {
                $sync_errors[] = 'Chapter title conflicts with another source folder: ' . $resolved_chapter['path'];
                continue;
            }

            $source_files = manga_imported_source_files($resolved_chapter['path'], $source_group);
            $image_urls = array();
            foreach ($source_files as $source_file) {
                $image_url = manga_source_file_url($source_file);
                if (is_wp_error($image_url)) {
                    $image_urls = array();
                    break;
                }
                $image_urls[] = $image_url;
            }
            if (!$image_urls) {
                continue;
            }

            $chapter_id = wp_insert_post(array(
                'post_title' => $chapter_title,
                'post_type' => 'chapter',
                'post_status' => 'draft',
                'meta_input' => array(
                    'connected_manga_id' => $manga_id,
                    'volume_number' => $numbers['volume'],
                    'chapter_number' => $numbers['chapter'],
                    '_manga_chapter_parse_source' => $numbers['source'] ?? '',
                    '_manga_chapter_parse_confidence' => $numbers['confidence'] ?? 'none',
                    'image_links' => implode("\n", $image_urls),
                    'image_attachment_ids' => array(),
                    'imported_from_path' => $resolved_chapter['path'],
                    '_manga_source_group' => $source_group,
                    'import_date' => current_time('mysql'),
                ),
            ), true);

            if (is_wp_error($chapter_id) || !$chapter_id ||
                !manga_imported_chapter_valid($chapter_id, $resolved_chapter['path'], $manga_id, $source_group) ||
                manga_imported_source_files($resolved_chapter['path'], $source_group) !== $source_files) {
                $sync_errors[] = 'Chapter validation failed: ' . $resolved_chapter['path'];
                continue;
            }
            $published = wp_update_post(array('ID' => $chapter_id, 'post_status' => 'publish'), true);
            if (is_wp_error($published) || !$published || get_post_status($chapter_id) !== 'publish') {
                $sync_errors[] = 'Could not publish chapter: ' . $resolved_chapter['path'];
                continue;
            }
            mark_chapter_as_imported($resolved_chapter['path']);
            $created_chapters++;
        }
    }

    if ($continue_sync) {
        update_option('manga_auto_sync_cursor', $next_cursor, false);
    } else {
        delete_option('manga_auto_sync_cursor');
    }
    delete_option($sync_lock);
    update_option('manga_auto_sync_status', array(
        'last_run' => current_time('mysql'),
        'manga_scanned' => $scanned_manga,
        'manga_created' => $created_manga,
        'chapters_created' => $created_chapters,
        'continued' => $continue_sync,
        'errors' => array_slice($sync_errors, 0, 20),
    ), false);

    if ($continue_sync && !wp_next_scheduled('manga_auto_sync_continue')) {
        wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'manga_auto_sync_continue');
    }
}
add_action('manga_auto_sync_library', 'manga_auto_sync_library');

// Render the import page
function render_folder_import_page() {
    $base_path = get_manga_base_directory();
    $base_url = get_manga_base_url();
    $auto_sync_status = get_option('manga_auto_sync_status', array());
    // Folder names may contain spaces, apostrophes, or punctuation; preserve the
    // actual name instead of applying filename sanitization before path lookup.
    $current_manga = isset($_GET['manga']) ? sanitize_text_field(wp_unslash($_GET['manga'])) : '';
    $import_status = '';
    
    // Handle manga creation from folder
    if (isset($_POST['create_manga_from_folder']) && isset($_POST['manga_folder_path'])) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to create manga from folders.', 'manga-theme'));
        }
        check_admin_referer('create_manga_from_folder');
        $resolved = manga_resolve_source_folder(wp_unslash($_POST['manga_folder_path']), false);
        if (is_wp_error($resolved)) {
            $import_status = '<div class="notice notice-error"><p>' . esc_html($resolved->get_error_message()) . '</p></div>';
        } else {
            $manga_id = manga_find_or_create_from_folder($resolved['path']);
            if (is_wp_error($manga_id)) {
                $import_status = '<div class="notice notice-error"><p>' . esc_html($manga_id->get_error_message()) . '</p></div>';
            } else {
                $import_status = '<div class="notice notice-success"><p>✓ Manga entry is ready: ' . esc_html($resolved['series_name']) . ' (cover imported when available).</p></div>';
            }
        }
    }

    if (isset($_GET['clear_cache'])) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to clear the import cache.', 'manga-theme'));
        }
        check_admin_referer('clear_imported_chapters_cache');
        clear_imported_chapters_cache();
        $import_status = '<div class="notice notice-success"><p>✓ Import cache cleared. Existing chapter records are still checked to prevent duplicate imports.</p></div>';
    }
    
    // Clear PHP cache before scanning
    clearstatcache();
    
    // Scan for manga folders
    $manga_folders = array();
    if (is_dir($base_path)) {
        $items = scandir($base_path);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($base_path . $item)) {
                $manga_folders[] = $item;
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>📁 Import from Folder</h1>
        <p>Import manga chapters by linking directly to files in your manga folder. Images are not copied to the Media Library.</p>
        <p class="description"><strong>Automatic discovery is enabled.</strong> New manga folders and chapter folders are checked every 15 minutes by WordPress Cron.<?php if (!empty($auto_sync_status['last_run'])): ?> Last check: <?php echo esc_html($auto_sync_status['last_run']); ?> — added <?php echo absint($auto_sync_status['manga_created'] ?? 0); ?> manga and <?php echo absint($auto_sync_status['chapters_created'] ?? 0); ?> chapters.<?php endif; ?></p>
        <?php if (!empty($auto_sync_status['errors'])): ?>
            <div class="notice notice-warning"><p>Automatic sync needs attention:</p><ul>
                <?php foreach ((array) $auto_sync_status['errors'] as $sync_error): ?>
                    <li><?php echo esc_html($sync_error); ?></li>
                <?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
        
        <div class="notice notice-info">
            <p><strong>📂 Folder Location:</strong> <code><?php echo esc_html($base_path); ?></code></p>
            <p><strong>🔗 Public URL:</strong> <code><?php echo esc_html($base_url); ?></code></p>
            <p><strong>📁 Folder Structure:</strong><br>
            <code><?php echo esc_html($base_path); ?><strong>Manga Name/</strong>Chapter Name/01.jpg</code></p>
        </div>
        
        <?php echo $import_status; ?>
        
        <div style="margin-bottom: 20px;">
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'folder-import', 'clear_cache' => '1'), admin_url('admin.php')), 'clear_imported_chapters_cache')); ?>"
               class="button button-secondary" 
               onclick="return confirm('Clear import cache? This will allow you to re-import chapters that may have had issues.');">
                ⟳ Clear Import Cache
            </a>
            <span class="description">Use this if chapters are showing 0 images after multiple imports</span>
        </div>
        
        <div class="folder-import-container" style="display: flex; gap: 30px; margin-top: 20px; flex-wrap: wrap;">
            <!-- Left Panel: Manga List -->
            <div class="manga-list-panel" style="flex: 1; min-width: 250px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <h2>📁 Manga Folders</h2>
                
                <?php if (empty($manga_folders)): ?>
                    <p class="description">No manga folders found in <?php echo esc_html($base_path); ?></p>
                    <div class="notice notice-warning">
                        <p><strong>How to set up:</strong></p>
                        <ol>
                            <li>Using FTP or file manager, create folder: <code><?php echo esc_html($base_path); ?>Yotsubato/</code></li>
                            <li>Create chapter folder: <code><?php echo esc_html($base_path); ?>Yotsubato/Chapter 1/</code></li>
                            <li>Add images: <code><?php echo esc_html($base_path); ?>Yotsubato/Chapter 1/01.jpg</code></li>
                            <li>Refresh this page</li>
                        </ol>
                    </div>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($manga_folders as $manga): 
                            $folder_path = $base_path . $manga;
                            $chapter_count = count(array_filter(manga_scan_chapter_folders($folder_path), function($chapter) {
                                return !is_chapter_imported($chapter['path']);
                            }));
                        ?>
                            <li style="margin-bottom: 10px;">
                                <a href="<?php echo esc_url(add_query_arg(array('page' => 'folder-import', 'manga' => $manga), admin_url('admin.php'))); ?>"
                                   style="display: block; padding: 10px; background: <?php echo ($current_manga === $manga) ? '#e94560' : '#f5f5f5'; ?>; color: <?php echo ($current_manga === $manga) ? 'white' : '#333'; ?>; text-decoration: none; border-radius: 6px;">
                                    📖 <strong><?php echo esc_html($manga); ?></strong>
                                    <span style="float: right; font-size: 12px;">📄 <?php echo $chapter_count; ?> chapters</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <!-- Right Panel: Chapters List -->
            <div class="chapters-list-panel" style="flex: 2; min-width: 400px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <?php if ($current_manga): 
                    $manga_path = trailingslashit($base_path) . $current_manga;
                    $manga_real_path = realpath($manga_path);
                    $base_real_path = realpath($base_path);
                    $chapters = array();
                    if ($manga_real_path && $base_real_path && dirname($manga_real_path) === $base_real_path) {
                        $manga_path = $manga_real_path;
                        $chapters = array_values(array_filter(manga_scan_chapter_folders($manga_path), function($chapter) {
                            return !is_chapter_imported($chapter['path']);
                        }));
                    } else {
                        $current_manga = '';
                    }
                    ?>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                        <h2>📖 <?php echo esc_html($current_manga); ?></h2>
                        <div>
                            <button id="selectAllChapters" class="button">Select All</button>
                            <button id="deselectAllChapters" class="button">Deselect All</button>
                            <button id="refreshScanBtn" class="button button-secondary">⟳ Refresh Scan</button>
                        </div>
                    </div>
                    
                    <?php if (empty($chapters)): ?>
                        <p>No new chapters found in this manga folder. All chapters may have been imported already.</p>
                        <p><a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('page' => 'folder-import', 'clear_cache' => '1'), admin_url('admin.php')), 'clear_imported_chapters_cache')); ?>" class="button">Clear Import Cache</a></p>
                    <?php else: ?>
                        <form method="post" id="bulkImportForm">
                            <?php wp_nonce_field('start_folder_import', 'folder_import_nonce'); ?>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th width="50"><input type="checkbox" id="selectAllCheckbox"></th>
                                            <th>Chapter Name</th>
                                            <th>Images Found</th>
                                            <th width="120">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($chapters as $chapter):
                                            $chapter_preview = manga_parse_chapter_title($chapter['name']);
                                            if ($chapter_preview['chapter'] <= 0 && $chapter_preview['volume'] <= 0) {
                                                $filename_preview = manga_infer_chapter_from_filenames(manga_supported_image_files($chapter['path']));
                                                if (!empty($filename_preview['chapter'])) {
                                                    $chapter_preview['chapter'] = $filename_preview['chapter'];
                                                    $chapter_preview['confidence'] = $filename_preview['confidence'];
                                                    $chapter_preview['source'] = $filename_preview['source'];
                                                }
                                            }
                                            $preview_label = $chapter_preview['chapter'] > 0
                                                ? 'Chapter ' . $chapter_preview['chapter']
                                                : ($chapter_preview['volume'] > 0 ? 'Volume ' . $chapter_preview['volume'] : 'Number needs review');
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_chapters[]" value="<?php echo esc_attr($chapter['path']); ?>" 
                                                           class="chapter-checkbox">
                                                </td>
                                                <td>
                                                    <strong><?php echo esc_html($chapter['name']); ?></strong>
                                                    <br><small><?php echo esc_html($preview_label); ?> · <?php echo esc_html($chapter_preview['confidence']); ?> confidence · <?php echo esc_html($chapter_preview['source'] ?: 'no reliable evidence'); ?></small>
                                                  </td>
                                                  <td><?php echo $chapter['image_count']; ?> images</td>
                                                  <td>
                                                    <button type="button" class="button button-small manga-import-single" data-path="<?php echo esc_attr($chapter['path']); ?>" data-name="<?php echo esc_attr($chapter['name']); ?>">Import</button>
                                                  </td>
                                              </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div style="margin-top: 20px;">
                                <button type="submit" name="import_bulk_chapters" class="button button-primary">
                                    📥 Import Selected Chapters
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h3>📚 Create Manga Entry</h3>
                        <form method="post">
                            <?php wp_nonce_field('create_manga_from_folder'); ?>
                            <input type="hidden" name="manga_folder_path" value="<?php echo esc_attr($manga_path); ?>">
                            <button type="submit" name="create_manga_from_folder" class="button" 
                                    onclick="return confirm('Create manga "<?php echo esc_js($current_manga); ?>" from this folder?');">
                                Create Manga Entry
                            </button>
                            <span class="description">Creates a manga entry in WordPress for this series.</span>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 50px;">Select a manga from the left panel to view its chapters.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var importConfig = {
            ajaxUrl: ajaxurl,
            nonce: $('#folder_import_nonce').val()
        };
        var $importStatus = $('<div class="notice notice-info"><p></p></div>').hide();
        $('#bulkImportForm').before($importStatus);

        function postImportAction(action, data) {
            data = data || {};
            data.action = action;
            data.nonce = importConfig.nonce;
            return $.ajax({url: importConfig.ajaxUrl, type: 'POST', data: data});
        }

        function importOne(path, name) {
            $importStatus.removeClass('notice-error notice-success').addClass('notice-info').show().find('p').text('Preparing ' + name + '...');
            return postImportAction('manga_start_folder_import', {chapter_path: path}).then(function(start) {
                if (!start.success) throw new Error(start.data && start.data.message ? start.data.message : 'Could not start import.');
                var job = start.data;
                function nextBatch(offset) {
                    $importStatus.find('p').text('Importing ' + name + ': ' + offset + ' / ' + job.total_images + ' images');
                    return postImportAction('manga_process_folder_import_batch', {job_id: job.job_id}).then(function(batch) {
                        if (!batch.success) throw new Error(batch.data && batch.data.message ? batch.data.message : 'An image could not be imported.');
                        if (!batch.data.done) return nextBatch(batch.data.processed);
                        return batch.data;
                    });
                }
                return nextBatch(0);
            });
        }

        $('.manga-import-single').on('click', function() {
            var $button = $(this);
            if (!window.confirm('Import chapter ' + $button.data('name') + '?')) return;
            $button.prop('disabled', true);
            importOne($button.data('path'), $button.data('name')).then(function(result) {
                $importStatus.removeClass('notice-info').addClass('notice-success').find('p').text(result.message);
                window.setTimeout(function() { window.location.reload(); }, 900);
            }).catch(function(error) {
                $importStatus.removeClass('notice-info').addClass('notice-error').find('p').text(error.message);
                $button.prop('disabled', false);
            });
        });

        $('#bulkImportForm').on('submit', function(event) {
            event.preventDefault();
            var selected = $('.chapter-checkbox:checked').map(function() {
                return {path: this.value, name: $(this).closest('tr').find('strong').text()};
            }).get();
            if (!selected.length) {
                window.alert('Select at least one chapter.');
                return;
            }
            if (!window.confirm('Import ' + selected.length + ' selected chapters?')) return;
            $('#bulkImportForm button, #bulkImportForm input').prop('disabled', true);
            var index = 0;
            var completed = 0;
            var failures = [];
            function nextChapter() {
                if (index >= selected.length) {
                    var summary = 'Imported ' + completed + ' chapters.';
                    if (failures.length) summary += ' Failed: ' + failures.join('; ');
                    $importStatus.removeClass('notice-info').addClass(failures.length ? 'notice-error' : 'notice-success').find('p').text(summary);
                    window.setTimeout(function() { window.location.reload(); }, 1800);
                    return;
                }
                var chapter = selected[index++];
                importOne(chapter.path, chapter.name).then(function() {
                    completed++;
                    nextChapter();
                }).catch(function(error) {
                    failures.push(chapter.name + ': ' + error.message);
                    nextChapter();
                });
            }
            nextChapter();
        });

        $('#selectAllChapters').on('click', function(e) {
            e.preventDefault();
            $('.chapter-checkbox:not(:disabled)').prop('checked', true);
        });
        
        $('#deselectAllChapters').on('click', function(e) {
            e.preventDefault();
            $('.chapter-checkbox').prop('checked', false);
        });
        
        $('#selectAllCheckbox').on('change', function() {
            $('.chapter-checkbox:not(:disabled)').prop('checked', $(this).prop('checked'));
        });
        
        $('#refreshScanBtn').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });
    });
    </script>
    <?php
}

function manga_start_folder_import() {
    check_ajax_referer('start_folder_import', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied.'), 403);
    }

    $resolved = manga_resolve_source_folder(wp_unslash($_POST['chapter_path'] ?? ''), true);
    if (is_wp_error($resolved)) {
        wp_send_json_error(array('message' => $resolved->get_error_message()));
    }

    $path = $resolved['path'];
    // A cache flag alone does not prove that the chapter was committed.
    $existing_by_path = get_posts(array(
        'post_type' => 'chapter',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'meta_key' => 'imported_from_path',
        'meta_value' => $path,
    ));
    if ($existing_by_path) {
        wp_send_json_error(array('message' => 'A chapter record already exists for this folder. Check its status before retrying.'));
    }

    $files = manga_supported_image_files($path);
    if (!$files) {
        wp_send_json_error(array('message' => 'No supported images were found in this chapter folder.'));
    }

    $chapter_label = manga_source_chapter_label($resolved);
    $chapter_numbers = manga_parse_chapter_title($chapter_label);
    if ($chapter_numbers['chapter'] <= 0) {
        $filename_candidate = manga_infer_chapter_from_filenames($files);
        if (!empty($filename_candidate['chapter'])) {
            $chapter_numbers['chapter'] = (float) $filename_candidate['chapter'];
            $chapter_numbers['confidence'] = $filename_candidate['confidence'];
            $chapter_numbers['source'] = $filename_candidate['source'];
            $chapter_label .= ' - Ch. ' . $chapter_numbers['chapter'];
        }
    }
    if ($chapter_numbers['chapter'] <= 0) {
        $reason = !empty($chapter_numbers['ambiguous']) || !empty($filename_candidate['ambiguous'])
            ? 'Several numbers were found but the chapter number is ambiguous.'
            : 'No reliable chapter number was found in the folder or image filenames.';
        wp_send_json_error(array('message' => $reason . ' Rename the folder clearly or review its file naming.'));
    }

    $manga_id = manga_find_or_create_from_folder($resolved['series_path']);
    if (is_wp_error($manga_id)) {
        wp_send_json_error(array('message' => $manga_id->get_error_message()));
    }

    $chapter_title = $resolved['series_name'] . ' - ' . $chapter_label;
    $existing_by_title = get_posts(array(
        'post_type' => 'chapter',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'title' => $chapter_title,
        'meta_query' => array(array('key' => 'connected_manga_id', 'value' => $manga_id, 'compare' => '=')),
    ));
    if ($existing_by_title) {
        wp_send_json_error(array('message' => 'A chapter with this manga and chapter number already exists.'));
    }

    $job_id = wp_generate_uuid4();
    $job = array(
        'user_id' => get_current_user_id(),
        'path' => $path,
        'series_path' => $resolved['series_path'],
        'series_name' => $resolved['series_name'],
        'chapter_label' => $chapter_label,
        'chapter_title' => $chapter_title,
        'volume_number' => $chapter_numbers['volume'],
        'chapter_number' => $chapter_numbers['chapter'],
        'chapter_source' => $chapter_numbers['source'] ?? '',
        'chapter_confidence' => $chapter_numbers['confidence'] ?? 'none',
        'manga_id' => $manga_id,
        'files' => $files,
        'next_offset' => 0,
        'image_urls' => array(),
    );
    set_transient('manga_import_job_' . get_current_user_id() . '_' . md5($job_id), $job, 2 * HOUR_IN_SECONDS);
    wp_send_json_success(array('job_id' => $job_id, 'total_images' => count($files), 'chapter_number' => $chapter_numbers['chapter'], 'chapter_source' => $chapter_numbers['source'] ?? '', 'chapter_confidence' => $chapter_numbers['confidence'] ?? 'none'));
}
add_action('wp_ajax_manga_start_folder_import', 'manga_start_folder_import');

function manga_process_folder_import_batch() {
    check_ajax_referer('start_folder_import', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied.'), 403);
    }

    $job_id = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
    if (!wp_is_uuid($job_id)) {
        wp_send_json_error(array('message' => 'Invalid or expired import job.'));
    }
    $transient_key = 'manga_import_job_' . get_current_user_id() . '_' . md5($job_id);
    $job = get_transient($transient_key);
    if (!is_array($job) || (int) $job['user_id'] !== get_current_user_id()) {
        wp_send_json_error(array('message' => 'Import job expired. Start the chapter import again.'));
    }

    $batch_size = 200;
    $files = array_slice($job['files'], $job['next_offset'], $batch_size);
    foreach ($files as $source_path) {
        $image_url = manga_source_file_url($source_path);
        if (is_wp_error($image_url)) {
            delete_transient($transient_key);
            wp_send_json_error(array('message' => 'Import stopped at ' . basename($source_path) . ': ' . $image_url->get_error_message()));
        }
        $job['image_urls'][] = $image_url;
        $job['next_offset']++;
    }

    if ($job['next_offset'] < count($job['files'])) {
        set_transient($transient_key, $job, 2 * HOUR_IN_SECONDS);
        wp_send_json_success(array('done' => false, 'processed' => $job['next_offset']));
    }

    $resolved = manga_resolve_source_folder($job['path'], true);
    if (is_wp_error($resolved) || $resolved['series_path'] !== $job['series_path'] ||
        manga_supported_image_files($job['path']) !== $job['files']) {
        delete_transient($transient_key);
        wp_send_json_error(array('message' => 'Source files changed during import. Start again; no chapter was published.'));
    }
    $chapter_lock = manga_acquire_import_lock('chapter:' . $job['path']);
    if (!$chapter_lock) {
        wp_send_json_error(array('message' => 'This chapter is being imported by another request. Retry shortly.'));
    }
    $existing = get_posts(array(
        'post_type' => 'chapter',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'meta_key' => 'imported_from_path',
        'meta_value' => $job['path'],
    ));
    if ($existing || get_post_status($job['manga_id']) !== 'publish') {
        delete_option($chapter_lock);
        delete_transient($transient_key);
        wp_send_json_error(array('message' => 'The chapter or manga changed during import. Nothing new was published.'));
    }

    $chapter_id = wp_insert_post(array(
        'post_title' => $job['chapter_title'],
        'post_type' => 'chapter',
        'post_status' => 'draft',
        'meta_input' => array(
            'connected_manga_id' => $job['manga_id'],
            'volume_number' => $job['volume_number'],
            'chapter_number' => $job['chapter_number'],
            '_manga_chapter_parse_source' => $job['chapter_source'] ?? '',
            '_manga_chapter_parse_confidence' => $job['chapter_confidence'] ?? 'none',
            'image_links' => implode("\n", $job['image_urls']),
            'image_attachment_ids' => array(),
            'imported_from_path' => $job['path'],
            'import_date' => current_time('mysql'),
        ),
    ), true);
    if (is_wp_error($chapter_id) || !$chapter_id ||
        !manga_imported_chapter_valid($chapter_id, $job['path'], $job['manga_id'])) {
        delete_option($chapter_lock);
        delete_transient($transient_key);
        wp_send_json_error(array('message' => 'Chapter validation failed. Any incomplete draft was retained for recovery.'));
    }
    $published = wp_update_post(array('ID' => $chapter_id, 'post_status' => 'publish'), true);
    if (is_wp_error($published) || !$published || get_post_status($chapter_id) !== 'publish') {
        delete_option($chapter_lock);
        delete_transient($transient_key);
        wp_send_json_error(array('message' => 'Publishing failed. The draft was retained for recovery.'));
    }
    mark_chapter_as_imported($job['path']);
    delete_option($chapter_lock);
    delete_transient($transient_key);    wp_send_json_success(array(
        'done' => true,
        'processed' => $job['next_offset'],
        'message' => sprintf('Imported %s (%d images).', $job['chapter_label'], count($job['image_urls'])),
        'chapter_id' => $chapter_id,
    ));
}
add_action('wp_ajax_manga_process_folder_import_batch', 'manga_process_folder_import_batch');

// ============================================
// AJAX FILTERS FOR MANGA ARCHIVE (UPDATED SORTING)
// ============================================

// AJAX Filter for Homepage
function ajax_filter_manga_home() {
    check_ajax_referer('manga_home_filter_nonce', 'nonce');
    
    $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'recent';
    
    $args = array(
        'post_type' => 'manga',
        'posts_per_page' => -1
    );
    
    switch ($sort_by) {
        case 'alphabetical':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'recent':
        default:
            $args['orderby'] = 'modified';
            $args['order'] = 'DESC';
            break;
    }
    
    $manga_query = new WP_Query($args);
    output_manga_grid($manga_query->posts);
    wp_die();
}
add_action('wp_ajax_filter_manga_home', 'ajax_filter_manga_home');
add_action('wp_ajax_nopriv_filter_manga_home', 'ajax_filter_manga_home');

// Helper function to get latest chapter date for a manga
function get_latest_chapter_date($manga_id) {
    $latest_chapter = get_posts(array(
        'post_type' => 'chapter',
        'posts_per_page' => 1,
        'meta_key' => 'connected_manga_id',
        'meta_value' => $manga_id,
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    if (!empty($latest_chapter)) {
        return $latest_chapter[0]->post_date;
    }
    
    return get_the_date('Y-m-d H:i:s', $manga_id);
}

// Helper function to get sorted chapters for a manga (with volume support)
function get_sorted_chapters_for_manga($manga_id) {
    $chapters = get_posts(array(
        'post_type' => 'chapter',
        'posts_per_page' => -1,
        'meta_key' => 'connected_manga_id',
        'meta_value' => $manga_id,
        'post_status' => 'publish'
    ));
    
    // Sort by volume number first, then chapter number
    usort($chapters, function($a, $b) {
        $info_a = manga_parse_chapter_title(get_the_title($a->ID));
        $info_b = manga_parse_chapter_title(get_the_title($b->ID));
        $vol_a = (float) get_post_meta($a->ID, 'volume_number', true);
        $vol_b = (float) get_post_meta($b->ID, 'volume_number', true);
        $chap_a = (float) get_post_meta($a->ID, 'chapter_number', true);
        $chap_b = (float) get_post_meta($b->ID, 'chapter_number', true);
        $vol_a = $vol_a > 0 ? $vol_a : $info_a['volume'];
        $vol_b = $vol_b > 0 ? $vol_b : $info_b['volume'];
        $chap_a = $chap_a > 0 ? $chap_a : $info_a['chapter'];
        $chap_b = $chap_b > 0 ? $chap_b : $info_b['chapter'];
        
        if ($vol_a != $vol_b) {
            return $vol_b - $vol_a; // Higher volume first
        }
        if ($chap_a != $chap_b) {
            return $chap_b <=> $chap_a; // Higher chapter first within same volume
        }
        return $b->ID <=> $a->ID;
    });
    
    return $chapters;
}

// Helper function to output manga grid for custom sorted arrays
function output_manga_grid_custom($manga_list) {
    if (empty($manga_list)) {
        echo '<p class="no-results">No manga found.</p>';
        return;
    }
    
    echo '<div class="manga-grid">';
    foreach ($manga_list as $manga) {
        $manga_id = $manga->ID;
        $manga_title = get_the_title($manga_id);
        $manga_cover = manga_get_cover_url($manga_id, 'manga-cover-small');
        
        // Get sorted recent chapters
        $all_chapters = get_sorted_chapters_for_manga($manga_id);
        $recent_chapters = array_slice($all_chapters, 0, 3);
        ?>
        <div class="manga-item">
            <div class="manga-item-cover">
                <a href="<?php echo get_permalink($manga_id); ?>">
                    <img src="<?php echo esc_url($manga_cover); ?>" alt="<?php echo esc_attr($manga_title); ?>" loading="lazy" decoding="async">
                    <div class="manga-item-overlay">
                        <span class="view-details">View Details</span>
                    </div>
                </a>
                <?php if (!empty($recent_chapters)): 
                    $latest = $recent_chapters[0];
                    $display = manga_chapter_display_label($latest->ID);
                ?>
                    <div class="latest-chapter-badge">
                        <?php echo esc_html($display); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="manga-item-info">
                <h3 class="manga-item-title">
                    <a href="<?php echo get_permalink($manga_id); ?>"><?php echo esc_html($manga_title); ?></a>
                </h3>
                <?php if (!empty($recent_chapters)) : ?>
                    <div class="manga-item-chapters">
                        <?php foreach ($recent_chapters as $chapter) : 
                            $display = manga_chapter_display_label($chapter->ID);
                            $chapter_date = get_the_date('M j, Y', $chapter->ID);
                        ?>
                            <a href="<?php echo get_permalink($chapter->ID); ?>" class="chapter-link">
                                <span class="chapter-num"><?php echo esc_html($display); ?></span>
                                <span class="chapter-date"><?php echo esc_html($chapter_date); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="no-chapters">No chapters yet</div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    echo '</div>';
}

// AJAX Filter for Manga Archive
function ajax_filter_manga() {
    check_ajax_referer('manga_filter_nonce', 'nonce');
    
    $letter = isset($_POST['letter']) ? sanitize_text_field($_POST['letter']) : 'all';
    $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'alphabetical';
    
    $args = array(
        'post_type' => 'manga',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );
    
    // Handle sorting
    switch ($sort_by) {
        case 'alphabetical':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'updated':
            $all_manga = get_posts($args);
            usort($all_manga, function($a, $b) {
                $latest_chapter_a = get_latest_chapter_date($a->ID);
                $latest_chapter_b = get_latest_chapter_date($b->ID);
                return strtotime($latest_chapter_b) - strtotime($latest_chapter_a);
            });
            output_manga_grid_custom($all_manga);
            wp_die();
            break;
        case 'popular':
            $all_manga = get_posts($args);
            usort($all_manga, function($a, $b) {
                $chapters_a = count(get_posts(array(
                    'post_type' => 'chapter',
                    'meta_key' => 'connected_manga_id',
                    'meta_value' => $a->ID,
                    'posts_per_page' => -1
                )));
                $chapters_b = count(get_posts(array(
                    'post_type' => 'chapter',
                    'meta_key' => 'connected_manga_id',
                    'meta_value' => $b->ID,
                    'posts_per_page' => -1
                )));
                return $chapters_b - $chapters_a;
            });
            output_manga_grid_custom($all_manga);
            wp_die();
            break;
        default:
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
    }
    
    // Handle letter filtering - ONLY for alphabetical sort
    if ($sort_by === 'alphabetical' && $letter && $letter !== 'all') {
        $all_manga = get_posts($args);
        $filtered_manga = array();
        foreach ($all_manga as $manga) {
            $title = get_the_title($manga->ID);
            $first_letter = strtoupper(substr($title, 0, 1));
            if ($first_letter === strtoupper($letter)) {
                $filtered_manga[] = $manga;
            }
        }
        output_manga_grid_custom($filtered_manga);
        wp_die();
    } else {
        $query = new WP_Query($args);
        if ($query->have_posts()) {
            echo '<div class="manga-grid">';
            while ($query->have_posts()) {
                $query->the_post();
                get_template_part('template-parts/manga-card');
            }
            echo '</div>';
        } else {
            echo '<p class="no-results">No manga found.</p>';
        }
    }
    
    wp_die();
}
add_action('wp_ajax_filter_manga', 'ajax_filter_manga');
add_action('wp_ajax_nopriv_filter_manga', 'ajax_filter_manga');

// AJAX Filter Manga by Status
function ajax_filter_manga_by_status() {
    check_ajax_referer('manga_status_filter_nonce', 'nonce');
    
    $status_slug = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'all';
    
    $args = array(
        'post_type' => 'manga',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    );
    
    if ($status_slug !== 'all') {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'manga_status',
                'field' => 'slug',
                'terms' => $status_slug
            )
        );
    }
    
    $manga_query = new WP_Query($args);
    
    if ($manga_query->have_posts()) {
        echo '<div class="manga-grid">';
        while ($manga_query->have_posts()) {
            $manga_query->the_post();
            get_template_part('template-parts/manga-card');
        }
        echo '</div>';
    } else {
        echo '<p class="no-results">No manga found with this status.</p>';
    }
    
    wp_die();
}
add_action('wp_ajax_filter_manga_by_status', 'ajax_filter_manga_by_status');
add_action('wp_ajax_nopriv_filter_manga_by_status', 'ajax_filter_manga_by_status');

// AJAX Filter Manga by Genre
function ajax_filter_manga_by_genre() {
    check_ajax_referer('manga_genre_filter_nonce', 'nonce');
    
    $genre_slug = isset($_POST['genre']) ? sanitize_text_field($_POST['genre']) : '';
    
    $args = array(
        'post_type' => 'manga',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'tax_query' => array(
            array(
                'taxonomy' => 'genre',
                'field' => 'slug',
                'terms' => $genre_slug
            )
        )
    );
    
    $manga_query = new WP_Query($args);
    
    if ($manga_query->have_posts()) {
        echo '<div class="manga-grid">';
        while ($manga_query->have_posts()) {
            $manga_query->the_post();
            get_template_part('template-parts/manga-card');
        }
        echo '</div>';
    } else {
        echo '<p class="no-results">No manga found in this genre.</p>';
    }
    
    wp_die();
}
add_action('wp_ajax_filter_manga_by_genre', 'ajax_filter_manga_by_genre');
add_action('wp_ajax_nopriv_filter_manga_by_genre', 'ajax_filter_manga_by_genre');

// Helper Functions
function output_manga_grid($manga_list) {
    if (empty($manga_list)) {
        echo '<p class="no-results">No manga found.</p>';
        return;
    }
    
    echo '<div class="manga-grid">';
    foreach ($manga_list as $manga) {
        $manga_id = $manga->ID;
        $manga_title = get_the_title($manga_id);
        $manga_cover = manga_get_cover_url($manga_id, 'manga-cover-small');
        
        // Get sorted recent chapters
        $all_chapters = get_sorted_chapters_for_manga($manga_id);
        $recent_chapters = array_slice($all_chapters, 0, 3);
        ?>
        <div class="manga-item">
            <div class="manga-item-cover">
                <a href="<?php echo get_permalink($manga_id); ?>">
                    <img src="<?php echo esc_url($manga_cover); ?>" alt="<?php echo esc_attr($manga_title); ?>" loading="lazy" decoding="async">
                    <div class="manga-item-overlay">
                        <span class="view-details">View Details</span>
                    </div>
                </a>
                <?php if (!empty($recent_chapters)): 
                    $latest = $recent_chapters[0];
                    $display = manga_chapter_display_label($latest->ID);
                ?>
                    <div class="latest-chapter-badge">
                        <?php echo esc_html($display); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="manga-item-info">
                <h3 class="manga-item-title">
                    <a href="<?php echo get_permalink($manga_id); ?>"><?php echo esc_html($manga_title); ?></a>
                </h3>
                <?php if (!empty($recent_chapters)) : ?>
                    <div class="manga-item-chapters">
                        <?php foreach ($recent_chapters as $chapter) : 
                            $display = manga_chapter_display_label($chapter->ID);
                            $chapter_date = get_the_date('M j, Y', $chapter->ID);
                        ?>
                            <a href="<?php echo get_permalink($chapter->ID); ?>" class="chapter-link">
                                <span class="chapter-num"><?php echo esc_html($display); ?></span>
                                <span class="chapter-date"><?php echo esc_html($chapter_date); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="no-chapters">No chapters yet</div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    echo '</div>';
}

// Enqueue Scripts
function manga_theme_scripts() {
    wp_enqueue_style('manga-theme-style', get_stylesheet_uri(), array(), '6.2');
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'manga_theme_scripts');

// Register Widgets
function manga_theme_widgets_init() {
    register_sidebar(array(
        'name'          => __('Sidebar', 'manga-theme'),
        'id'            => 'sidebar-1',
        'description'   => __('Add widgets here.', 'manga-theme'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ));
}
add_action('widgets_init', 'manga_theme_widgets_init');

// Ensure chapter_number is saved
add_action('save_post_chapter', 'ensure_chapter_number_saved', 5, 2);
function ensure_chapter_number_saved($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($post_id)) {
        return;
    }
    
    $chapter_num = get_post_meta($post_id, 'chapter_number', true);
    $volume_num = get_post_meta($post_id, 'volume_number', true);
    
    $parsed = manga_parse_chapter_title($post->post_title);
    if ((float) $chapter_num <= 0 && $parsed['chapter'] > 0) {
        update_post_meta($post_id, 'chapter_number', $parsed['chapter']);
    }

    if ((float) $volume_num <= 0 && $parsed['volume'] > 0) {
        update_post_meta($post_id, 'volume_number', $parsed['volume']);
    }
}

?>
