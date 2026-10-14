<?php
get_header();

$popular_manga = get_posts(array(
    'post_type' => 'manga',
    'posts_per_page' => 5,
    'orderby' => array('comment_count' => 'DESC', 'date' => 'DESC', 'ID' => 'DESC'),
));
if (empty($popular_manga)) {
    $popular_manga = get_posts(array(
        'post_type' => 'manga',
        'posts_per_page' => 5,
        'orderby' => array('date' => 'DESC', 'ID' => 'DESC'),
    ));
}

$manga_query = new WP_Query(array(
    'post_type' => 'manga',
    'posts_per_page' => -1,
    'orderby' => 'modified',
    'order' => 'DESC',
    'no_found_rows' => true,
));
$manga_ids = array_merge(wp_list_pluck($popular_manga, 'ID'), wp_list_pluck($manga_query->posts, 'ID'));
$chapter_groups = manga_chapters_by_manga_ids($manga_ids);
manga_sort_posts_by_latest_chapter($manga_query->posts, $chapter_groups);
?>

<div class="homepage-content">
    <!-- Featured Manga Section -->
    <div class="featured-section">
        <div class="featured-header">
            <h2>Popular New Titles</h2>
            <div class="featured-nav">
                <button class="featured-prev" id="featuredPrev">◀</button>
                <button class="featured-next" id="featuredNext">▶</button>
            </div>
        </div>
        
        <div class="featured-container">
            <div class="featured-slider" id="featuredSlider">
                <?php
                foreach ($popular_manga as $index => $manga):
                    $manga_id = $manga->ID;
                    $manga_title = get_the_title($manga_id);
                    $manga_cover = manga_get_cover_url($manga_id, 'medium');
                    
                    // Get genres
                    $genres = wp_get_post_terms($manga_id, 'genre');
                    $primary_genre = !empty($genres) ? strtoupper($genres[0]->name) : 'MANGA';
                    $all_genres = array();
                    foreach ($genres as $genre) {
                        $all_genres[] = $genre->name;
                    }
                    
                    // Get description
                    $description = $manga->post_excerpt ?: wp_trim_words($manga->post_content, 60, '...');
                    
                    // Get chapter count
                    $chapters = $chapter_groups[$manga_id] ?? array();
                    $chapter_count = count($chapters);
                    $latest_chapter = !empty($chapters) ? $chapters[0] : null;
                    
                    // Get formatted latest chapter display
                    $latest_display = '';
                    if ($latest_chapter) {
                        $latest_display = manga_chapter_display_label($latest_chapter->ID);
                    }
                    ?>
                    <div class="featured-item">
                        <div class="featured-cover">
                            <img <?php if ($index === 0): ?>src="<?php echo esc_url($manga_cover); ?>" loading="eager" fetchpriority="high"<?php else: ?>data-src="<?php echo esc_url($manga_cover); ?>" loading="lazy"<?php endif; ?> alt="<?php echo esc_attr($manga_title); ?>" decoding="async">
                            <div class="featured-overlay">
                                <a href="<?php echo get_permalink($manga_id); ?>" class="featured-btn">View Details</a>
                            </div>
                        </div>
                        <div class="featured-info">
                            <h3 class="featured-title"><?php echo esc_html($manga_title); ?></h3>
                            <div class="featured-genre"><?php echo esc_html($primary_genre); ?></div>
                            <div class="featured-stats">
                                <span>★ <?php echo $chapter_count; ?> Chapters</span>
                            </div>
                            <p class="featured-description"><?php echo esc_html($description); ?></p>
                            <?php if (!empty($all_genres)): ?>
                                <div class="featured-tags">
                                    <?php foreach (array_slice($all_genres, 0, 3) as $genre): ?>
                                        <span class="featured-tag"><?php echo esc_html($genre); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="featured-footer">
                                <span class="featured-chapter"><?php echo $latest_display ?: 'Coming Soon'; ?></span>
                                <a href="<?php echo $latest_chapter ? get_permalink($latest_chapter->ID) : get_permalink($manga_id); ?>" class="featured-read">Read →</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Slider Indicators -->
        <div class="featured-dots" id="featuredDots"></div>
    </div>

    <div class="content-wrapper">
        <div class="main-content">
            <div class="section-header">
                <h2>Latest Updates</h2>
                <div class="sort-options">
                    <button class="sort-btn active" data-sort="recent">Recently Updated</button>
                    <button class="sort-btn" data-sort="alphabetical">A-Z</button>
                </div>
            </div>
            
            <div id="manga-container">
                <?php
                if ($manga_query->have_posts()) :
                    echo '<div class="manga-grid">';
                    while ($manga_query->have_posts()) : $manga_query->the_post();
                        $manga_id = get_the_ID();
                        $manga_title = get_the_title();
                        $manga_cover = manga_get_cover_url(get_the_ID(), 'manga-cover-small');
                        
                        // Get chapters sorted by volume and chapter number
                        $all_chapters = manga_sort_chapter_posts($chapter_groups[$manga_id] ?? array());
                        $recent_chapters = array_slice($all_chapters, 0, 3);
                        ?>
                        <div class="manga-item">
                            <div class="manga-item-cover">
                                <a href="<?php the_permalink(); ?>">
                                    <img src="<?php echo esc_url($manga_cover); ?>" alt="<?php echo esc_attr($manga_title); ?>" loading="lazy" decoding="async">
                                    <div class="manga-item-overlay">
                                        <span class="view-details">View Details</span>
                                    </div>
                                </a>
                                <?php if (!empty($recent_chapters)): 
                                    $latest = $recent_chapters[0];
                                    $badge_text = manga_chapter_display_label($latest->ID);
                                ?>
                                    <div class="latest-chapter-badge">
                                        <?php echo esc_html($badge_text); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="manga-item-info">
                                <h3 class="manga-item-title">
                                    <a href="<?php the_permalink(); ?>"><?php echo esc_html($manga_title); ?></a>
                                </h3>
                                <?php if (!empty($recent_chapters)) : ?>
                                    <div class="manga-item-chapters">
                                        <?php foreach ($recent_chapters as $chapter) : 
                                            $display_text = manga_chapter_display_label($chapter->ID);
                                            $chapter_date = get_the_date('M j, Y', $chapter->ID);
                                        ?>
                                            <a href="<?php echo get_permalink($chapter->ID); ?>" class="chapter-link">
                                                <span class="chapter-num"><?php echo esc_html($display_text); ?></span>
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
                    endwhile;
                    echo '</div>';
                    wp_reset_postdata();
                else :
                    echo '<p class="no-results">No manga found. Create your first manga series!</p>';
                endif;
                ?>
            </div>
        </div>
        
        <div class="sidebar">
            <div class="widget">
                <h3>Popular Genres</h3>
                <div class="genre-list">
                    <?php
                    $genres = get_terms(array(
                        'taxonomy' => 'genre',
                        'hide_empty' => true,
                        'number' => 10
                    ));
                    if (!empty($genres) && !is_wp_error($genres)) {
                        foreach ($genres as $genre) {
                            echo '<a href="' . get_term_link($genre) . '" class="genre-item" data-genre="' . esc_attr($genre->slug) . '">' . esc_html($genre->name) . '</a>';
                        }
                    } else {
                        echo '<p>No genres found.</p>';
                    }
                    ?>
                </div>
            </div>
            
            <div class="widget">
                <h3>Reading Status</h3>
                <div class="status-list" id="statusList">
                    <?php
                    $statuses = get_terms(array(
                        'taxonomy' => 'manga_status',
                        'hide_empty' => false
                    ));
                    if (!empty($statuses) && !is_wp_error($statuses)) {
                        echo '<div class="status-filter active" data-status="all">All</div>';
                        foreach ($statuses as $status) {
                            $count = $status->count;
                            echo '<div class="status-filter" data-status="' . esc_attr($status->slug) . '">';
                            echo '<span class="status-name">' . esc_html($status->name) . '</span>';
                            echo '<span class="status-count">' . $count . '</span>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
jQuery(document).ready(function($) {
    // Featured Slider
    var currentSlide = 0;
    var totalSlides = $('.featured-item').length;

    function loadFeaturedImage(index) {
        var image = $('.featured-item').eq(index).find('img[data-src]')[0];
        if (image) {
            image.src = image.dataset.src;
            image.removeAttribute('data-src');
        }
    }
    
    function updateSlider() {
        if (!totalSlides) return;
        loadFeaturedImage(currentSlide);
        loadFeaturedImage((currentSlide + 1) % totalSlides);
        var newTransform = -currentSlide * 100 + '%';
        $('.featured-slider').css('transform', 'translateX(' + newTransform + ')');
        
        $('.featured-dot').removeClass('active');
        $('.featured-dot[data-slide="' + currentSlide + '"]').addClass('active');
    }
    
    function nextSlide() {
        if (currentSlide < totalSlides - 1) {
            currentSlide++;
            updateSlider();
        } else {
            currentSlide = 0;
            updateSlider();
        }
    }
    
    function prevSlide() {
        if (currentSlide > 0) {
            currentSlide--;
            updateSlider();
        } else {
            currentSlide = totalSlides - 1;
            updateSlider();
        }
    }
    
    // Create dots
    for (var i = 0; i < totalSlides; i++) {
        var dotClass = i === 0 ? 'featured-dot active' : 'featured-dot';
        $('#featuredDots').append('<div class="' + dotClass + '" data-slide="' + i + '"></div>');
    }
    if (totalSlides > 1) loadFeaturedImage(1);
    
    $('#featuredNext').on('click', nextSlide);
    $('#featuredPrev').on('click', prevSlide);
    
    $(document).on('click', '.featured-dot', function() {
        currentSlide = parseInt($(this).data('slide'));
        updateSlider();
    });
    
    // Auto-play
    var autoPlay = setInterval(nextSlide, 6000);
    $('.featured-section').hover(function() {
        clearInterval(autoPlay);
    }, function() {
        autoPlay = setInterval(nextSlide, 6000);
    });
    
    // Status Filter Functionality
    var currentStatus = 'all';
    
    function filterByStatus(status) {
        currentStatus = status;
        
        // Update active class on status filters
        $('.status-filter').removeClass('active');
        $('.status-filter[data-status="' + status + '"]').addClass('active');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'filter_manga_by_status',
                status: status,
                nonce: '<?php echo wp_create_nonce("manga_status_filter_nonce"); ?>'
            },
            beforeSend: function() {
                $('#manga-container').html('<div class="loading-container"><div class="spinner"></div></div>');
            },
            success: function(response) {
                $('#manga-container').html(response);
                // Add clear filter indicator if not showing all
                if (status !== 'all') {
                    var statusName = $('.status-filter[data-status="' + status + '"] .status-name').text();
                    $('#manga-container').prepend('<div class="filter-active-badge">Showing: ' + statusName + ' <button class="clear-filter-btn" id="clearFilter">✕ Clear</button></div>');
                    
                    $('#clearFilter').on('click', function() {
                        filterByStatus('all');
                    });
                }
            },
            error: function() {
                $('#manga-container').html('<p class="no-results">Error loading manga. Please refresh the page.</p>');
            }
        });
    }
    
    $('.status-filter').click(function() {
        var status = $(this).data('status');
        filterByStatus(status);
    });
    
    // Genre Filter Functionality
    $('.genre-item').click(function(e) {
        e.preventDefault();
        var genreSlug = $(this).data('genre');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'filter_manga_by_genre',
                genre: genreSlug,
                nonce: '<?php echo wp_create_nonce("manga_genre_filter_nonce"); ?>'
            },
            beforeSend: function() {
                $('#manga-container').html('<div class="loading-container"><div class="spinner"></div></div>');
            },
            success: function(response) {
                $('#manga-container').html(response);
                var genreName = $(this).text();
                $('#manga-container').prepend('<div class="filter-active-badge">Showing: ' + genreName + ' <button class="clear-filter-btn" id="clearGenreFilter">✕ Clear</button></div>');
                
                $('#clearGenreFilter').on('click', function() {
                    location.reload();
                });
            }.bind(this),
            error: function() {
                $('#manga-container').html('<p class="no-results">Error loading manga. Please refresh the page.</p>');
            }
        });
    });
    
    $('.sort-btn').click(function() {
        $('.sort-btn').removeClass('active');
        $(this).addClass('active');
        
        var sortBy = $(this).data('sort');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'filter_manga_home',
                sort_by: sortBy,
                nonce: '<?php echo wp_create_nonce("manga_home_filter_nonce"); ?>'
            },
            beforeSend: function() {
                $('#manga-container').html('<div class="loading-container"><div class="spinner"></div></div>');
            },
            success: function(response) {
                $('#manga-container').html(response);
            },
            error: function() {
                $('#manga-container').html('<p class="no-results">Error loading manga. Please refresh the page.</p>');
            }
        });
    });
});
</script>

<?php get_footer(); ?>
