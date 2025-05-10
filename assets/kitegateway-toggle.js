jQuery(document).ready(function ($) {
    // Add toggle button to api_secret field
    var $input = $('.kitegateway-api-secret');
    if ($input.length) {
        var $toggle = $('<button type="button" class="kitegateway-toggle-visibility dashicons dashicons-visibility" aria-label="Show API secret"></button>');
        $input.after($toggle);

        $toggle.on('click', function () {
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $toggle.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                $toggle.attr('aria-label', 'Hide API secret');
            } else {
                $input.attr('type', 'password');
                $toggle.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $toggle.attr('aria-label', 'Show API secret');
            }
        });
    }
});
