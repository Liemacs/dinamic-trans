/*
 * Livewire owns the interactive parts and ships its own Alpine build, so nothing
 * is imported here. Any Alpine store or plugin must be registered on
 * `livewire:init` so it hooks into Livewire's Alpine instance rather than a
 * second copy — registering on `alpine:init` would run against a build that
 * does not exist.
 *
 * Both stores below are ported from the Hey Roger back office.
 */
document.addEventListener('livewire:init', () => {
    /*
     * A single styled confirmation dialog, in place of the browser's native
     * confirm() and Livewire's wire:confirm.
     *
     * One modal lives in the dashboard layout and reads this store; any
     * destructive button anywhere — a plain form, a Livewire action — opens it by
     * calling ask() with the copy to show and a closure to run if the user
     * agrees. The closure is captured at click time (see
     * x-dashboard.confirm-delete), so it still submits the right form or hits the
     * right component when it fires.
     */
    Alpine.store('confirm', {
        open: false,
        title: '',
        message: '',
        confirmLabel: 'Șterge',
        action: null,

        ask(options = {}) {
            this.title = options.title ?? 'Sigur?';
            this.message = options.message ?? '';
            this.confirmLabel = options.confirmLabel ?? 'Șterge';
            this.action = typeof options.action === 'function' ? options.action : null;
            this.open = true;
        },

        agree() {
            const action = this.action;
            this.close();
            // Run after the dialog closes so the destructive act — a navigation,
            // a Livewire round trip — never races the modal's own teardown.
            if (action) {
                action();
            }
        },

        close() {
            this.open = false;
            this.action = null;
        },
    });

    /*
     * Guards a half-filled form against being walked away from.
     *
     * Dirtiness is read off the inputs themselves rather than from Livewire's
     * state, because the fields that matter most here — plecarea, destinația,
     * notele — are bound with a deferred `wire:model`, so the server has not been
     * told about them yet at the moment the user clicks a link.
     *
     * Two exits are covered. An in-app link is intercepted and answered with the
     * dialog below, which offers to save first. A reload, a closed tab or the
     * browser's Back button can only be met with the browser's own confirmation:
     * `beforeunload` cannot be styled and cannot run an async save, and that is a
     * browser rule, not a choice made here.
     */
    Alpine.data('unsavedGuard', (config = {}) => ({
        open: false,
        saving: false,
        pending: null,

        fields: config.fields ?? [],
        saveAction: config.save ?? null,

        init() {
            // Capture phase, so the link is caught before anything else acts on
            // the click.
            this.onDocumentClick = (event) => this.interceptLink(event);
            document.addEventListener('click', this.onDocumentClick, true);

            this.onBeforeUnload = (event) => {
                if (this.isDirty()) {
                    event.preventDefault();
                    // Older browsers want the assignment; none of them show the
                    // string any more.
                    event.returnValue = '';
                }
            };
            window.addEventListener('beforeunload', this.onBeforeUnload);
        },

        destroy() {
            document.removeEventListener('click', this.onDocumentClick, true);
            window.removeEventListener('beforeunload', this.onBeforeUnload);
        },

        isDirty() {
            return this.fields.some((id) => {
                const field = document.getElementById(id);

                return field !== null && String(field.value).trim() !== '';
            });
        },

        interceptLink(event) {
            // A modified click is the user asking for a new tab or a download —
            // this page is not going anywhere, so leave it alone.
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const link = event.target.closest('a[href]');

            if (!link || link.target === '_blank' || link.hasAttribute('download')) {
                return;
            }

            const url = new URL(link.href, window.location.href);

            // Same page, or somewhere else entirely: neither loses any work.
            if (url.origin !== window.location.origin || url.href === window.location.href) {
                return;
            }

            if (!this.isDirty()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            this.pending = url.href;
            this.open = true;
        },

        async saveAndLeave() {
            if (!this.saveAction) {
                return;
            }

            this.saving = true;

            // The action returns true only once it has actually stored the row;
            // a failed validation resolves without it, and then the dialog closes
            // onto the errors rather than navigating away from them.
            const saved = await this.$wire[this.saveAction]();

            this.saving = false;
            this.open = false;

            if (saved) {
                this.leave();
            } else {
                this.pending = null;
            }
        },

        discardAndLeave() {
            this.open = false;
            this.leave();
        },

        cancel() {
            this.open = false;
            this.pending = null;
        },

        leave() {
            const target = this.pending;
            this.pending = null;

            if (!target) {
                return;
            }

            // The form is either saved or deliberately abandoned, so the
            // browser's own prompt would be a second question about a settled
            // matter.
            window.removeEventListener('beforeunload', this.onBeforeUnload);
            window.location.href = target;
        },
    }));

    /*
     * Whether the sidebar is collapsed to an icons-only rail.
     *
     * A store rather than x-data on the <aside>: the toggle button and the labels
     * live inside a nested Alpine scope (the nav), and assigning to a variable
     * inherited from an ancestor scope writes a shadow copy on the child instead
     * of updating the ancestor. A global store has one owner, reachable and
     * writable from anywhere. The choice is remembered per browser; storage can
     * throw in private windows, so every access is guarded.
     */
    Alpine.store('sidebar', {
        collapsed: false,

        init() {
            try {
                this.collapsed = localStorage.getItem('cr-sidebar-collapsed') === '1';
            } catch (error) {
                // No stored choice to restore; start expanded.
            }
        },

        set(value) {
            this.collapsed = value;

            try {
                localStorage.setItem('cr-sidebar-collapsed', value ? '1' : '0');
            } catch (error) {
                // The choice simply will not outlive the page.
            }
        },

        toggle() {
            this.set(!this.collapsed);
        },
    });
});
