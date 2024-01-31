"use strict";

var _interactivity = require("@wordpress/interactivity");
function _getRequireWildcardCache(e) { if ("function" != typeof WeakMap) return null; var r = new WeakMap(), t = new WeakMap(); return (_getRequireWildcardCache = function (e) { return e ? t : r; })(e); }
function _interopRequireWildcard(e, r) { if (!r && e && e.__esModule) return e; if (null === e || "object" != typeof e && "function" != typeof e) return { default: e }; var t = _getRequireWildcardCache(r); if (t && t.has(e)) return t.get(e); var n = { __proto__: null }, a = Object.defineProperty && Object.getOwnPropertyDescriptor; for (var u in e) if ("default" !== u && Object.prototype.hasOwnProperty.call(e, u)) { var i = a ? Object.getOwnPropertyDescriptor(e, u) : null; i && (i.get || i.set) ? Object.defineProperty(n, u, i) : n[u] = e[u]; } return n.default = e, t && t.set(e, n), n; }
const isValidLink = ref => ref && ref instanceof window.HTMLAnchorElement && ref.href && (!ref.target || ref.target === '_self') && ref.origin === window.location.origin;
const isValidEvent = event => event.button === 0 &&
// Left clicks only.
!event.metaKey &&
// Open in new tab (Mac).
!event.ctrlKey &&
// Open in new tab (Windows).
!event.altKey &&
// Download.
!event.shiftKey && !event.defaultPrevented;
(0, _interactivity.store)('core/query', {
  state: {
    get startAnimation() {
      return (0, _interactivity.getContext)().animation === 'start';
    },
    get finishAnimation() {
      return (0, _interactivity.getContext)().animation === 'finish';
    }
  },
  actions: {
    *navigate(event) {
      const ctx = (0, _interactivity.getContext)();
      const {
        ref
      } = (0, _interactivity.getElement)();
      const {
        queryRef
      } = ctx;
      const isDisabled = queryRef?.dataset.wpNavigationDisabled;
      if (isValidLink(ref) && isValidEvent(event) && !isDisabled) {
        event.preventDefault();

        // Don't announce the navigation immediately, wait 400 ms.
        const timeout = setTimeout(() => {
          ctx.message = ctx.loadingText;
          ctx.animation = 'start';
        }, 400);
        const {
          actions
        } = yield Promise.resolve().then(() => _interopRequireWildcard(require('@wordpress/interactivity-router')));
        yield actions.navigate(ref.href);

        // Dismiss loading message if it hasn't been added yet.
        clearTimeout(timeout);

        // Announce that the page has been loaded. If the message is the
        // same, we use a no-break space similar to the @wordpress/a11y
        // package: https://github.com/WordPress/gutenberg/blob/c395242b8e6ee20f8b06c199e4fc2920d7018af1/packages/a11y/src/filter-message.js#L20-L26
        ctx.message = ctx.loadedText + (ctx.message === ctx.loadedText ? '\u00A0' : '');
        ctx.animation = 'finish';
        ctx.url = ref.href;

        // Focus the first anchor of the Query block.
        const firstAnchor = `.wp-block-post-template a[href]`;
        queryRef.querySelector(firstAnchor)?.focus();
      }
    },
    *prefetch() {
      const {
        queryRef
      } = (0, _interactivity.getContext)();
      const {
        ref
      } = (0, _interactivity.getElement)();
      const isDisabled = queryRef?.dataset.wpNavigationDisabled;
      if (isValidLink(ref) && !isDisabled) {
        const {
          actions
        } = yield Promise.resolve().then(() => _interopRequireWildcard(require('@wordpress/interactivity-router')));
        yield actions.prefetch(ref.href);
      }
    }
  },
  callbacks: {
    *prefetch() {
      const {
        url
      } = (0, _interactivity.getContext)();
      const {
        ref
      } = (0, _interactivity.getElement)();
      if (url && isValidLink(ref)) {
        const {
          actions
        } = yield Promise.resolve().then(() => _interopRequireWildcard(require('@wordpress/interactivity-router')));
        yield actions.prefetch(ref.href);
      }
    },
    setQueryRef() {
      const ctx = (0, _interactivity.getContext)();
      const {
        ref
      } = (0, _interactivity.getElement)();
      ctx.queryRef = ref;
    }
  }
});
//# sourceMappingURL=view.js.map