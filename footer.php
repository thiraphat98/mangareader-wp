        </div>
    </main>
    
    <footer class="site-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>About</h3>
                    <p><?php bloginfo('description'); ?></p>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'footer',
                        'menu_class' => 'footer-menu',
                        'container' => false,
                        'depth' => 1,
                        'fallback_cb' => false
                    ));
                    ?>
                </div>
                
                <div class="footer-section">
                    <h3>Browse</h3>
                    <ul>
                        <li><a href="<?php echo get_post_type_archive_link('manga'); ?>">All Manga</a></li>
                        <li><a href="<?php echo home_url('/'); ?>">Latest Chapters</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
</div>

<script>
jQuery(function($) {
    // Submenus remain available when the viewport changes after page load.
    $(document).on('click', '.primary-menu .menu-item-has-children > a', function(event) {
        if (window.innerWidth > 768) return;
        event.preventDefault();
        $(this).parent().toggleClass('active');
    });
});
</script>

<?php wp_footer(); ?>
</body>
</html>