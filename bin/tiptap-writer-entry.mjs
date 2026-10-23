/**
 * TipTap bundle entry for Dokumentenverfasser.
 * Rebuild: node bin/build-tiptap-writer.mjs (needs tmp-tiptap-build/node_modules).
 */
export { Editor } from '@tiptap/core';
export { default as StarterKit } from '@tiptap/starter-kit';
export { Placeholder } from '@tiptap/extension-placeholder';
export { Image } from '@tiptap/extension-image';
export { Table } from '@tiptap/extension-table';
export { TableRow } from '@tiptap/extension-table-row';
export { TableCell } from '@tiptap/extension-table-cell';
export { TableHeader } from '@tiptap/extension-table-header';
export { Underline } from '@tiptap/extension-underline';
export { TextStyle } from '@tiptap/extension-text-style';
export { Color } from '@tiptap/extension-color';
export { FontFamily } from '@tiptap/extension-font-family';
export { TextAlign } from '@tiptap/extension-text-align';
export { Link } from '@tiptap/extension-link';
export { Highlight } from '@tiptap/extension-highlight';

import { Extension } from '@tiptap/core';

/** Font size via inline style (pt). */
export const FontSize = Extension.create({
  name: 'fontSize',
  addOptions() {
    return { types: ['textStyle'] };
  },
  addGlobalAttributes() {
    return [
      {
        types: this.options.types,
        attributes: {
          fontSize: {
            default: null,
            parseHTML: (element) => element.style.fontSize || null,
            renderHTML: (attributes) => {
              if (!attributes.fontSize) {
                return {};
              }
              return { style: `font-size: ${attributes.fontSize}` };
            },
          },
        },
      },
    ];
  },
  addCommands() {
    return {
      setFontSize:
        (fontSize) =>
        ({ chain }) =>
          chain().setMark('textStyle', { fontSize }).run(),
      unsetFontSize:
        () =>
        ({ chain }) =>
          chain().setMark('textStyle', { fontSize: null }).removeEmptyTextStyle().run(),
    };
  },
});
