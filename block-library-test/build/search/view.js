"use strict";

var _interactivity = require("@wordpress/interactivity");
/**
 * WordPress dependencies
 */

const {
  actions
} = (0, _interactivity.store)('core/search', {
  state: {
    get ariaLabel() {
      const {
        isSearchInputVisible,
        ariaLabelCollapsed,
        ariaLabelExpanded
      } = (0, _interactivity.getContext)();
      return isSearchInputVisible ? ariaLabelExpanded : ariaLabelCollapsed;
    },
    get ariaControls() {
      const {
        isSearchInputVisible,
        inputId
      } = (0, _interactivity.getContext)();
      return isSearchInputVisible ? null : inputId;
    },
    get type() {
      const {
        isSearchInputVisible
      } = (0, _interactivity.getContext)();
      return isSearchInputVisible ? 'submit' : 'button';
    },
    get tabindex() {
      const {
        isSearchInputVisible
      } = (0, _interactivity.getContext)();
      return isSearchInputVisible ? '0' : '-1';
    }
  },
  actions: {
    openSearchInput(event) {
      const ctx = (0, _interactivity.getContext)();
      const {
        ref
      } = (0, _interactivity.getElement)();
      if (!ctx.isSearchInputVisible) {
        event.preventDefault();
        ctx.isSearchInputVisible = true;
        ref.parentElement.querySelector('input').focus();
      }
    },
    closeSearchInput() {
      const ctx = (0, _interactivity.getContext)();
      ctx.isSearchInputVisible = false;
    },
    handleSearchKeydown(event) {
      const {
        ref
      } = (0, _interactivity.getElement)();
      // If Escape close the menu.
      if (event?.key === 'Escape') {
        actions.closeSearchInput();
        ref.querySelector('button').focus();
      }
    },
    handleSearchFocusout(event) {
      const {
        ref
      } = (0, _interactivity.getElement)();
      // If focus is outside search form, and in the document, close menu
      // event.target === The element losing focus
      // event.relatedTarget === The element receiving focus (if any)
      // When focusout is outside the document,
      // `window.document.activeElement` doesn't change.
      if (!ref.contains(event.relatedTarget) && event.target !== window.document.activeElement) {
        actions.closeSearchInput();
      }
    }
  }
});
//# sourceMappingURL=view.js.map