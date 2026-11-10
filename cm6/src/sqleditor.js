// CodeMirror 6 SQL editor for the Custom SQL form of block_configurable_reports.
//
// The API is close to the CodeMirror 5 one that main.js used: fromTextArea(textarea, options) returns an editor
// with getValue() and setValue(). The textarea stays in the form, hidden, and always holds the current SQL.
import {EditorView, keymap, lineNumbers, highlightActiveLine, highlightActiveLineGutter, drawSelection,
    dropCursor, rectangularSelection, crosshairCursor, highlightSpecialChars} from '@codemirror/view';
import {EditorState, Compartment} from '@codemirror/state';
import {defaultKeymap, history, historyKeymap, indentWithTab} from '@codemirror/commands';
import {syntaxHighlighting, defaultHighlightStyle, bracketMatching, foldGutter, foldKeymap, indentOnInput,
    syntaxTree} from '@codemirror/language';
import {autocompletion, completionKeymap, closeBrackets, closeBracketsKeymap} from '@codemirror/autocomplete';
import {searchKeymap, highlightSelectionMatches} from '@codemirror/search';
import {linter, lintGutter, lintKeymap} from '@codemirror/lint';
import {sql, MySQL, MariaSQL, PostgreSQL, StandardSQL} from '@codemirror/lang-sql';

// The SQL dialects, by the names that PHP gives.
const DIALECTS = {mysql: MySQL, mariadb: MariaSQL, pgsql: PostgreSQL, standard: StandardSQL};

// Report SQL writes the table prefix as "prefix_", which the plugin replaces when the report runs.
const TABLE_PREFIX = 'prefix_';

const editorTheme = EditorView.theme({
    '&': {height: '35em', border: '1px solid #ced4da', fontSize: '14px'},
    '&.cm-focused': {outline: '2px solid #80bdff'},
    '.cm-scroller': {overflow: 'auto', fontFamily: 'SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace'},
});

/**
 * Complete the %%...%% placeholders of the plugin. The SQL parser reads % as an operator, so match the text.
 *
 * @param {String[]} placeholders
 * @return {Function} A completion source.
 */
const placeholderSource = (placeholders) => (context) => {
    const word = context.matchBefore(/%%?[A-Z_]*/);
    if (!word || (word.from === word.to && !context.explicit)) {
        return null;
    }
    return {
        from: word.from,
        options: placeholders.map((label) => ({label, type: 'variable', boost: 99})),
        validFor: /^%%?[A-Z_]*$/,
    };
};

/**
 * Mark the syntax errors that the SQL grammar finds. The grammar is tolerant: it finds unbalanced brackets.
 *
 * @param {String} message
 * @return {Extension}
 */
const syntaxLinter = (message) => linter((view) => {
    const diagnostics = [];
    syntaxTree(view.state).cursor().iterate((node) => {
        if (node.type.isError) {
            diagnostics.push({from: node.from, to: Math.max(node.to, node.from + 1), severity: 'error', message});
        }
    });
    return diagnostics;
});

/**
 * Get the language extension: the dialect, and the tables for completion.
 *
 * @param {Object} dialect
 * @param {Object} tables {table name without prefix: [column names]}
 * @param {String[]} placeholders
 * @return {Extension}
 */
const language = (dialect, tables, placeholders) => {
    const schema = {};
    Object.keys(tables).forEach((name) => {
        schema[TABLE_PREFIX + name] = tables[name];
    });
    const support = sql({dialect, schema, upperCaseKeywords: true});
    return [support, support.language.data.of({autocomplete: placeholderSource(placeholders)})];
};

/**
 * Replace a textarea with the SQL editor.
 *
 * @param {HTMLTextAreaElement} textarea
 * @param {Object} options dialect ('mysql', 'mariadb', 'pgsql'), placeholders, syntaxerror (message), autofocus.
 * @return {Object} The editor: getValue(), setValue(text), setTables(tables), view.
 */
export const fromTextArea = (textarea, options = {}) => {
    const dialect = DIALECTS[options.dialect] || StandardSQL;
    const placeholders = options.placeholders || [];
    const languageConf = new Compartment();

    const view = new EditorView({
        doc: textarea.value,
        extensions: [
            lineNumbers(), highlightActiveLineGutter(), highlightSpecialChars(), history(), foldGutter(),
            drawSelection(), dropCursor(), EditorState.allowMultipleSelections.of(true), indentOnInput(),
            syntaxHighlighting(defaultHighlightStyle, {fallback: true}), bracketMatching(), closeBrackets(),
            rectangularSelection(), crosshairCursor(), highlightActiveLine(), highlightSelectionMatches(),
            languageConf.of(language(dialect, {}, placeholders)),
            autocompletion(),
            syntaxLinter(options.syntaxerror || 'Syntax error'), lintGutter(),
            keymap.of([...closeBracketsKeymap, ...defaultKeymap, ...searchKeymap, ...historyKeymap, ...foldKeymap,
                ...completionKeymap, ...lintKeymap, indentWithTab]),
            editorTheme,
            EditorView.lineWrapping,
            // Keep the textarea up to date, for the form submit and for other scripts.
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    textarea.value = update.state.doc.toString();
                }
            }),
        ],
    });

    textarea.parentNode.insertBefore(view.dom, textarea);
    textarea.style.display = 'none';
    if (options.autofocus) {
        view.focus();
    }

    return {
        view,
        getValue: () => view.state.doc.toString(),
        setValue: (text) => view.dispatch({changes: {from: 0, to: view.state.doc.length, insert: text}}),
        setTables: (tables) => view.dispatch({effects: languageConf.reconfigure(language(dialect, tables, placeholders))}),
    };
};
