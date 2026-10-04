$(document).ready(function() {
    var _toasts = $('.toast-message');
    if (_toasts.length) {
        _toasts.toast();
    }

    $('.tooltip').popup({
        variation: 'inverted',
        inline: false,
        addTouchEvents: false,
    });

    $('.ui.dropdown').dropdown();

    function _darkMode() {
        var _dark_enabled = Cookies.get('galettetelemetry_dark_mode');
        var _cookie_value = 1;
        if (_dark_enabled && _dark_enabled == 1) {
            var _cookie_value = 0;
        }

        $('.darkmode').on('click', function(e) {
            e.preventDefault();
            Cookies.set(
                'galettetelemetry_dark_mode',
                _cookie_value,
                {
                    expires: 365,
                    path: '/'
                }
            );
            window.location.reload();
        });

        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
                if (event.matches) {
                    _cookie_value = 1;
                }
                Cookies.set(
                    'galettetelemetry_dark_mode',
                    _cookie_value,
                    {
                        expires: 365,
                        path: '/'
                    }
                );
                window.location.reload();
            });
        }
    }
    _darkMode();
});
