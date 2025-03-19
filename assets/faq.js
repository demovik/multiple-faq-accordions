jQuery(document).ready(function($) {
    $('.mfaq-accordion').each(function() {
        var $accordion = $(this);
        var options = $accordion.data('options') || {};

        // Convert string options to proper types
        options.collapsible = (options.collapsible === 'true' || options.collapsible === true);

        if (options.active === 'false' || options.active === false) {
            options.active = false; // All collapsed
        } else {
            options.active = parseInt(options.active, 10);
            if (isNaN(options.active) || options.active < 0) {
                options.active = false; // Default to collapsed if invalid
            }
        }

        options.animate = parseInt(options.animate, 10) || options.animate;

        // Initialize jQuery UI Accordion
        $accordion.accordion(options);
    });
});