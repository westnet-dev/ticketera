import { Editor } from "@tiptap/core";
import StarterKit from "@tiptap/starter-kit";
import { Placeholder } from "@tiptap/extensions";

/**
 * Alpine component backing <x-tickets.rich-editor>.
 *
 * The Tiptap instance is kept in a closure (not on `this`) on purpose: Alpine wraps
 * component properties in reactive proxies, which breaks ProseMirror's identity checks
 * ("mismatched transaction" errors). `tick` is bumped on every transaction so that
 * `isActive()` bindings re-evaluate.
 */
document.addEventListener("alpine:init", () => {
    window.Alpine.data("richEditor", ({ placeholder = "", submitOnEnter = false } = {}) => {
        let editor = null;

        return {
            value: "",
            tick: 0,

            init() {
                editor = new Editor({
                    element: this.$refs.content,
                    content: this.value || "",
                    extensions: [
                        StarterKit.configure({
                            heading: false,
                            link: { openOnClick: false, autolink: true, defaultProtocol: "https" },
                        }),
                        Placeholder.configure({ placeholder }),
                    ],
                    editorProps: {
                        attributes: {
                            class: "prose prose-sm prose-zinc dark:prose-invert max-w-none px-3 py-2 focus:outline-none",
                        },
                        handleKeyDown: (view, event) => {
                            if (submitOnEnter && event.key === "Enter" && !event.shiftKey && !event.isComposing) {
                                event.preventDefault();
                                this.$root.closest("form")?.requestSubmit();
                                return true;
                            }

                            return false;
                        },
                    },
                    onTransaction: () => {
                        this.tick++;
                    },
                    onUpdate: ({ editor }) => {
                        this.value = editor.isEmpty ? "" : editor.getHTML();
                    },
                });

                // External changes (e.g. $this->reset() after submit, or loading a draft).
                this.$watch("value", (value) => {
                    const current = editor.isEmpty ? "" : editor.getHTML();

                    if ((value || "") !== current) {
                        editor.commands.setContent(value || "", { emitUpdate: false });
                    }
                });
            },

            destroy() {
                editor?.destroy();
                editor = null;
            },

            isActive(name, attributes = {}) {
                this.tick;

                return editor?.isActive(name, attributes) ?? false;
            },

            run(command) {
                editor?.chain().focus()[command]().run();
            },

            setLink() {
                if (!editor) {
                    return;
                }

                const previous = editor.getAttributes("link").href ?? "";
                const url = window.prompt("URL", previous);

                if (url === null) {
                    return;
                }

                if (url.trim() === "") {
                    editor.chain().focus().extendMarkRange("link").unsetLink().run();

                    return;
                }

                editor.chain().focus().extendMarkRange("link").setLink({ href: url.trim() }).run();
            },
        };
    });
});
