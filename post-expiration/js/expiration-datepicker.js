document.addEventListener('DOMContentLoaded', function() {
    const expirationInput = document.getElementById('expiration_date');
    if (expirationInput) {
        flatpickr(expirationInput, {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
        });
    }
});