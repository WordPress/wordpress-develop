<?php

global $html4wp_named_character_entity_set;

/**
 * Set of HTML4 entity names plus a few extra that WordPress added to the list.
 *
 * This is for legacy support only; prefer the HTML5 version.
 * See $html5_named_character_entity_set;
 */
$html4wp_named_character_entity_set = WP_Token_Set::from_precomputed_table(
	2,
	array(
		// &AElig
		'AE' => "\x03lig",
		// &Aacute
		'Aa' => "\x04cute",
		// &Acirc
		'Ac' => "\x03irc",
		// &Agrave
		'Ag' => "\x04rave",
		// &Alpha
		'Al' => "\x03pha",
		// &Aring
		'Ar' => "\x03ing",
		// &Atilde
		'At' => "\x04ilde",
		// &Auml
		'Au' => "\x02ml",
		// &Beta
		'Be' => "\x02ta",
		// &Ccedil
		'Cc' => "\x04edil",
		// &Chi
		'Ch' => "\x01i",
		// &Dagger
		'Da' => "\x04gger",
		// &Delta
		'De' => "\x03lta",
		// &ETH
		'ET' => "\x01H",
		// &Eacute
		'Ea' => "\x04cute",
		// &Ecirc
		'Ec' => "\x03irc",
		// &Egrave
		'Eg' => "\x04rave",
		// &Epsilon
		'Ep' => "\x05silon",
		// &Eta
		'Et' => "\x01a",
		// &Euml
		'Eu' => "\x02ml",
		// &Gamma
		'Ga' => "\x03mma",
		// &Iacute
		'Ia' => "\x04cute",
		// &Icirc
		'Ic' => "\x03irc",
		// &Igrave
		'Ig' => "\x04rave",
		// &Iota
		'Io' => "\x02ta",
		// &Iuml
		'Iu' => "\x02ml",
		// &Kappa
		'Ka' => "\x03ppa",
		// &Lambda
		'La' => "\x04mbda",
		// &Ntilde
		'Nt' => "\x04ilde",
		// &OElig
		'OE' => "\x03lig",
		// &Oacute
		'Oa' => "\x04cute",
		// &Ocirc
		'Oc' => "\x03irc",
		// &Ograve
		'Og' => "\x04rave",
		// &Omicron &Omega
		'Om' => "\x05icron\x03ega",
		// &Oslash
		'Os' => "\x04lash",
		// &Otilde
		'Ot' => "\x04ilde",
		// &Ouml
		'Ou' => "\x02ml",
		// &Phi
		'Ph' => "\x01i",
		// &Prime
		'Pr' => "\x03ime",
		// &Psi
		'Ps' => "\x01i",
		// &Rho
		'Rh' => "\x01o",
		// &Scaron
		'Sc' => "\x04aron",
		// &Sigma
		'Si' => "\x03gma",
		// &THORN
		'TH' => "\x03ORN",
		// &Tau
		'Ta' => "\x01u",
		// &Theta
		'Th' => "\x03eta",
		// &Uacute
		'Ua' => "\x04cute",
		// &Ucirc
		'Uc' => "\x03irc",
		// &Ugrave
		'Ug' => "\x04rave",
		// &Upsilon
		'Up' => "\x05silon",
		// &Uuml
		'Uu' => "\x02ml",
		// &Yacute
		'Ya' => "\x04cute",
		// &Yuml
		'Yu' => "\x02ml",
		// &Zeta
		'Ze' => "\x02ta",
		// &aacute
		'aa' => "\x04cute",
		// &acirc &acute
		'ac' => "\x03irc\x03ute",
		// &aelig
		'ae' => "\x03lig",
		// &agrave
		'ag' => "\x04rave",
		// &alefsym &alpha
		'al' => "\x05efsym\x03pha",
		// &amp
		'am' => "\x01p",
		// &and &ang
		'an' => "\x01d\x01g",
		// &apos
		'ap' => "\x02os",
		// &aring
		'ar' => "\x03ing",
		// &asymp
		'as' => "\x03ymp",
		// &atilde
		'at' => "\x04ilde",
		// &auml
		'au' => "\x02ml",
		// &bdquo
		'bd' => "\x03quo",
		// &beta
		'be' => "\x02ta",
		// &brvbar
		'br' => "\x04vbar",
		// &bull
		'bu' => "\x02ll",
		// &cap
		'ca' => "\x01p",
		// &ccedil
		'cc' => "\x04edil",
		// &cedil &cent
		'ce' => "\x03dil\x02nt",
		// &chi
		'ch' => "\x01i",
		// &circ
		'ci' => "\x02rc",
		// &clubs
		'cl' => "\x03ubs",
		// &cong &copy
		'co' => "\x02ng\x02py",
		// &crarr
		'cr' => "\x03arr",
		// &curren &cup
		'cu' => "\x04rren\x01p",
		// &dArr
		'dA' => "\x02rr",
		// &dagger &darr
		'da' => "\x04gger\x02rr",
		// &delta &deg
		'de' => "\x03lta\x01g",
		// &divide &diams
		'di' => "\x04vide\x03ams",
		// &eacute
		'ea' => "\x04cute",
		// &ecirc
		'ec' => "\x03irc",
		// &egrave
		'eg' => "\x04rave",
		// &empty &emsp
		'em' => "\x03pty\x02sp",
		// &ensp
		'en' => "\x02sp",
		// &epsilon
		'ep' => "\x05silon",
		// &equiv
		'eq' => "\x03uiv",
		// &eta &eth
		'et' => "\x01a\x01h",
		// &euml &euro
		'eu' => "\x02ml\x02ro",
		// &exist
		'ex' => "\x03ist",
		// &fnof
		'fn' => "\x02of",
		// &forall
		'fo' => "\x04rall",
		// &frac12 &frac14 &frac34 &frasl
		'fr' => "\x04ac12\x04ac14\x04ac34\x03asl",
		// &gamma
		'ga' => "\x03mma",
		// &hArr
		'hA' => "\x02rr",
		// &harr
		'ha' => "\x02rr",
		// &hearts &hellip
		'he' => "\x04arts\x04llip",
		// &iacute
		'ia' => "\x04cute",
		// &icirc
		'ic' => "\x03irc",
		// &iexcl
		'ie' => "\x03xcl",
		// &igrave
		'ig' => "\x04rave",
		// &image
		'im' => "\x03age",
		// &infin &int
		'in' => "\x03fin\x01t",
		// &iota
		'io' => "\x02ta",
		// &iquest
		'iq' => "\x04uest",
		// &isin
		'is' => "\x02in",
		// &iuml
		'iu' => "\x02ml",
		// &kappa
		'ka' => "\x03ppa",
		// &lArr
		'lA' => "\x02rr",
		// &lambda &laquo &lang &larr
		'la' => "\x04mbda\x03quo\x02ng\x02rr",
		// &lceil
		'lc' => "\x03eil",
		// &ldquo
		'ld' => "\x03quo",
		// &lfloor
		'lf' => "\x04loor",
		// &lowast &loz
		'lo' => "\x04wast\x01z",
		// &lrm
		'lr' => "\x01m",
		// &lsaquo &lsquo
		'ls' => "\x04aquo\x03quo",
		// &macr
		'ma' => "\x02cr",
		// &mdash
		'md' => "\x03ash",
		// &middot &micro &minus
		'mi' => "\x04ddot\x03cro\x03nus",
		// &nabla
		'na' => "\x03bla",
		// &nbsp
		'nb' => "\x02sp",
		// &ndash
		'nd' => "\x03ash",
		// &notin &not
		'no' => "\x03tin\x01t",
		// &nsub
		'ns' => "\x02ub",
		// &ntilde
		'nt' => "\x04ilde",
		// &oacute
		'oa' => "\x04cute",
		// &ocirc
		'oc' => "\x03irc",
		// &oelig
		'oe' => "\x03lig",
		// &ograve
		'og' => "\x04rave",
		// &oline
		'ol' => "\x03ine",
		// &omicron &omega
		'om' => "\x05icron\x03ega",
		// &oplus
		'op' => "\x03lus",
		// &ordf &ordm
		'or' => "\x02df\x02dm",
		// &oslash
		'os' => "\x04lash",
		// &otilde &otimes
		'ot' => "\x04ilde\x04imes",
		// &ouml
		'ou' => "\x02ml",
		// &para &part
		'pa' => "\x02ra\x02rt",
		// &permil &perp
		'pe' => "\x04rmil\x02rp",
		// &phi
		'ph' => "\x01i",
		// &piv
		'pi' => "\x01v",
		// &plusmn
		'pl' => "\x04usmn",
		// &pound
		'po' => "\x03und",
		// &prime &prod &prop
		'pr' => "\x03ime\x02od\x02op",
		// &psi
		'ps' => "\x01i",
		// &quot
		'qu' => "\x02ot",
		// &rArr
		'rA' => "\x02rr",
		// &radic &raquo &rang &rarr
		'ra' => "\x03dic\x03quo\x02ng\x02rr",
		// &rceil
		'rc' => "\x03eil",
		// &rdquo
		'rd' => "\x03quo",
		// &real &reg
		're' => "\x02al\x01g",
		// &rfloor
		'rf' => "\x04loor",
		// &rho
		'rh' => "\x01o",
		// &rlm
		'rl' => "\x01m",
		// &rsaquo &rsquo
		'rs' => "\x04aquo\x03quo",
		// &sbquo
		'sb' => "\x03quo",
		// &scaron
		'sc' => "\x04aron",
		// &sdot
		'sd' => "\x02ot",
		// &sect
		'se' => "\x02ct",
		// &shy
		'sh' => "\x01y",
		// &sigmaf &sigma &sim
		'si' => "\x04gmaf\x03gma\x01m",
		// &spades
		'sp' => "\x04ades",
		// &sube &sup1 &sup2 &sup3 &supe &sub &sum &sup
		'su' => "\x02be\x02p1\x02p2\x02p3\x02pe\x01b\x01m\x01p",
		// &szlig
		'sz' => "\x03lig",
		// &tau
		'ta' => "\x01u",
		// &thetasym &there4 &thinsp &theta &thorn
		'th' => "\x06etasym\x04ere4\x04insp\x03eta\x03orn",
		// &tilde &times
		'ti' => "\x03lde\x03mes",
		// &trade
		'tr' => "\x03ade",
		// &uArr
		'uA' => "\x02rr",
		// &uacute &uarr
		'ua' => "\x04cute\x02rr",
		// &ucirc
		'uc' => "\x03irc",
		// &ugrave
		'ug' => "\x04rave",
		// &uml
		'um' => "\x01l",
		// &upsilon &upsih
		'up' => "\x05silon\x03sih",
		// &uuml
		'uu' => "\x02ml",
		// &weierp
		'we' => "\x04ierp",
		// &yacute
		'ya' => "\x04cute",
		// &yen
		'ye' => "\x01n",
		// &yuml
		'yu' => "\x02ml",
		// &zeta
		'ze' => "\x02ta",
		// &zwnj &zwj
		'zw' => "\x02nj\x01j",
	),
	"MuNuPiXigegtleltmuneninuorpixi"
);
