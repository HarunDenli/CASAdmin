(function () {
    const form = document.getElementById('contact-form');
    const phone = document.getElementById('contact-phone');

    if (!form || !phone) {
        return;
    }

    function digitsOnly(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function isValidSaPhone(value) {
        let digits = digitsOnly(value);
        if (!digits) {
            return false;
        }
        if (digits.indexOf('0027') === 0) {
            digits = digits.slice(2);
        }
        if (/^0[1-9]\d{8}$/.test(digits)) {
            return true;
        }
        return /^27[1-9]\d{8}$/.test(digits);
    }

    function refreshPhoneValidity() {
        const value = phone.value.trim();
        if (value === '') {
            phone.setCustomValidity('Enter a valid phone number.');
            return;
        }
        if (!isValidSaPhone(value)) {
            phone.setCustomValidity('Enter a valid phone number.');
            return;
        }
        phone.setCustomValidity('');
    }

    phone.addEventListener('input', refreshPhoneValidity);
    phone.addEventListener('blur', refreshPhoneValidity);
    form.addEventListener('submit', refreshPhoneValidity);
    refreshPhoneValidity();

    const field = form.getAttribute('data-error-field');
    const message = form.getAttribute('data-error-message');
    if (field && message) {
        const target = form.querySelector('[name="' + field + '"]');
        if (target) {
            target.setCustomValidity(message);
            if (typeof target.reportValidity === 'function') {
                target.reportValidity();
            }
            target.addEventListener('input', function clearOnce() {
                target.setCustomValidity('');
                target.removeEventListener('input', clearOnce);
            });
        }
    }
})();
