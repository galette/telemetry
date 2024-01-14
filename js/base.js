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
            if (writedarkcss == true) {
                _writedarkcss();
            }
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

function writeDarkTheme() {
    DarkReader.enable({
        brightness: 100,
        contrast: 90,
        sepia: 10
    });
    return DarkReader.exportGeneratedCSS();
}

function _writedarkcss() {
    writeDarkTheme().then(function (cssdata) {
        $.ajax({
            url: darkcss_path,
            method: 'post',
            data: cssdata.replaceAll('themes/galette/assets', 'ui/themes/galette/assets'),
            success: function (res) {
                console.log('Dark theme CSS stored');
            },
            error: function () {
                console.log('Error storing dark theme CSS');
            }
        });
    });
}
