<?php
/**
 * HTML API: WP_HTML_Processor_State class
 *
 * @package WordPress
 * @subpackage HTML-API
 * @since 6.4.0
 */

/**
 * Core class used by the HTML processor during HTML parsing
 * for managing the internal parsing state.
 *
 * This class is designed for internal use by the HTML processor.
 *
 * @since 6.4.0
 *
 * @access private
 *
 * @see WP_HTML_Processor
 */
class WP_HTML_Processor_State {
	/*
	 * Insertion mode constants.
	 *
	 * These constants exist and are named to make it easier to
	 * discover and recognize the supported insertion modes in
	 * the parser.
	 *
	 * Out of all the possible insertion modes, only those
	 * supported by the parser are listed here. As support
	 * is added to the parser for more modes, add them here
	 * following the same naming and value pattern.
	 *
	 * @see https://html.spec.whatwg.org/#the-insertion-mode
	 */

	/**
	 * Initial insertion mode for full HTML parser.
	 *
	 * @since 6.4.0
	 *
	 * @see https://html.spec.whatwg.org/#the-initial-insertion-mode
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_INITIAL = 'insertion-mode-initial';

	/**
	 * In body insertion mode for full HTML parser.
	 *
	 * @since 6.4.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-inbody
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_BODY = 'insertion-mode-in-body';

	/**
	 * In select insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-inselect
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_SELECT = 'insertion-mode-in-select';

	/**
	 * In select in table insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-inselectintable
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_SELECT_IN_TABLE = 'insertion-mode-in-select-in-table';

	/**
	 * In table insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-intable
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_TABLE = 'insertion-mode-in-table';

	/**
	 * In caption insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-incaption
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_CAPTION = 'insertion-mode-in-caption';

	/**
	 * In table body insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-intablebody
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_TABLE_BODY = 'insertion-mode-in-table-body';

	/**
	 * In row insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-inrow
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_ROW = 'insertion-mode-in-row';

	/**
	 * In cell insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-incell
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_CELL = 'insertion-mode-in-cell';

	/**
	 * In column group insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-incolumngroup
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_COLUMN_GROUP = 'insertion-mode-in-column-group';

	/**
	 * In frameset insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-inframeset
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_FRAMESET = 'insertion-mode-in-frameset';

	/**
	 * In head insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-inhead
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_HEAD = 'insertion-mode-in-head';

	/**
	 * Before head insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-beforehead
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_BEFORE_HEAD = 'insertion-mode-before-head';

	/**
	 * After head insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-afterhead
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_AFTER_HEAD = 'insertion-mode-after-head';

	/**
	 * In template insertion mode for full HTML parser.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#parsing-main-intemplate
	 * @see WP_HTML_Processor_State::$insertion_mode
	 *
	 * @var string
	 */
	const INSERTION_MODE_IN_TEMPLATE = 'insertion-mode-in-template';

	/**
	 * The stack of template insertion modes.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/#the-insertion-mode:stack-of-template-insertion-modes
	 *
	 * @var array<string>
	 */
	public $stack_of_template_insertion_modes = array();

	/**
	 * Tracks open elements while scanning HTML.
	 *
	 * This property is initialized in the constructor and never null.
	 *
	 * @since 6.4.0
	 *
	 * @see https://html.spec.whatwg.org/#stack-of-open-elements
	 *
	 * @var WP_HTML_Open_Elements
	 */
	public $stack_of_open_elements = null;

	/**
	 * Tracks open formatting elements, used to handle mis-nested formatting element tags.
	 *
	 * This property is initialized in the constructor and never null.
	 *
	 * @since 6.4.0
	 *
	 * @see https://html.spec.whatwg.org/#list-of-active-formatting-elements
	 *
	 * @var WP_HTML_Active_Formatting_Elements
	 */
	public $active_formatting_elements = null;

	/**
	 * Refers to the currently-matched tag, if any.
	 *
	 * @since 6.4.0
	 *
	 * @var WP_HTML_Token|null
	 */
	public $current_token = null;

	/**
	 * Tree construction insertion mode.
	 *
	 * @since 6.4.0
	 *
	 * @see https://html.spec.whatwg.org/#insertion-mode
	 *
	 * @var string
	 */
	public $insertion_mode = self::INSERTION_MODE_INITIAL;

	/**
	 * Context node initializing fragment parser, if created as a fragment parser.
	 *
	 * @since 6.4.0
	 *
	 * @see https://html.spec.whatwg.org/#concept-frag-parse-context
	 *
	 * @var [string, array]|null
	 */
	public $context_node = null;

	/**
	 * HEAD element pointer.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#head-element-pointer
	 *
	 * @var WP_HTML_Token|null
	 */
	public $head_element = null;

	/**
	 * The frameset-ok flag indicates if a `FRAMESET` element is allowed in the current state.
	 *
	 * > The frameset-ok flag is set to "ok" when the parser is created. It is set to "not ok" after certain tokens are seen.
	 *
	 * @since 6.4.0
	 *
	 * @see https://html.spec.whatwg.org/#frameset-ok-flag
	 *
	 * @var bool
	 */
	public $frameset_ok = true;

	/**
	 * Constructor - creates a new and empty state value.
	 *
	 * @since 6.4.0
	 *
	 * @see WP_HTML_Processor
	 */
	public function __construct() {
		$this->stack_of_open_elements     = new WP_HTML_Open_Elements();
		$this->active_formatting_elements = new WP_HTML_Active_Formatting_Elements();
	}

	/**
	 * Runs the reset the insertion mode appropriately algorithm.
	 *
	 * @since 6.7.0
	 *
	 * @see https://html.spec.whatwg.org/multipage/parsing.html#reset-the-insertion-mode-appropriately
	 */
	public function reset_insertion_mode(): void {
		/*
		 * > 1. Let _last_ be false.
		 * > 2. Let _node_ be the last node in the stack of open elements.
		 * > 3. _Loop_: If _node_ is the first node in the stack of open elements, then set _last_
		 * >            to true, and, if the parser was created as part of the HTML fragment parsing
		 * >            algorithm (fragment case), set node to the context element passed to
		 * >            that algorithm.
		 * > …
		 */
		$last       = false;
		$last_index = $this->stack_of_open_elements->count() - 1;
		foreach ( $this->stack_of_open_elements->walk_up() as $i => $node ) {
			if ( $i === $last_index ) {
				$last = true;
			}
			switch ( $node->node_name ) {
				/*
				 * > 4. If node is a `select` element, run these substeps:
				 * >   1. If _last_ is true, jump to the step below labeled done.
				 * >   2. Let _ancestor_ be _node_.
				 * >   3. _Loop_: If _ancestor_ is the first node in the stack of open elements,
				 * >      jump to the step below labeled done.
				 * >   4. Let ancestor be the node before ancestor in the stack of open elements.
				 * >   …
				 * >   7. Jump back to the step labeled _loop_.
				 * >   8. _Done_: Switch the insertion mode to "in select" and return.
				 */
				case 'SELECT':
					if ( ! $last ) {
						foreach ( $this->stack_of_open_elements->walk_up( $node ) as $ancestor ) {
							switch ( $ancestor->node_name ) {
								/*
								 * > 5. If _ancestor_ is a `template` node, jump to the step below
								 * >    labeled _done_.
								 */
								case 'TEMPLATE':
									break 2;

								/*
								 * > 6. If _ancestor_ is a `table` node, switch the insertion mode to
								 * >    "in select in table" and return.
								 */
								case 'TABLE':
									$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT_IN_TABLE;
									return;
							}
						}
					}
					$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_SELECT;
					return;

				/*
				 * > 5. If _node_ is a `td` or `th` element and _last_ is false, then switch the
				 * >    insertion mode to "in cell" and return.
				 */
				case 'TD':
				case 'TH':
					if ( ! $last ) {
						$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_CELL;
						return;
					}
					break;

					/*
					* > 6. If _node_ is a `tr` element, then switch the insertion mode to "in row"
					* >    and return.
					*/
				case 'TR':
					$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_ROW;
					return;

				/*
				 * > 7. If _node_ is a `tbody`, `thead`, or `tfoot` element, then switch the
				 * >    insertion mode to "in table body" and return.
				 */
				case 'TBODY':
				case 'THEAD':
				case 'TFOOT':
					$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE_BODY;
					return;

				/*
				 * > 8. If _node_ is a `caption` element, then switch the insertion mode to
				 * >    "in caption" and return.
				 */
				case 'CAPTION':
					$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_CAPTION;
					return;

				/*
				 * > 9. If _node_ is a `colgroup` element, then switch the insertion mode to
				 * >    "in column group" and return.
				 */
				case 'COLGROUP':
					$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_COLUMN_GROUP;
					return;

				/*
				 * > 10. If _node_ is a `table` element, then switch the insertion mode to
				 * >     "in table" and return.
				 */
				case 'TABLE':
					$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_TABLE;
					return;

				/*
				 * > 11. If _node_ is a `template` element, then switch the insertion mode to the
				 * >     current template insertion mode and return.
				 */
				case 'TEMPLATE':
					$this->insertion_mode = end( $this->stack_of_template_insertion_modes );
					return;

				/*
				 * > 12. If _node_ is a `head` element and _last_ is false, then switch the
				 * >     insertion mode to "in head" and return.
				 */
				case 'HEAD':
					if ( ! $last ) {
						$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_HEAD;
						return;
					}
					break;

				/*
				 * > 13. If _node_ is a `body` element, then switch the insertion mode to "in body"
				 * >     and return.
				 */
				case 'BODY':
					$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_BODY;
					return;

				/*
				 * > 14. If _node_ is a `frameset` element, then switch the insertion mode to
				 * >     "in frameset" and return. (fragment case)
				 */
				case 'FRAMESET':
					$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_FRAMESET;
					return;

				/*
				 * > 15. If _node_ is an `html` element, run these substeps:
				 * >     1. If the head element pointer is null, switch the insertion mode to
				 * >        "before head" and return. (fragment case)
				 * >     2. Otherwise, the head element pointer is not null, switch the insertion
				 * >        mode to "after head" and return.
				 */
				case 'HTML':
					if ( null === $this->head_element ) {
						$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_BEFORE_HEAD;
					} else {
						$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_AFTER_HEAD;
					}
					return;
			}
		}

		/*
		 * > 16. If _last_ is true, then switch the insertion mode to "in body"
		 * >     and return. (fragment case)
		 *
		 * `$last` will always be true here, we've reached the end of the stack.
		 */
		$this->insertion_mode = WP_HTML_Processor_State::INSERTION_MODE_IN_BODY;
	}
}
