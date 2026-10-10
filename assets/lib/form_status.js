const timers = new WeakMap();

export function clearStatusTimer(element) {
    clearTimeout(timers.get(element));
    timers.delete(element);
}

export function showStatus(element, message, state = 'success') {
    clearStatusTimer(element);
    element.dataset.state = state;
    element.textContent = message || '';
    if (message && state === 'success') {
        timers.set(element, setTimeout(() => {
            element.textContent = '';
            timers.delete(element);
        }, 3500));
    }
}
