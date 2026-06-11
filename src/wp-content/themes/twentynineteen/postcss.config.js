var postcssFocusWithin = require('postcss-focus-within');

module.exports = {
    plugins: {
        autoprefixer: {}
    }
};

var oneSelectorPerLine = function () {
    return {
        postcssPlugin: 'one-selector-per-line',
        Rule: function (rule) {
            if (rule.selector.indexOf(',') !== -1) {
                var before = rule.raws.before || '';
                var indent = before.substring(before.lastIndexOf('\n') + 1);
                rule.selector = rule.selector.split(/,\s*/).join(',\n' + indent);
            }
        }
    };
};
oneSelectorPerLine.postcss = true;

module.exports = {
    plugins: [
        postcssFocusWithin({
            disablePolyfillReadyClass: true
        }),
        oneSelectorPerLine
    ]
};
