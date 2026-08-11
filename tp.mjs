import { readFileSync } from 'node:fs';
import { TagProcessor as TP } from './tag-processor.mjs';

let html;
html = `
<div class="is-wide">
	<input type="text" enabled>
	<img src="https://s.wp.com/i/atat.png" title="The <img> tag is void.">
</div not-an-attribute>`;

html = readFileSync( '/Users/dmsnell/Downloads/single-page.html', 'utf8' );
//html = readFileSync( '/Users/dmsnell/code/Gutenberg/test/performance/assets/large-post.html', 'utf8' );

const tic = performance.now();
const tp = new TP( html );

let c = 0;
let a = 0;
while ( tp.nextTag() ) {
/*
	const attributes = tp.getAttributes().map( ( [ name, value ] ) => value === true ? name : `${name}="${value}"` ).join( ' ' );
	console.log( `<${tp.isTagCloser() ? '/' : ''}${tp.getTag()}> ${attributes}` );
*/
	c++
}

const toc = performance.now();
console.log( `Found ${c} tags` );
console.log( `Took ${ toc - tic } ms` );
console.log( `Ran at ${ html.length * 2 / ( ( toc - tic ) * 1000 ) } MB/s` );
