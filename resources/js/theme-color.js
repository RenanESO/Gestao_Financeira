function originalThemeColor(key) {
    // A nested theme scope reads DaisyUI defaults, without the user's root overrides.
    const scope = document.createElement('div');
    scope.dataset.theme = document.documentElement.dataset.theme || 'dark';
    scope.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none';
    const sample = document.createElement('span');
    sample.style.color = `var(--color-${key})`;
    scope.append(sample);
    document.body.append(scope);

    try {
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = 1;
        const context = canvas.getContext('2d', { colorSpace: 'srgb' });
        context.fillStyle = getComputedStyle(sample).color;
        context.fillRect(0, 0, 1, 1);
        const channels = context.getImageData(0, 0, 1, 1).data;

        return '#' + [...channels].slice(0, 3)
            .map((value) => value.toString(16).padStart(2, '0')).join('');
    } finally {
        scope.remove();
    }
}

export default function themeColor(key, binding) {
    return {
        color: binding,
        original: '',

        init() {
            this.original = originalThemeColor(key);
        },

        get displayedColor() {
            return this.color || this.original;
        },
    };
}
