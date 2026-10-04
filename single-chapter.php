<?php get_header(); ?>

<?php while (have_posts()) : the_post(); 
    $chapter_num = get_post_meta(get_the_ID(), 'chapter_number', true);
    $volume_num = get_post_meta(get_the_ID(), 'volume_number', true);
    $manga_id = get_post_meta(get_the_ID(), 'connected_manga_id', true);
    
    $parsed_chapter = manga_parse_chapter_title(get_the_title());
    if (empty($chapter_num)) {
        $chapter_num = $parsed_chapter['chapter'] > 0 ? $parsed_chapter['chapter'] : '?';
    }
    if (empty($volume_num) && $parsed_chapter['volume'] > 0) {
        $volume_num = $parsed_chapter['volume'];
    }
    
    $manga_title = $manga_id ? get_the_title($manga_id) : 'Unknown Manga';
    $images = manga_get_chapter_image_urls(get_the_ID());
    
    // Descending order means the previous chapter is a smaller index and the next chapter a larger one.
    $all_chapters = $manga_id ? get_sorted_chapters_for_manga($manga_id) : array();
    
    $current_index = -1;
    foreach ($all_chapters as $index => $chapter) {
        if ($chapter->ID == get_the_ID()) {
            $current_index = $index;
            break;
        }
    }
    
    $prev_chapter = ($current_index >= 0 && isset($all_chapters[$current_index + 1])) ? $all_chapters[$current_index + 1] : null;
    $next_chapter = ($current_index > 0 && isset($all_chapters[$current_index - 1])) ? $all_chapters[$current_index - 1] : null;
    
    $manga_url = $manga_id ? get_permalink($manga_id) : '#';
    $total_pages = count($images);
    
    // Get formatted display text for current chapter
    $display_chapter_text = manga_chapter_display_label(get_the_ID());
?>

<div class="manga-reader-container" id="mangaReaderContainer" data-current-bg="dark">
    <!-- Top Navigation Bar -->
    <div class="reader-top-bar" id="readerTopBar">
        <div class="reader-top-bar-left">
            <a href="<?php echo esc_url($manga_url); ?>" class="reader-nav-btn" id="backToMangaBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
                <span>Back</span>
            </a>
            <div class="chapter-info-compact">
                <span class="compact-title"><?php echo esc_html($manga_title); ?></span>
                <span class="compact-chapter"><?php echo esc_html($display_chapter_text); ?></span>
            </div>
        </div>
        
        <div class="reader-top-bar-center">
            <div class="chapter-nav">
                <?php if ($prev_chapter): ?>
                    <a href="<?php echo get_permalink($prev_chapter->ID); ?>" class="reader-nav-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                        Prev
                    </a>
                <?php else: ?>
                    <span class="reader-nav-btn disabled" style="opacity:0.5; cursor:not-allowed;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                        Prev
                    </span>
                <?php endif; ?>
                
                <div class="chapter-selector-wrapper">
                    <select id="chapterSelector" class="chapter-selector">
                        <option value="">-- Select Chapter --</option>
                        <?php if (!empty($all_chapters)): ?>
                            <?php 
                            $chapters_for_dropdown = array_reverse($all_chapters);
                            foreach ($chapters_for_dropdown as $chapter): 
                                $chap_num = get_post_meta($chapter->ID, 'chapter_number', true);
                                $vol_num = get_post_meta($chapter->ID, 'volume_number', true);
                                $parsed_option_chapter = manga_parse_chapter_title($chapter->post_title);
                                if (empty($chap_num)) {
                                    $chap_num = $parsed_option_chapter['chapter'] > 0 ? $parsed_option_chapter['chapter'] : '?';
                                }
                                if (empty($vol_num) && $parsed_option_chapter['volume'] > 0) {
                                    $vol_num = $parsed_option_chapter['volume'];
                                }
                                $selected = ($chapter->ID == get_the_ID()) ? 'selected' : '';
                                $display_text = manga_chapter_display_label($chapter->ID);
                            ?>
                                <option value="<?php echo get_permalink($chapter->ID); ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html($display_text); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No chapters available</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <?php if ($next_chapter): ?>
                    <a href="<?php echo get_permalink($next_chapter->ID); ?>" class="reader-nav-btn">
                        Next
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </a>
                <?php else: ?>
                    <span class="reader-nav-btn disabled" style="opacity:0.5; cursor:not-allowed;">
                        Next
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18l6-6-6-6"/>
                        </svg>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="reader-top-bar-right">
            <button class="reader-settings-btn" id="settingsToggleBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
            </button>
            <button class="reader-fullscreen-btn" id="fullscreenToggleBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
                </svg>
            </button>
        </div>
    </div>
    
    <!-- Progress Bar -->
    <div class="reader-progress-bar">
        <div class="reader-progress-fill" id="readerProgressFill"></div>
    </div>
    
    <!-- Main Reader Area -->
    <div class="reader-main-content" id="readerMainContent">
        <div class="reader-viewer" id="readerViewer">
            <?php if (!empty($images)): ?>
                <?php foreach ($images as $index => $image): ?>
                    <div class="reader-page" data-page="<?php echo $index; ?>">
                        <img <?php if (0 === $index): ?>src="<?php echo esc_url($image); ?>" loading="eager" fetchpriority="high"<?php else: ?>data-src="<?php echo esc_url($image); ?>" loading="lazy"<?php endif; ?> alt="Page <?php echo $index + 1; ?>" decoding="async" data-page-index="<?php echo $index; ?>">
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-images-message">
                    <p>No images found for this chapter.</p>
                    <a href="<?php echo admin_url('post.php?post=' . get_the_ID() . '&action=edit'); ?>" class="btn-primary" style="display: inline-block; padding: 10px 20px; background: #0078d4; color: white; border-radius: 6px; text-decoration: none;">Edit Chapter</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Navigation Arrows -->
    <button type="button" class="nav-arrow nav-arrow-left" id="navArrowLeft" aria-label="Previous page">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 18l-6-6 6-6"/>
        </svg>
    </button>
    <button type="button" class="nav-arrow nav-arrow-right" id="navArrowRight" aria-label="Next page">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18l6-6-6-6"/>
        </svg>
    </button>
    
    <!-- Page Indicator (hidden by default, shown only in paged mode) -->
    <div class="page-indicator" id="pageIndicator" style="display: none;">
        <span id="currentPageDisplay">1</span> / <span id="totalPagesDisplay"><?php echo $total_pages; ?></span>
    </div>
    
    <!-- Settings Panel -->
    <div class="reader-settings-panel" id="readerSettingsPanel">
        <div class="settings-panel-header">
            <h3>Reader Settings</h3>
            <button class="close-settings-btn" id="closeSettingsPanelBtn">×</button>
        </div>
        <div class="settings-panel-content">
            <div class="settings-section">
                <label>Reading Mode</label>
                <div class="settings-buttons-group">
                    <button class="settings-btn active" data-setting="mode" data-value="paged">
                        <span class="btn-icon">📄</span> Paged
                    </button>
                    <button class="settings-btn" data-setting="mode" data-value="longstrip">
                        <span class="btn-icon">📋</span> Long Strip
                    </button>
                    <button class="settings-btn" data-setting="mode" data-value="webtoon">
                        <span class="btn-icon">📱</span> Webtoon
                    </button>
                </div>
            </div>
            
            <div class="settings-section">
                <label>Image Fit</label>
                <div class="settings-buttons-group">
                    <button class="settings-btn active" data-setting="fit" data-value="contain">
                        <span class="btn-icon">🖼️</span> Contain
                    </button>
                    <button class="settings-btn" data-setting="fit" data-value="cover">
                        <span class="btn-icon">📷</span> Cover
                    </button>
                    <button class="settings-btn" data-setting="fit" data-value="original">
                        <span class="btn-icon">🔍</span> Original
                    </button>
                </div>
            </div>
            
            <div class="settings-section">
                <label>Scale</label>
                <div class="settings-buttons-group">
                    <button class="settings-btn" data-setting="scale" data-value="100">
                        <span class="btn-icon">📏</span> 100%
                    </button>
                    <button class="settings-btn active" data-setting="scale" data-value="auto">
                        <span class="btn-icon">🔄</span> Auto
                    </button>
                    <button class="settings-btn" data-setting="scale" data-value="width">
                        <span class="btn-icon">📐</span> Fit to Width
                    </button>
                </div>
            </div>
            
            <div class="settings-section">
                <label>Background</label>
                <div class="settings-buttons-group">
                    <button class="settings-btn active" data-setting="bg" data-value="dark">
                        <span class="btn-icon">🌙</span> Dark
                    </button>
                    <button class="settings-btn" data-setting="bg" data-value="light">
                        <span class="btn-icon">☀️</span> Light
                    </button>
                    <button class="settings-btn" data-setting="bg" data-value="sepia">
                        <span class="btn-icon">📖</span> Sepia
                    </button>
                </div>
            </div>
            
            <div class="settings-section">
                <label>Navigation</label>
                <div class="settings-buttons-group">
                    <button class="settings-btn active" data-setting="nav" data-value="click">
                        <span class="btn-icon">🖱️</span> Click to Advance
                    </button>
                    <button class="settings-btn" data-setting="nav" data-value="buttons">
                        <span class="btn-icon">⬅️➡️</span> Buttons Only
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Zoom Controls -->
    <div class="reader-zoom-controls" id="readerZoomControls">
        <button class="zoom-btn" id="zoomOutBtn" title="Zoom Out">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                <line x1="8" y1="11" x2="14" y2="11"/>
            </svg>
        </button>
        <span class="zoom-level" id="zoomLevelDisplay">100%</span>
        <button class="zoom-btn" id="zoomInBtn" title="Zoom In">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                <line x1="11" y1="8" x2="11" y2="14"/>
                <line x1="8" y1="11" x2="14" y2="11"/>
            </svg>
        </button>
        <button class="zoom-btn" id="resetZoomBtn" title="Reset Zoom">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="16"/>
                <line x1="8" y1="12" x2="16" y2="12"/>
            </svg>
        </button>
    </div>
</div>

<style>
/* ============================================
   READER STYLES - FULL BACKGROUND MODE SUPPORT
   ============================================ */

/* Base reader container styles */
.manga-reader-container {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: background-color 0.3s ease;
}

/* ============================================
   DARK MODE (DEFAULT)
   ============================================ */
.manga-reader-container[data-current-bg="dark"] {
    background: #0a0a0f;
}

.manga-reader-container[data-current-bg="dark"] .reader-top-bar {
    background: rgba(16, 16, 20, 0.95);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.manga-reader-container[data-current-bg="dark"] .reader-nav-btn,
.manga-reader-container[data-current-bg="dark"] .reader-settings-btn,
.manga-reader-container[data-current-bg="dark"] .reader-fullscreen-btn,
.manga-reader-container[data-current-bg="dark"] .chapter-selector {
    background: rgba(255, 255, 255, 0.08);
    color: white;
}

.manga-reader-container[data-current-bg="dark"] .compact-title,
.manga-reader-container[data-current-bg="dark"] .chapter-info-compact span {
    color: white;
}

.manga-reader-container[data-current-bg="dark"] .compact-chapter {
    color: rgba(255, 255, 255, 0.6);
}

.manga-reader-container[data-current-bg="dark"] .reader-settings-panel {
    background: rgba(20, 20, 30, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.manga-reader-container[data-current-bg="dark"] .settings-panel-header h3 {
    color: white;
}

.manga-reader-container[data-current-bg="dark"] .settings-section label {
    color: rgba(255, 255, 255, 0.7);
}

.manga-reader-container[data-current-bg="dark"] .settings-btn {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.9);
}

.manga-reader-container[data-current-bg="dark"] .page-indicator {
    background: rgba(0, 0, 0, 0.7);
    color: white;
}

/* ============================================
   LIGHT MODE
   ============================================ */
.manga-reader-container[data-current-bg="light"] {
    background: #f5f5f5;
}

.manga-reader-container[data-current-bg="light"] .reader-top-bar {
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.manga-reader-container[data-current-bg="light"] .reader-nav-btn,
.manga-reader-container[data-current-bg="light"] .reader-settings-btn,
.manga-reader-container[data-current-bg="light"] .reader-fullscreen-btn,
.manga-reader-container[data-current-bg="light"] .chapter-selector {
    background: rgba(0, 0, 0, 0.05);
    color: #333;
}

.manga-reader-container[data-current-bg="light"] .compact-title,
.manga-reader-container[data-current-bg="light"] .chapter-info-compact span {
    color: #333;
}

.manga-reader-container[data-current-bg="light"] .compact-chapter {
    color: #666;
}

.manga-reader-container[data-current-bg="light"] .reader-settings-panel {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.manga-reader-container[data-current-bg="light"] .settings-panel-header h3 {
    color: #333;
}

.manga-reader-container[data-current-bg="light"] .settings-section label {
    color: #666;
}

.manga-reader-container[data-current-bg="light"] .settings-btn {
    background: rgba(0, 0, 0, 0.05);
    color: #333;
}

.manga-reader-container[data-current-bg="light"] .page-indicator {
    background: rgba(0, 0, 0, 0.7);
    color: white;
}

/* ============================================
   SEPIA MODE
   ============================================ */
.manga-reader-container[data-current-bg="sepia"] {
    background: #f4ecd8;
}

.manga-reader-container[data-current-bg="sepia"] .reader-top-bar {
    background: rgba(244, 236, 216, 0.95);
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.manga-reader-container[data-current-bg="sepia"] .reader-nav-btn,
.manga-reader-container[data-current-bg="sepia"] .reader-settings-btn,
.manga-reader-container[data-current-bg="sepia"] .reader-fullscreen-btn,
.manga-reader-container[data-current-bg="sepia"] .chapter-selector {
    background: rgba(0, 0, 0, 0.08);
    color: #5b4636;
}

.manga-reader-container[data-current-bg="sepia"] .compact-title,
.manga-reader-container[data-current-bg="sepia"] .chapter-info-compact span {
    color: #5b4636;
}

.manga-reader-container[data-current-bg="sepia"] .compact-chapter {
    color: #8b7355;
}

.manga-reader-container[data-current-bg="sepia"] .reader-settings-panel {
    background: rgba(244, 236, 216, 0.95);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.manga-reader-container[data-current-bg="sepia"] .settings-panel-header h3 {
    color: #5b4636;
}

.manga-reader-container[data-current-bg="sepia"] .settings-section label {
    color: #8b7355;
}

.manga-reader-container[data-current-bg="sepia"] .settings-btn {
    background: rgba(0, 0, 0, 0.08);
    color: #5b4636;
}

/* ============================================
   SETTINGS BUTTON STYLES
   ============================================ */
.settings-btn {
    position: relative;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.2s cubic-bezier(0.2, 0.9, 0.4, 1.1);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.settings-btn .btn-icon {
    font-size: 14px;
}

.settings-btn.active {
    background: #0078d4 !important;
    color: white !important;
    box-shadow: 0 2px 8px rgba(0, 120, 212, 0.3);
}

.settings-btn.active::after {
    content: '✓';
    margin-left: 4px;
    font-size: 11px;
    font-weight: bold;
}

.settings-btn:hover {
    transform: translateY(-1px);
}

/* ============================================
   SHARED READER STYLES
   ============================================ */
.reader-top-bar {
    padding: 12px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 101;
    max-height: 80px;
    overflow: hidden;
    transition: max-height 0.3s ease, padding 0.3s ease, opacity 0.2s ease, transform 0.3s ease;
}

.reader-top-bar.is-hidden {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    opacity: 0;
    transform: translateY(-100%);
    pointer-events: none;
}

.reader-nav-btn {
    display: flex;
    align-items: center;
    min-height: 44px;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.2s;
}

.reader-settings-btn,
.reader-fullscreen-btn {
    border: none;
    border-radius: 8px;
    min-width: 44px;
    min-height: 44px;
    padding: 8px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.reader-progress-bar {
    position: absolute;
    top: 61px;
    left: 0;
    right: 0;
    height: 2px;
    z-index: 100;
    transition: top 0.3s ease;
}

.reader-progress-fill {
    width: 0%;
    height: 100%;
    background: #0078d4;
    transition: width 0.3s;
}

.reader-top-bar.is-hidden + .reader-progress-bar {
    top: 0;
}

.reader-main-content {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 20px;
}

.reader-main-content.landscape-pan {
    overflow-x: auto;
    overscroll-behavior-x: contain;
}

.reader-viewer {
    max-width: 1000px;
    margin: 0 auto;
}

.reader-page {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 20px;
    /* Reserve space for deferred images so the observer does not treat collapsed pages as visible. */
    min-height: 70vh;
    cursor: pointer;
}

.reader-page img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto;
    border-radius: 4px;
}

/* Loaded continuous pages must use their rendered image height, not the loading placeholder. */
.reader-main-content.continuous-mode .reader-page.has-loaded-image {
    min-height: 0;
}

.reader-main-content.continuous-mode .reader-page {
    justify-content: center;
}

.reader-main-content.continuous-mode .reader-page img {
    flex: none;
    max-width: none;
}

.reader-main-content.longstrip-content .reader-page {
    margin-bottom: 0;
}

.reader-main-content.continuous-mode .reader-page.is-landscape {
    position: static;
    left: auto;
    width: 100%;
    max-width: none;
    transform: none;
    justify-content: center;
}

.reader-main-content.continuous-mode .reader-page.is-landscape img {
    flex: none;
    max-width: none;
}

.reader-main-content.longstrip-content {
    padding: 0;
}

.reader-main-content.continuous-mode {
    overflow-x: auto;
    scrollbar-gutter: stable both-edges;
}

.reader-main-content.webtoon-content {
    padding: 0;
    overflow-x: auto;
}

.reader-viewer.webtoon-mode {
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
}

.reader-viewer.webtoon-mode .reader-page {
    width: 100%;
    margin: 0;
    cursor: default;
}

.reader-viewer.webtoon-mode .reader-page img {
    flex: 0 0 auto;
    max-width: none;
    max-height: none;
    height: auto;
    display: block;
    margin: 0 auto;
    border-radius: 0;
}

/* Navigation Arrows - Hidden on mobile */
.nav-arrow {
    position: fixed;
    top: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    transform: translateY(-50%);
    backdrop-filter: blur(10px);
    border-radius: 50%;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s;
    z-index: 100;
    opacity: 0;
}

.manga-reader-container:hover .nav-arrow,
.manga-reader-container.show-page-arrows .nav-arrow {
    opacity: 1;
}

.nav-arrow-left {
    left: 20px;
}

.nav-arrow-right {
    right: 20px;
}

/* Show page controls on mobile only when tap-to-advance is disabled. */
@media (max-width: 768px) {
    .nav-arrow {
        display: none !important;
        opacity: 0 !important;
        visibility: hidden !important;
    }

    .manga-reader-container.show-page-arrows .nav-arrow {
        display: flex !important;
        opacity: 1 !important;
        visibility: visible !important;
        min-width: 48px;
        min-height: 48px;
        padding: 10px;
        background: rgba(0, 0, 0, 0.8);
    }

    .manga-reader-container.show-page-arrows .nav-arrow-left {
        left: 8px;
    }

    .manga-reader-container.show-page-arrows .nav-arrow-right {
        right: 8px;
    }
}

.page-indicator {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    backdrop-filter: blur(10px);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    z-index: 100;
    pointer-events: none;
}

.reader-settings-panel {
    position: fixed;
    top: 50%;
    right: -300px;
    transform: translateY(-50%);
    width: 300px;
    backdrop-filter: blur(20px);
    border-radius: 12px;
    z-index: 1000;
    transition: right 0.3s;
    overflow: hidden;
    max-height: calc(100vh - 24px);
    max-height: calc(100dvh - 24px);
}

.reader-settings-panel.open {
    right: 20px;
}

.settings-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.settings-panel-header h3 {
    margin: 0;
    font-size: 16px;
}

.close-settings-btn {
    background: none;
    border: none;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.2s;
}

.close-settings-btn:hover {
    background: rgba(0, 0, 0, 0.1);
}

.settings-panel-content {
    padding: 20px;
    max-height: calc(100vh - 88px);
    max-height: calc(100dvh - 88px);
    overflow-y: auto;
    overscroll-behavior: contain;
}

.settings-section {
    margin-bottom: 20px;
}

.settings-section label {
    display: block;
    font-size: 11px;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.settings-section label::before {
    content: '⚙️';
    margin-right: 6px;
    font-size: 11px;
}

.settings-buttons-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Zoom Controls */
.reader-zoom-controls {
    position: fixed;
    bottom: 20px;
    right: 20px;
    backdrop-filter: blur(10px);
    padding: 8px;
    border-radius: 40px;
    display: flex;
    gap: 8px;
    z-index: 100;
    background: rgba(0, 0, 0, 0.7);
}

/* Keep zoom controls usable on touch screens, including portrait pages. */
@media (max-width: 768px) {
    .reader-zoom-controls {
        right: 8px;
        bottom: 8px;
        gap: 4px;
        padding: 4px;
    }

    .zoom-btn {
        min-width: 36px;
        min-height: 36px;
        padding: 6px;
    }

    .zoom-level {
        padding: 0 4px;
    }
}

@media (max-width: 480px) {
    .page-indicator {
        left: 8px;
        bottom: 12px;
        transform: none;
    }
}

.zoom-btn {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.zoom-btn:hover {
    background: #0078d4;
    transform: scale(1.05);
}

.zoom-level {
    color: white;
    font-size: 12px;
    padding: 0 8px;
    display: flex;
    align-items: center;
}

.no-images-message {
    text-align: center;
    padding: 60px;
}

/* Responsive */
@media (max-width: 768px) {
    .reader-top-bar {
        padding: 10px 16px;
    }

    .reader-top-bar .chapter-nav {
        flex-direction: row;
        gap: 6px;
    }
    
    .reader-nav-btn span {
        display: none;
    }
    
    .chapter-info-compact {
        display: none;
    }
    
    .chapter-selector {
        min-width: 100px;
        font-size: 11px;
    }
    
    .reader-settings-panel.open {
        right: 10px;
        width: min(280px, calc(100vw - 20px));
    }
    
    .settings-btn {
        padding: 6px 10px;
        font-size: 11px;
    }
}

@media (max-height: 500px) and (orientation: landscape) {
    .reader-settings-panel.open {
        top: 8px;
        right: 8px;
        width: min(320px, calc(100vw - 16px));
        max-height: calc(100vh - 16px);
        max-height: calc(100dvh - 16px);
        transform: none;
    }

    .settings-panel-content {
        max-height: calc(100vh - 72px);
        max-height: calc(100dvh - 72px);
        padding: 12px 16px;
    }

    .settings-section {
        margin-bottom: 12px;
    }
}

@media (max-width: 480px) {
    .reader-top-bar {
        flex-wrap: wrap;
        gap: 4px;
        padding: 6px 8px;
        max-height: 108px;
    }

    .reader-top-bar.is-hidden {
        max-height: 0;
    }

    .reader-top-bar-left,
    .reader-top-bar-right {
        gap: 4px;
    }

    .reader-top-bar-center {
        order: 3;
        flex: 0 0 100%;
        justify-content: center;
        min-width: 0;
    }

    .chapter-nav {
        gap: 4px;
    }

    .chapter-nav .reader-nav-btn {
        font-size: 0;
        gap: 0;
        padding: 0 8px;
    }

    .chapter-selector {
        min-width: 0;
        width: min(180px, 52vw);
        min-height: 44px;
    }

    .reader-progress-bar {
        top: 106px;
    }

    .reader-top-bar.is-hidden + .reader-progress-bar {
        top: 0;
    }

    .settings-buttons-group {
        gap: 6px;
    }
    
    .settings-btn {
        padding: 5px 8px;
        font-size: 10px;
    }
    
    .settings-btn .btn-icon {
        font-size: 11px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // DOM Elements
    const container = document.getElementById('mangaReaderContainer');
    const readerTopBar = document.getElementById('readerTopBar');
    const pages = document.querySelectorAll('.reader-page');
    const totalPages = pages.length;
    const progressFill = document.getElementById('readerProgressFill');
    const currentPageDisplay = document.getElementById('currentPageDisplay');
    const totalPagesDisplay = document.getElementById('totalPagesDisplay');
    const pageIndicator = document.getElementById('pageIndicator');
    const prevArrow = document.getElementById('navArrowLeft');
    const nextArrow = document.getElementById('navArrowRight');
    const settingsPanel = document.getElementById('readerSettingsPanel');
    const settingsToggle = document.getElementById('settingsToggleBtn');
    const closeSettings = document.getElementById('closeSettingsPanelBtn');
    const fullscreenBtn = document.getElementById('fullscreenToggleBtn');
    const chapterSelector = document.getElementById('chapterSelector');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const resetZoomBtn = document.getElementById('resetZoomBtn');
    const zoomLevelDisplay = document.getElementById('zoomLevelDisplay');
    const scrollContainer = document.getElementById('readerMainContent');
    let readerImageObserver = null;
    function loadReaderImage(index, highPriority = false) {
        const image = pages[index]?.querySelector('img[data-src]');
        if (!image?.dataset.src) return;
        const source = image.dataset.src;
        image.setAttribute('fetchpriority', highPriority ? 'high' : 'low');
        image.loading = 'eager';
        image.removeAttribute('data-src');
        image.src = source;
        readerImageObserver?.unobserve(image);
    }

    // Fetch the active image and exactly the next two images, even when pages are hidden.
    function preloadNextImages(index) {
        if (!totalPages || !Number.isFinite(index)) return;
        const first = Math.max(0, Math.min(totalPages - 1, Math.trunc(index)));
        for (let offset = 0; offset <= 2 && first + offset < totalPages; offset++) {
            loadReaderImage(first + offset, offset === 0);
        }
    }

    // Fast scrolling may expose a page before the scroll handler runs.
    if (typeof window.IntersectionObserver === 'function') {
        readerImageObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) loadReaderImage(Number(entry.target.dataset.pageIndex), true);
            });
        }, { root: scrollContainer, rootMargin: '0px', threshold: 0.01 });
        document.querySelectorAll('.reader-page img[data-src]').forEach(image => readerImageObserver.observe(image));
    }
    
    // State
    let currentPage = 0;
    let currentZoom = 1;
    let readingMode = 'paged';
    let imageFit = 'contain';
    let scaleMode = 'auto';
    let navMode = 'click';
    let currentBgMode = 'dark';
    let isScrolling = false;
    let topBarManuallyVisible = false;
    let activeLandscapePage = -1;

    pages.forEach((page, index) => {
        const image = page.querySelector('img');
        if (!image) return;
        const updateOrientation = () => {
            if (!image.naturalWidth || !image.naturalHeight) return;
            page.classList.add('has-loaded-image');
            page.classList.toggle('is-landscape', image.naturalWidth > image.naturalHeight);
            if (index === currentPage || isContinuousMode()) applyScale();
        };
        image.addEventListener('load', updateOrientation);
        if (image.complete && image.naturalWidth) updateOrientation();
    });
    
    // Check if mobile view
    function isMobile() {
        return window.innerWidth <= 768;
    }

    function isContinuousMode() {
        return readingMode === 'longstrip' || readingMode === 'webtoon';
    }

    function updatePageArrowVisibility() {
        container.classList.toggle('show-page-arrows', navMode === 'buttons');
    }

    function updateReaderTopBarVisibility() {
        if (!readerTopBar || !scrollContainer) return;
        let atStart = false;
        let atEnd = false;

        if (isContinuousMode()) {
            const maxScroll = scrollContainer.scrollHeight - scrollContainer.clientHeight;
            atStart = scrollContainer.scrollTop <= 24;
            atEnd = maxScroll > 24 && maxScroll - scrollContainer.scrollTop <= 24;
        } else {
            atStart = currentPage <= 0;
            atEnd = totalPages > 0 && currentPage >= totalPages - 1;
        }

        readerTopBar.classList.toggle('is-hidden', !(atStart || atEnd || topBarManuallyVisible));
    }
    
    // Update total pages display
    if (totalPagesDisplay && totalPages > 0) {
        totalPagesDisplay.textContent = totalPages;
    }
    
    // Show/hide page indicator based on reading mode
    function updatePageIndicatorVisibility() {
        if (pageIndicator) {
            if (!isContinuousMode()) {
                pageIndicator.style.display = 'block';
            } else {
                pageIndicator.style.display = 'none';
            }
        }
    }
    
    // Apply background mode
    function applyBackgroundMode(mode) {
        currentBgMode = mode;
        if (container) {
            container.setAttribute('data-current-bg', mode);
        }
        
        if (mode === 'light') {
            document.body.classList.remove('dark-mode', 'sepia-mode');
        } else if (mode === 'sepia') {
            document.body.classList.remove('dark-mode');
            document.body.classList.add('sepia-mode');
        } else {
            document.body.classList.remove('sepia-mode');
            document.body.classList.add('dark-mode');
        }
        
        localStorage.setItem('readerBgMode', mode);
    }
    
    // Apply image fit
    function applyImageFit() {
        pages.forEach(page => {
            const img = page.querySelector('img');
            if (img) {
                img.style.objectFit = imageFit === 'original' ? 'initial' : imageFit;
                img.style.height = 'auto';
            }
        });
    }
    
    function updateLandscapeControls() {
        let visibleLandscape = false;
        if (isContinuousMode()) {
            const viewport = scrollContainer.getBoundingClientRect();
            visibleLandscape = Array.from(pages).some(page => {
                if (!page.classList.contains('is-landscape')) return false;
                const bounds = page.getBoundingClientRect();
                return bounds.bottom > viewport.top && bounds.top < viewport.bottom;
            });
        } else {
            visibleLandscape = pages[currentPage]?.classList.contains('is-landscape') || false;
        }
        container.classList.toggle('has-landscape-page', visibleLandscape);
    }

    function landscapeFitWidth(image, availableWidth, availableHeight) {
        const aspectRatio = image.naturalWidth / image.naturalHeight;
        const minimumScale = window.innerWidth <= 480 ? 1.5 : window.innerWidth <= 768 ? 1.25 : 1;
        // Keep wide spreads readable on narrow screens without enlarging beyond the source.
        const readingWidth = Math.max(availableWidth * minimumScale, availableHeight * 0.55 * aspectRatio);
        return Math.min(image.naturalWidth, availableWidth * 4, readingWidth);
    }

    // Wide spreads use real layout width, so the reader can pan instead of clipping a transform.
    function updateLandscapeLayout() {
        const viewer = document.getElementById('readerViewer');
        const page = pages[currentPage];
        const image = page?.querySelector('img');
        const isLandscapePage = readingMode === 'paged' && page?.classList.contains('is-landscape') && image?.naturalWidth;
        const wasPagedLandscape = viewer.classList.contains('landscape-page-active');

        updateLandscapeControls();
        scrollContainer.classList.toggle('landscape-pan', Boolean(isLandscapePage));
        viewer.classList.toggle('landscape-page-active', Boolean(isLandscapePage));
        if (!isLandscapePage) {
            viewer.style.width = '';
            viewer.style.maxWidth = '';
            if (readingMode === 'paged' || wasPagedLandscape) scrollContainer.scrollLeft = 0;
            activeLandscapePage = -1;
            return;
        }

        const previousRange = Math.max(0, scrollContainer.scrollWidth - scrollContainer.clientWidth);
        const position = activeLandscapePage === currentPage && previousRange > 0
            ? scrollContainer.scrollLeft / previousRange : 0.5;
        const styles = getComputedStyle(scrollContainer);
        const availableWidth = Math.max(1, scrollContainer.clientWidth - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight));
        const availableHeight = Math.max(1, scrollContainer.clientHeight - parseFloat(styles.paddingTop) - parseFloat(styles.paddingBottom));
        const fitWidth = landscapeFitWidth(image, availableWidth, availableHeight);
        const baseWidth = scaleMode === 'width' ? availableWidth : imageFit === 'original' ? image.naturalWidth : fitWidth;
        const zoom = scaleMode === 'auto' ? currentZoom : 1;

        viewer.style.width = Math.round(baseWidth * zoom) + 'px';
        viewer.style.maxWidth = 'none';
        image.style.width = '100%';
        image.style.maxWidth = 'none';
        image.style.height = 'auto';
        image.style.transform = 'none';
        activeLandscapePage = currentPage;
        const nextRange = Math.max(0, scrollContainer.scrollWidth - scrollContainer.clientWidth);
        scrollContainer.scrollLeft = nextRange * position;
    }

    // Size images in normal flow in every mode; wide continuous spreads may exceed the viewport.
    function updateReaderImageLayout(scaleValue) {
        const continuous = isContinuousMode();
        const current = pages[currentPage];
        if (!continuous && current?.classList.contains('is-landscape') && current.querySelector('img')?.naturalWidth) return;
        const viewer = document.getElementById('readerViewer');
        const styles = getComputedStyle(scrollContainer);
        const padding = (parseFloat(styles.paddingLeft) || 0) + (parseFloat(styles.paddingRight) || 0);
        const availableWidth = Math.max(1, scrollContainer.clientWidth - padding);
        const availableHeight = Math.max(1, scrollContainer.clientHeight - (parseFloat(styles.paddingTop) || 0) - (parseFloat(styles.paddingBottom) || 0));
        const baseWidth = Math.min(readingMode === 'webtoon' ? 900 : 1000, availableWidth);
        const activePages = continuous ? Array.from(pages) : current ? [current] : [];
        const widths = activePages.map(page => {
            const image = page.querySelector('img');
            const naturalWidth = image?.naturalWidth || 0;
            const isLandscape = page.classList.contains('is-landscape') && naturalWidth && image.naturalHeight;
            const fittedWidth = scaleMode === 'width' ? baseWidth
                : isLandscape ? imageFit === 'original' ? naturalWidth : landscapeFitWidth(image, availableWidth, availableHeight)
                : imageFit === 'original' && naturalWidth ? naturalWidth
                : imageFit === 'contain' && naturalWidth ? Math.min(baseWidth, naturalWidth)
                : baseWidth;
            return Math.max(1, Math.round(fittedWidth * scaleValue));
        });
        viewer.style.width = Math.ceil(widths.reduce((max, width) => Math.max(max, width), baseWidth)) + 'px';
        viewer.style.maxWidth = 'none';

        activePages.forEach((page, index) => {
            const image = page.querySelector('img');
            page.style.width = '100%';
            if (image) image.style.width = widths[index] + 'px';
        });
        const nextRange = Math.max(0, scrollContainer.scrollWidth - scrollContainer.clientWidth);
        scrollContainer.classList.toggle('landscape-pan', nextRange > 1);
        scrollContainer.scrollLeft = nextRange / 2;
    }
    
    // Keep the point being read under the same viewport position while images resize.
    function captureReadingAnchor() {
        const viewport = scrollContainer.getBoundingClientRect();
        const x = viewport.left + viewport.width / 2;
        const y = viewport.top + viewport.height / 2;
        const visiblePages = isContinuousMode() ? Array.from(pages) : [pages[currentPage]];
        const page = visiblePages.find(candidate => {
            if (!candidate) return false;
            const bounds = candidate.getBoundingClientRect();
            return bounds.top <= y && bounds.bottom >= y;
        }) || visiblePages.reduce((nearest, candidate) => {
            if (!candidate) return nearest;
            const bounds = candidate.getBoundingClientRect();
            const distance = Math.max(bounds.top - y, y - bounds.bottom, 0);
            return distance < nearest.distance ? { page: candidate, distance } : nearest;
        }, { page: null, distance: Infinity }).page;
        if (!page) return null;

        const image = page.querySelector('img');
        const imageBounds = image?.getBoundingClientRect();
        const target = imageBounds?.width && imageBounds.height
            && imageBounds.left <= x && imageBounds.right >= x
            && imageBounds.top <= y && imageBounds.bottom >= y ? image : page;
        const bounds = target.getBoundingClientRect();
        if (!bounds.width || !bounds.height) return null;
        const horizontalRange = Math.max(0, scrollContainer.scrollWidth - scrollContainer.clientWidth);
        return {
            target, x, y,
            fractionX: (x - bounds.left) / bounds.width,
            fractionY: (y - bounds.top) / bounds.height,
            preserveX: horizontalRange > 1 && Math.abs(scrollContainer.scrollLeft - horizontalRange / 2) > Math.max(8, horizontalRange * 0.15)
        };
    }

    function restoreReadingAnchor(anchor) {
        if (!anchor?.target.isConnected) return;
        let bounds = anchor.target.getBoundingClientRect();
        if (!bounds.width || !bounds.height) return;
        const verticalShift = bounds.top + anchor.fractionY * bounds.height - anchor.y;
        if (Math.abs(verticalShift) > 0.5) scrollContainer.scrollTop += verticalShift;
        if (anchor.preserveX) {
            bounds = anchor.target.getBoundingClientRect();
            const horizontalShift = bounds.left + anchor.fractionX * bounds.width - anchor.x;
            if (Math.abs(horizontalShift) > 0.5) scrollContainer.scrollLeft += horizontalShift;
        }
    }

    // Apply scale/zoom
    function applyScale() {
        const readingAnchor = captureReadingAnchor();
        const scaleValue = scaleMode === 'auto' ? currentZoom : 1;
        pages.forEach(page => {
            const img = page.querySelector('img');
            if (img) {
                img.style.height = 'auto';
                img.style.transform = 'none';
            }
        });
        if (zoomLevelDisplay) {
            zoomLevelDisplay.textContent = scaleMode === 'width' ? 'Fit Width' : Math.round(scaleValue * 100) + '%';
        }
        updateLandscapeLayout();
        updateReaderImageLayout(scaleValue);
        restoreReadingAnchor(readingAnchor);
    }
    
    // Update page display based on mode (PAGED MODE)
    function updatePageDisplay() {
        updatePageArrowVisibility();
        if (isContinuousMode()) {
            const viewer = document.getElementById('readerViewer');
            viewer.classList.toggle('webtoon-mode', readingMode === 'webtoon');
            scrollContainer.classList.toggle('webtoon-content', readingMode === 'webtoon');
            scrollContainer.classList.toggle('longstrip-content', readingMode === 'longstrip');
            scrollContainer.classList.add('continuous-mode');
            pages.forEach(page => {
                page.style.display = 'flex';
                page.style.width = '';
            });
            updateProgress();
            updatePageIndicatorVisibility();
        } else {
            const viewer = document.getElementById('readerViewer');
            viewer.classList.remove('webtoon-mode');
            scrollContainer.classList.remove('webtoon-content');
            scrollContainer.classList.remove('longstrip-content');
            scrollContainer.classList.remove('continuous-mode');
            pages.forEach((page, index) => {
                page.style.display = index === currentPage ? 'flex' : 'none';
                page.style.width = '';
            });
            updateProgress();
            updatePageIndicator();
            updatePageIndicatorVisibility();
            
            // Scroll to top of current page
            if (pages[currentPage]) {
                pages[currentPage].scrollIntoView({ behavior: 'instant', block: 'start' });
            }
        }
        preloadNextImages(isContinuousMode() ? continuousPageIndex() : currentPage);
        applyScale();
    }
    
    // Update progress bar
    function updateProgress() {
        if (isContinuousMode() && scrollContainer) {
            const scrollTop = scrollContainer.scrollTop;
            const scrollHeight = scrollContainer.scrollHeight - scrollContainer.clientHeight;
            if (scrollHeight > 0) {
                const progress = (scrollTop / scrollHeight) * 100;
                if (progressFill) progressFill.style.width = progress + '%';
            }
        } else if (totalPages > 0) {
            const progress = ((currentPage + 1) / totalPages) * 100;
            if (progressFill) progressFill.style.width = progress + '%';
        }
        updateReaderTopBarVisibility();
    }
    
    // Update page indicator
    function updatePageIndicator() {
        if (currentPageDisplay && totalPages > 0) {
            currentPageDisplay.textContent = currentPage + 1;
        }
    }
    
    // Navigate to next page (Paged mode only)
    function nextPage() {
        if (isContinuousMode()) {
            const next = pages[continuousPageIndex() + 1];
            if (next) next.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
            return;
        }

        if (currentPage < totalPages - 1) {
            currentPage++;
            updatePageDisplay();
        }
    }
    
    // Navigate to previous page (Paged mode only)
    function prevPage() {
        if (isContinuousMode()) {
            const previous = pages[continuousPageIndex() - 1];
            if (previous) previous.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
            return;
        }

        if (currentPage > 0) {
            currentPage--;
            updatePageDisplay();
        }
    }
    
    function continuousPageIndex() {
        const top = scrollContainer.getBoundingClientRect().top + 2;
        let index = 0;
        pages.forEach((page, i) => {
            if (page.getBoundingClientRect().top <= top) index = i;
        });
        return index;
    }

    // LONG STRIP MODE: Navigate to next image when clicking
    function longStripNextImage(clickedPage) {
        if (!isContinuousMode()) return;
        
        const allPages = Array.from(pages);
        const currentIndex = allPages.indexOf(clickedPage);
        
        if (currentIndex < allPages.length - 1) {
            const nextPage = allPages[currentIndex + 1];
            nextPage.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });
        }
    }
    
    // Set reading mode
    function setReadingMode(mode) {
        readingMode = mode;
        updatePageDisplay();
        applyImageFit();
        applyScale();
        
        localStorage.setItem('readerReadingMode', mode);
    }
    
    // Set image fit
    function setImageFitMode(fit) {
        imageFit = fit;
        activeLandscapePage = -1;
        applyImageFit();
        applyScale();
        localStorage.setItem('readerImageFit', fit);
    }
    
    // Set scale mode
    function setScaleMode(mode) {
        scaleMode = mode;
        if (mode === 'auto' || mode === '100') {
            currentZoom = 1;
            localStorage.setItem('readerZoomLevel', '1');
        }
        applyScale();
        localStorage.setItem('readerScaleMode', mode);
    }
    
    // Zoom functions
    function zoomIn() {
        if (scaleMode !== 'auto') {
            setScaleMode('auto');
            document.querySelectorAll('[data-setting="scale"]').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.value === 'auto') {
                    btn.classList.add('active');
                }
            });
        }
        if (currentZoom < 2.5) {
            currentZoom += 0.25;
            localStorage.setItem('readerZoomLevel', String(currentZoom));
            applyScale();
        }
    }
    
    function zoomOut() {
        if (scaleMode !== 'auto') {
            setScaleMode('auto');
            document.querySelectorAll('[data-setting="scale"]').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.value === 'auto') {
                    btn.classList.add('active');
                }
            });
        }
        if (currentZoom > 0.5) {
            currentZoom -= 0.25;
            localStorage.setItem('readerZoomLevel', String(currentZoom));
            applyScale();
        }
    }
    
    function resetZoom() {
        currentZoom = 1;
        localStorage.setItem('readerZoomLevel', '1');
        setScaleMode('auto');
        document.querySelectorAll('[data-setting="scale"]').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.value === 'auto') {
                btn.classList.add('active');
            }
        });
    }
    
    // Bind once across viewport changes; CSS controls mobile visibility.
    if (prevArrow) prevArrow.addEventListener('click', prevPage);
    if (nextArrow) nextArrow.addEventListener('click', nextPage);
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (isMobile()) return; // Disable keyboard navigation on mobile
        
        if (!isContinuousMode()) {
            if (e.key === 'ArrowLeft') {
                prevPage();
                e.preventDefault();
            } else if (e.key === 'ArrowRight') {
                nextPage();
                e.preventDefault();
            }
        } else {
            if (e.key === 'ArrowDown' && scrollContainer) {
                scrollContainer.scrollBy({ top: window.innerHeight * 0.8, behavior: 'smooth' });
                e.preventDefault();
            } else if (e.key === 'ArrowUp' && scrollContainer) {
                scrollContainer.scrollBy({ top: -window.innerHeight * 0.8, behavior: 'smooth' });
                e.preventDefault();
            }
        }
        
        if (e.key === 'f' || e.key === 'F') {
            if (fullscreenBtn) fullscreenBtn.click();
            e.preventDefault();
        } else if (e.key === 's' || e.key === 'S') {
            if (settingsToggle) settingsToggle.click();
            e.preventDefault();
        } else if (e.key === 'Escape') {
            if (settingsPanel && settingsPanel.classList.contains('open')) {
                settingsPanel.classList.remove('open');
            }
        }
    });
    
    // Settings panel
    if (settingsToggle) {
        settingsToggle.addEventListener('click', function() {
            settingsPanel.classList.toggle('open');
        });
    }
    
    if (closeSettings) {
        closeSettings.addEventListener('click', function() {
            settingsPanel.classList.remove('open');
        });
    }
    
    // Click outside to close settings
    document.addEventListener('click', function(e) {
        if (settingsPanel && settingsPanel.classList.contains('open')) {
            if (!settingsPanel.contains(e.target) && !settingsToggle.contains(e.target)) {
                settingsPanel.classList.remove('open');
            }
        }
    });
    
    // Fullscreen
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', function() {
            const containerElem = document.querySelector('.manga-reader-container');
            if (!document.fullscreenElement) {
                containerElem.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        });
    }
    
    // Chapter selector
    if (chapterSelector && chapterSelector.options.length > 1) {
        chapterSelector.addEventListener('change', function() {
            if (this.value) {
                window.location.href = this.value;
            }
        });
    }
    
    if (zoomInBtn) zoomInBtn.addEventListener('click', zoomIn);
    if (zoomOutBtn) zoomOutBtn.addEventListener('click', zoomOut);
    if (resetZoomBtn) resetZoomBtn.addEventListener('click', resetZoom);
    
    // IMAGE CLICK HANDLING - Works for both modes
    pages.forEach(page => {
        page.addEventListener('click', function(e) {
            // Only advance when the image itself is clicked.
            if (navMode === 'click' && e.target.closest('img')) {
                e.stopPropagation();
                
                if (isContinuousMode()) {
                    // Long strip mode: scroll to next image
                    const allPages = Array.from(pages);
                    const currentIdx = allPages.indexOf(this);
                    if (currentIdx < allPages.length - 1) {
                        allPages[currentIdx + 1].scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start',
                            inline: 'nearest'
                        });
                    }
                } else {
                    nextPage();
                }
            }
        });
    });
    
    // Toggle the top bar only from empty reader space, never from an image or control.
    if (scrollContainer && readerTopBar) {
        scrollContainer.addEventListener('click', function(e) {
            if (e.target.closest('img, .reader-top-bar, .reader-settings-panel, .reader-zoom-controls, .nav-arrow, .page-indicator')) return;
            topBarManuallyVisible = readerTopBar.classList.contains('is-hidden');
            updateReaderTopBarVisibility();
        });
    }

    // Coalesce scroll work to one progress/page update per animation frame.
    if (scrollContainer) {
        scrollContainer.addEventListener('scroll', function() {
            topBarManuallyVisible = false;
            if (isScrolling) return;

            isScrolling = true;
            requestAnimationFrame(() => {
                isScrolling = false;
                if (isContinuousMode()) {
                    updateProgress();
                    updateLandscapeControls();
                    preloadNextImages(continuousPageIndex());
                } else {
                    // A paged reader has one visible page; scrolling within a wide image must not change it.
                    updateReaderTopBarVisibility();
                }
            });
        }, { passive: true });
    }
    
    // Settings button handlers
    document.querySelectorAll('[data-setting]').forEach(btn => {
        btn.addEventListener('click', function() {
            const setting = this.dataset.setting;
            const value = this.dataset.value;
            
            document.querySelectorAll(`[data-setting="${setting}"]`).forEach(b => {
                b.classList.remove('active');
            });
            this.classList.add('active');
            
            switch(setting) {
                case 'mode':
                    setReadingMode(value);
                    break;
                case 'fit':
                    setImageFitMode(value);
                    break;
                case 'scale':
                    setScaleMode(value);
                    break;
                case 'bg':
                    applyBackgroundMode(value);
                    break;
                case 'nav':
                    navMode = value;
                    localStorage.setItem('readerNavMode', value);
                    updatePageArrowVisibility();
                    break;
            }
        });
    });
    
    // Load saved settings
    const savedMode = localStorage.getItem('readerReadingMode');
    const savedReadingMode = savedMode === 'comics' ? 'webtoon' : savedMode;
    const savedImageFit = localStorage.getItem('readerImageFit');
    const savedScaleMode = localStorage.getItem('readerScaleMode');
    const savedBgMode = localStorage.getItem('readerBgMode');
    const savedNavMode = localStorage.getItem('readerNavMode');
    const savedZoomLevel = parseFloat(localStorage.getItem('readerZoomLevel'));

    if (Number.isFinite(savedZoomLevel)) {
        currentZoom = Math.min(2.5, Math.max(0.5, savedZoomLevel));
    }
    
    if (['paged', 'longstrip', 'webtoon'].includes(savedReadingMode)) {
        setReadingMode(savedReadingMode);
        document.querySelectorAll('[data-setting="mode"]').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-setting="mode"][data-value="${savedReadingMode}"]`)?.classList.add('active');
    }
    
    if (savedImageFit && savedImageFit !== 'contain') {
        setImageFitMode(savedImageFit);
        document.querySelector(`[data-setting="fit"][data-value="${savedImageFit}"]`)?.classList.add('active');
        document.querySelector(`[data-setting="fit"][data-value="contain"]`)?.classList.remove('active');
    } else {
        applyImageFit();
    }
    
    if (savedScaleMode && savedScaleMode !== 'auto') {
        setScaleMode(savedScaleMode);
        document.querySelector(`[data-setting="scale"][data-value="${savedScaleMode}"]`)?.classList.add('active');
        document.querySelector(`[data-setting="scale"][data-value="auto"]`)?.classList.remove('active');
    } else {
        applyScale();
    }
    
    if (savedBgMode && savedBgMode !== 'dark') {
        applyBackgroundMode(savedBgMode);
        document.querySelector(`[data-setting="bg"][data-value="${savedBgMode}"]`)?.classList.add('active');
        document.querySelector(`[data-setting="bg"][data-value="dark"]`)?.classList.remove('active');
    } else {
        applyBackgroundMode('dark');
    }
    
    if (savedNavMode) {
        navMode = savedNavMode;
        document.querySelector(`[data-setting="nav"][data-value="${savedNavMode}"]`)?.classList.add('active');
        document.querySelector(`[data-setting="nav"][data-value="click"]`)?.classList.remove('active');
    }
    
    // Initial setup
    if (pages.length > 0) {
        updatePageDisplay();
    }
    
    // Recalculate responsive reader layout after orientation/viewport changes.
    window.addEventListener('resize', function() {
        if (readingMode === 'webtoon') {
            updatePageDisplay();
        } else {
            applyScale();
        }
        updateReaderTopBarVisibility();
    });

    // The top bar also changes the reader height without a window resize.
    if ('ResizeObserver' in window) {
        const readerSizeObserver = new ResizeObserver(() => {
            applyScale();
        });
        readerSizeObserver.observe(scrollContainer);
    }
    
    // Set initial navigation visibility at the start of the chapter.
    updateReaderTopBarVisibility();

    // Hide body overflow
    document.body.style.overflow = 'hidden';
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    document.body.style.overflow = '';
});
</script>

<?php endwhile; ?>

<?php get_footer(); ?>
