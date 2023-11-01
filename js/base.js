$(document).ready(function() {
    var _toasts = $('.toast-message');
    if (_toasts.length) {
        _toasts.toast();
    }

    $('.tooltip').popup();
});