<?php

/**
 * A parser grammar.
 *
 * This class represents a parser grammar that can be consumed by WP_Parser.
 * It loads a compressed grammar from a PHP array, inflates it to an internal
 * representation, and precomputes a lookup table for quick branch selection.
 *
 * @TODO: Add more details about the grammar implementation.
 *
 * @access private
 */
class WP_Parser_Grammar {
	/**
	 * ID for a special grammar rule that represents an empty "ε" (epsilon) rule.
	 *
	 * An "ε" rule in a grammar is a rule that matches an empty input of 0 bytes.
	 * It can be used to represent optional grammar productions, and it is helpful
	 * for expanding 0-or-1, 0-or-more, and 1-or-more quantifiers into simple rules.
	 *
	 * @TODO Investigate whether we can prevent possible conflict with a token ID.
	 *       The MySQL grammar doesn't define a token with ID "0", but generally
	 *       token IDs are not guaranteed to always satisfy this condition.
	 */
	const EMPTY_RULE_ID = 0;

	/**
	 * @TODO: Review and document these properties and their visibility.
	 */
	public $rules;
	public $rule_names;
	public $fragment_ids;
	public $lookahead_is_match_possible = array();
	public $lowest_non_terminal_id;
	public $highest_terminal_id;

	public function __construct( array $rules ) {
		$this->inflate( $rules );
	}

	public function get_rule_name( $rule_id ) {
		return $this->rule_names[ $rule_id ];
	}

	public function get_rule_id( $rule_name ) {
		return array_search( $rule_name, $this->rule_names, true );
	}

	/**
	 * Inflate the grammar to an internal representation optimized for parsing.
	 *
	 * The input grammar is a compressed PHP array to minimize the file size.
	 * Every rule and token in the compressed grammar is encoded as an integer.
	 */
	private function inflate( $grammar ) {
		$this->lowest_non_terminal_id = $grammar['rules_offset'];
		$this->highest_terminal_id    = $this->lowest_non_terminal_id - 1;

		foreach ( $grammar['rules_names'] as $rule_index => $rule_name ) {
			$this->rule_names[ $rule_index + $grammar['rules_offset'] ] = $rule_name;
			$this->rules[ $rule_index + $grammar['rules_offset'] ]      = array();

			/**
			 * Treat all intermediate rules as fragments to inline before returning
			 * the final parse tree to the API consumer.
			 *
			 * The original grammar was too difficult to parse with rules like:
			 *
			 *    query ::= EOF | ((simpleStatement | beginWork) ((SEMICOLON_SYMBOL EOF?) | EOF))
			 *
			 * We've factored rule fragments, such as `EOF?`, into separate rules, such as `%EOF_zero_or_one`.
			 * This is super useful for parsing, but it limits the API consumer's ability to
			 * reason about the parse tree.
			 *
			 * Fragments are intermediate rules that are not part of the original grammar.
			 * They are prefixed with a "%" to be distinguished from the original rules.
			 */
			if ( '%' === $rule_name[0] ) {
				$this->fragment_ids[ $rule_index + $grammar['rules_offset'] ] = true;
			}
		}

		$this->rules = array();
		foreach ( $grammar['grammar'] as $rule_index => $branches ) {
			$rule_id                 = $rule_index + $grammar['rules_offset'];
			$this->rules[ $rule_id ] = $branches;
		}

		/**
		 * Compute a rule => [token => true] lookup table for each rule
		 * that starts with a terminal OR with another rule that already
		 * has a lookahead mapping.
		 *
		 * This is similar to left-factoring the grammar, even if not quite
		 * the same.
		 *
		 * This enables us to quickly bail out from checking branches that
		 * cannot possibly match the current token. This increased the parser
		 * speed by a whopping 80%!
		 *
		 * @TODO: Explore these possible next steps:
		 *
		 * * Compute a rule => [token => branch[]] list lookup table and only
		 *   process the branches that have a chance of matching the current token.
		 * * Actually left-factor the grammar as much as possible. This, however,
		 *   could inflate the serialized grammar size.
		 */
		// 5 iterations seem to give us all the speed gains we can get from this.
		for ( $i = 0; $i < 5; $i++ ) {
			foreach ( $grammar['grammar'] as $rule_index => $branches ) {
				$rule_id = $rule_index + $grammar['rules_offset'];
				if ( isset( $this->lookahead_is_match_possible[ $rule_id ] ) ) {
					continue;
				}
				$rule_lookup                                   = array();
				$first_symbol_can_be_expanded_to_all_terminals = true;
				foreach ( $branches as $branch ) {
					$terminals                   = false;
					$branch_starts_with_terminal = $branch[0] < $this->lowest_non_terminal_id;
					if ( $branch_starts_with_terminal ) {
						$terminals = array( $branch[0] );
					} elseif ( isset( $this->lookahead_is_match_possible[ $branch[0] ] ) ) {
						$terminals = array_keys( $this->lookahead_is_match_possible[ $branch[0] ] );
					}

					if ( false === $terminals ) {
						$first_symbol_can_be_expanded_to_all_terminals = false;
						break;
					}
					foreach ( $terminals as $terminal ) {
						$rule_lookup[ $terminal ] = true;
					}
				}
				if ( $first_symbol_can_be_expanded_to_all_terminals ) {
					$this->lookahead_is_match_possible[ $rule_id ] = $rule_lookup;
				}
			}
		}
	}
}
