class Slice {
	constructor( at, length ) {
		this.at = at;
		this.length = length;
	}
}

class Attribute {
	constructor( start, length, nameLength, valueStart, valueLength, hasValue ) {
		this.start = start;
		this.length = length;
		this.nameLength = nameLength;
		this.valueStart = valueStart;
		this.valueLength = valueLength;
		this.hasValue = hasValue;
	}
}

export class TagProcessor {
	constructor( html ) {
		/**
		 * @type {string} The input HTML
		 */
		this.html = html;
		this.at   = 0;
		this.tokenStartsAt = 0;
		this.tokenLength = 0;
		this.tagNameStartsAt = 0;
		this.tagNameLength = 0;
		this.isClosingTag = false;

		/** @type Attribute[] */
		this.attributes = [];
	}

	nextTag() {
		if ( this.at >= this.html.length ) {
			return false;
		}

		if ( false === this.parseNextTag() ) {
			this.at = this.html.length;
			return false;
		}

		while ( this.parseNextAttribute() ) {
			continue;
		}

		if ( this.at >= this.html.length ) {
			return false;
		}

		const tagEndsAt = this.html.indexOf( '>', this.at );
		if ( -1 === tagEndsAt ) {
			return false;
		}
		this.tokenLength = tagEndsAt - this.tokenStartsAt;
		this.at = tagEndsAt;

		const tagName = this.getTag();
		switch ( tagName ) {
			case 'SCRIPT':
				if ( ! this.skipScriptData() ) {
					this.at = this.html.length;
					return false;
				}
				break;

			case 'TEXTAREA':
			case 'TITLE':
				if ( ! this.skipRCData( tagName ) ) {
					this.at = this.html.length;
					return false;
				}
				break;

			case 'IFRAME':
			case 'NOEMBED':
			case 'NOFRAMES':
			case 'NOSCRIPT':
			case 'STYLE':
				if ( ! this.skipRawtext( tagName ) ) {
					this.at = this.html.length;
					return false;
				}
				break;
		}

		return true;
	}

	skipRawtext( tagName ) {
		return this.skipRCData( tagName );
	}

	skipRCData( tagName ) {
		let at = this.at;

		loop: while ( -1 !== at && at < this.html.length ) {
			at = this.html.indexOf( '</', at );
			if ( -1 === at || ( at + tagName.length ) >= this.html.length ) {
				this.at = this.html.length;
				return false;
			}

			const closerPotentiallyAt = at;
			at += 2;

			for ( let i = 0; i < tagName.length; i++ ) {
				const c = tagName[ i ];
				const h = this.html[ at + i ];

				if ( h !== c && h.toUpperCase() !== c ) {
					at += i;
					continue loop;
				}
			}

			at += tagName.length;
			this.at = at;

			const c = this.html[ at ];
			if ( ' ' !== c && '\t' !== c && '\r' !== c && 'n' !== c && '/' !== c && '>' !== c ) {
				continue loop;
			}

			while ( this.parseNextAttribute() ) {
				continue;
			}

			at = this.at;
			if ( at >= this.html.length ) {
				return false;
			}

			if ( '>' === this.html[ at ] || '/' === this.html[ at ] ) {
				this.at = closerPotentiallyAt;
				return true;
			}
		}

		return false;
	}

	skipScriptData() {
		// @todo Add full support.
		return this.skipRCData( 'SCRIPT' );
	}

	parseNextTag() {
		this.afterTag();

		let at = this.at;

		while ( -1 !== at && at < this.html.length ) {
			at = this.html.indexOf( '<', at );
			if ( -1 === at ) {
				return false;
			}

			this.tokenStartsAt = at;

			if ( '/' === this.html[ at + 1 ] ) {
				this.isClosingTag = true;
				at++;
			} else {
				this.isClosingTag = false;
			}

			const tagNamePrefixLength = this.strspn( 'a-zA-Z', this.at + 1 );
			if ( tagNamePrefixLength > 0 ) {
				at++;
				this.tagNameLength = tagNamePrefixLength + this.strcsnp( ' \t\f\r\n', at + tagNamePrefixLength );
				this.tagNameStartsAt = at;
				this.at = at + this.tagNameLength;
				return true;
			}

			if ( at + 1 > this.html.length ) {
				return false;
			}

			if ( '!' === this.html[ at + 1 ] ) {
				throw new Error();
			}

			if ( '>' === this.html[ at + 1 ] ) {
				at++;
				continue;
			}

			if ( '?' === this.html[ at + 1 ] ) {
				const closerAt = this.html.indexOf( '>', at + 2 );
				if ( -1 === closerAt ) {
					return false;
				}

				at = closerAt + 1;
				continue;
			}

			if ( this.isClosingTag ) {
				const closerAt = this.html.indexOf( '>', at + 3 );
				if ( -1 === closerAt ) {
					return false;
				}

				at = closerAt + 1;
				continue;
			}

			at++;
		}

		return false;
	}

	parseNextAttribute() {
		this.at += this.strspn( ' \t\f\r\n', this.at );

		if ( this.at >= this.html.length ) {
			return false;
		}

		const nameLength = '=' === this.html[ this.at ]
			? ( 1 + this.strcspn( '=/> \t\f\r\n', this.at + 1 ) )
			: this.strcspn( '=/> \r\f\r\n', this.at );

		if ( 0 === nameLength || this.at + nameLength >= this.html.length ) {
			return false;
		}

		const attributeStart = this.at;
		this.at += name.length;
		if ( this.at >= this.html.length ) {
			return false;
		}

		this.skipWhitespace();
		if ( this.at >= this.html.length ) {
			return false;
		}

		const hasValue = '=' === this.html[ this.at ];
		let valueStart;
		let valueLength;
		let attributeEnd;
		if ( hasValue ) {
			this.at++;
			this.skipWhitespace();
			if ( this.at >= this.html.length ) {
				return false;
			}

			switch ( this.html[ this.at ] ) {
				case '"':
				case "'":
					const quote = this.html[ this.at ];
					valueStart = this.at + 1;
					valueLength = this.strcspn( quote, valueStart );
					attributeEnd = valueStart + valueLength + 1;
					this.at = attributeEnd;
					break;

				default:
					valueStart = this.at;
					valueLength = this.strcspn( '> \t\f\r\n', valueStart );
					attributeEnd = valueStart + valueLength;
					this.at = attributeEnd;
			}
		} else {
			valueStart = this.at;
			valueLength = 0;
			attributeEnd = attributeStart + nameLength;
		}

		if ( attributeEnd >= this.html.length ) {
			return false;
		}

		if ( this.isClosingTag ) {
			return true;
		}

		this.attributes.push( new Attribute( attributeStart, attributeEnd - attributeStart, nameLength, valueStart, valueLength, hasValue ); )
		return true;
	}

	strspn( chars, at ) {
		const pattern = new RegExp( `^[${chars}]` );
		pattern.lastIndex = at;
		const match = pattern.exec( this.html );
		if ( match === null ) {
			return 0;
		}

		return match[0].length;
	}

	strcspn( chars, at ) {
		const pattern = new RegExp( `^[^${chars}]` );
		pattern.lastIndex = at;
		const match = pattern.exec( this.html );
		if ( match === null ) {
			return 0;
		}

		return match[0].length;
	}

	skipWhitespace() {
		this.at += this.strspn( ' \t\f\r\n', this.at );
	}

	afterTag() {
		this.getUpdatedHtml();
		this.tokenStartsAt = 0;
		this.tokenLength = 0;
		this.tagNameStartsAt = 0;
		this.tagNameLength = 0;
		this.isClosingTag = false;
		this.attributes = [];
	}

	getAttribute( name ) {
		const comparable = name.toLowerCase();
		const attribute = this.getParsedAttribute( name );
		if ( null === attribute ) {
			return null;
		}

		if ( ! attribute.hasValue ) {
			return true;
		}

		return this.html.substring( attribute.valueStartsAt, attribute.valueLength );
	}

	getParsedAttribute( caseInsensitiveName ) {
		loop: for ( const attribute of this.attributes ) {
			if ( attribute.nameLength !== caseInsensitiveName.length ) {
				continue;
			}

			at = attribute.start;
			for ( let i = 0; i < caseInsensitiveName.length; i++ ) {
				const c = caseInsensitiveName[ i ];
				const h = this.html[ at++ ];

				if ( c !== h && c.toLowerCase() !== h.toLowerCase() ) {
					continue loop;
				}
			}

			return attribute;
		}

		return null;
	}

	getTag() {
		return this.html.substring( this.tagNameStartsAt, this.tagNameLength ).toUpperCase();
	}

	hasSelfClosingFlag() {
		return '/' === this.html[ this.tokenStartsAt + this.tokenLength - 1 ];
	}

	isTagCloser() {
		return this.isClosingTag;
	}

	getUpdatedHtml() {
		return this.html;
	}


}
