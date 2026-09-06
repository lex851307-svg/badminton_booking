const menuButton = document.querySelector('.menu-button');
const mainNav = document.querySelector('.main-nav');

if (menuButton && mainNav) {
    menuButton.addEventListener('click', function () {
        const isOpen = mainNav.classList.toggle('open');
        menuButton.setAttribute('aria-expanded', String(isOpen));
    });
}

const dateInput = document.querySelector('input[type="date"][name="date"]');

if (dateInput) {
    const now = new Date();
    const today = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 10);

    dateInput.min = today;
}

document.querySelectorAll('.date-filter-form input[type="date"]').forEach(function (input) {
    input.addEventListener('change', function () {
        input.form.submit();
    });
});

document.querySelectorAll('.multi-slot-form').forEach(function (form) {
    const checkboxes = form.querySelectorAll('input[name="slot_ids[]"]');
    const count = form.querySelector('.selected-slot-count');
    const submitButton = form.querySelector('.multi-slot-submit');

    function updateSelection() {
        const selected = form.querySelectorAll('input[name="slot_ids[]"]:checked').length;
        count.textContent = String(selected);
        submitButton.disabled = selected === 0;
    }

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelection);
    });
});

document.querySelectorAll('.confirm-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        const message = form.dataset.confirm || 'Bạn có chắc chắn muốn tiếp tục?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

const printButton = document.querySelector('.print-button');

if (printButton) {
    printButton.addEventListener('click', function () {
        window.print();
    });
}
