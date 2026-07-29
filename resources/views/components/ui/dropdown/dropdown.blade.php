@props([
    'defaultClasses' => [
        'fixed',
        'transition-all',
        'ease-in-out',
        'min-w-72',
        'rounded-2xl',
        'overflow-hidden',
        'bg-bg-pr/80',
        'backdrop-blur-xs',
        'z-[9999]',
        'shadow-2xl',
        'max-w-[calc(100vw-1.5rem)]',
        'min-h-10'
    ],
    'classes' => []
])

<div
    x-data="{
        open: false,
        style: '',
        toggle() {
            if (this.open) {
                this.close();
                return;
            }

            this.place(288, 192);
            this.open = true;

            this.$nextTick(() => {
                this.place(this.$refs.menu.offsetWidth, this.$refs.menu.offsetHeight);
            });
        },
        close() {
            this.open = false;
        },
        place(width, height) {
            if (!this.$refs.button) {
                return;
            }

            const rect = this.$refs.button.getBoundingClientRect();
            const viewportPadding = 12;
            const gap = 8;
            const menuWidth = width || 288;
            const menuHeight = height || 192;

            let left = rect.right - menuWidth;
            if (left < viewportPadding) {
                left = viewportPadding;
            }

            if (left + menuWidth > window.innerWidth - viewportPadding) {
                left = Math.max(viewportPadding, window.innerWidth - viewportPadding - menuWidth);
            }

            let top = rect.bottom + gap;
            if (top + menuHeight > window.innerHeight - viewportPadding) {
                top = Math.max(viewportPadding, rect.top - gap - menuHeight);
            }

            this.style = `top:${top}px;left:${left}px;`;
        }
    }"
    class="relative"
    x-cloak
    @keydown.escape.window="close()"
    @resize.window="if (open) place($refs.menu.offsetWidth, $refs.menu.offsetHeight)"
    @scroll.window="if (open) close()"
>
    <span x-ref="button" @click="toggle()" class="inline-block">
        {{ $dropdownButton }}
    </span>

    <div
        x-ref="menu"
        x-show="open"
        @click.outside="close()"
        :style="style"
        class="{{ implode(' ', array_merge($defaultClasses, $classes)) }}"
    >
        {{ $slot }}
    </div>
</div>
