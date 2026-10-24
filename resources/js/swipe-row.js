/**
 * Gesto de arraste horizontal em linhas de listagem (mouse, touchpad e toque via Pointer Events).
 *
 * O gesto e somente um atalho: ao soltar alem do limite, chama o metodo Livewire configurado,
 * que revalida estado/permissoes no servidor e, quando necessario, abre o modal de confirmacao.
 * Nada e executado enquanto o usuario arrasta, e soltar antes do limite devolve a linha.
 */
const AXIS_LOCK = 8;
const MIN_THRESHOLD = 72;
const MAX_THRESHOLD = 140;

export default function swipeRow({ right = null, left = null, disabled = false } = {}) {
    return {
        right,
        left,
        disabled,
        offset: 0,
        width: 0,
        dragging: false,
        busy: false,
        pointerId: null,
        axis: null,
        startX: 0,
        startY: 0,
        suppressClick: false,

        get threshold() {
            return Math.min(MAX_THRESHOLD, Math.max(MIN_THRESHOLD, this.width * 0.3));
        },

        get direction() {
            if (this.offset > 0 && this.right) return 'right';
            if (this.offset < 0 && this.left) return 'left';
            return null;
        },

        get armed() {
            return this.direction !== null && Math.abs(this.offset) >= this.threshold;
        },

        start(event) {
            if (this.disabled || this.busy || this.pointerId !== null || event.button > 0) return;
            // Controles da linha mantem o comportamento normal (clique, foco e selecao).
            if (event.target.closest('button, input, select, textarea, label, [data-swipe-ignore]')) return;
            this.pointerId = event.pointerId;
            this.startX = event.clientX;
            this.startY = event.clientY;
            this.axis = null;
            this.width = this.$el.offsetWidth;
        },

        move(event) {
            if (event.pointerId !== this.pointerId) return;
            const dx = event.clientX - this.startX;
            const dy = event.clientY - this.startY;
            if (this.axis === null) {
                if (Math.abs(dx) < AXIS_LOCK && Math.abs(dy) < AXIS_LOCK) return;
                // Movimento predominantemente vertical e rolagem: o gesto e abandonado.
                this.axis = Math.abs(dx) > Math.abs(dy) * 1.2 ? 'x' : 'y';
                if (this.axis === 'y') {
                    this.release();
                    return;
                }
                this.dragging = true;
                event.currentTarget.setPointerCapture?.(event.pointerId);
                window.getSelection()?.removeAllRanges();
            }
            event.preventDefault();
            const available = (dx > 0 && this.right) || (dx < 0 && this.left);
            const limit = this.width * 0.6;
            // Sem acao naquele sentido, a linha apenas resiste levemente.
            this.offset = Math.max(-limit, Math.min(limit, available ? dx : dx * 0.15));
        },

        end(event) {
            if (event.pointerId !== this.pointerId) return;
            const action = this.armed ? this[this.direction] : null;
            if (this.dragging) this.suppressClick = true;
            this.release();
            if (action) this.run(action);
        },

        cancel() {
            if (this.dragging) this.suppressClick = true;
            this.release();
        },

        release() {
            this.pointerId = null;
            this.axis = null;
            this.dragging = false;
            this.offset = 0;
        },

        guardClick(event) {
            // Evita que soltar o arraste sobre um link abra o registro.
            if (!this.suppressClick) return;
            this.suppressClick = false;
            event.preventDefault();
            event.stopPropagation();
        },

        run(action) {
            if (this.busy) return;
            this.busy = true;
            this.$wire.call(action.method, ...(action.args ?? [])).finally(() => {
                this.busy = false;
            });
        },
    };
}
