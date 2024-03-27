<?php

global $html4_named_character_entity_set;

/**
 * Set of HTML4 entity names plus a few extra that WordPress added to the list.
 *
 * This is for legacy support only; prefer the HTML5 version.
 * See $html5_named_character_entity_set;
 */
$html4_named_character_entity_set = WP_Token_Set::from_precomputed_table(
	array(
		// &nbsp;
		'nb' => "\x03sp;",
		// &iexcl;
		'ie' => "\x04xcl;",
		// &cedil; &cent;
		'ce' => "\x04dil;\x03nt;",
		// &pound;
		'po' => "\x04und;",
		// &curren; &cup;
		'cu' => "\x05rren;\x02p;",
		// &yen;
		'ye' => "\x02n;",
		// &brvbar;
		'br' => "\x05vbar;",
		// &sect;
		'se' => "\x03ct;",
		// &uml;
		'um' => "\x02l;",
		// &cong; &copy;
		'co' => "\x03ng;\x03py;",
		// &ordf; &ordm; &or;
		'or' => "\x03df;\x03dm;\x01;",
		// &lambda; &laquo; &lang; &larr;
		'la' => "\x05mbda;\x04quo;\x03ng;\x03rr;",
		// &notin; &not;
		'no' => "\x04tin;\x02t;",
		// &shy;
		'sh' => "\x02y;",
		// &real; &reg;
		're' => "\x03al;\x02g;",
		// &macr;
		'ma' => "\x03cr;",
		// &delta; &deg;
		'de' => "\x04lta;\x02g;",
		// &plusmn;
		'pl' => "\x05usmn;",
		// &acirc; &acute;
		'ac' => "\x04irc;\x04ute;",
		// &middot; &micro; &minus;
		'mi' => "\x05ddot;\x04cro;\x04nus;",
		// &para; &part;
		'pa' => "\x03ra;\x03rt;",
		// &radic; &raquo; &rang; &rarr;
		'ra' => "\x04dic;\x04quo;\x03ng;\x03rr;",
		// &iquest;
		'iq' => "\x05uest;",
		// &Agrave;
		'Ag' => "\x05rave;",
		// &Aacute;
		'Aa' => "\x05cute;",
		// &Acirc;
		'Ac' => "\x04irc;",
		// &Atilde;
		'At' => "\x05ilde;",
		// &Auml;
		'Au' => "\x03ml;",
		// &Aring;
		'Ar' => "\x04ing;",
		// &AElig;
		'AE' => "\x04lig;",
		// &Ccedil;
		'Cc' => "\x05edil;",
		// &Egrave;
		'Eg' => "\x05rave;",
		// &Eacute;
		'Ea' => "\x05cute;",
		// &Ecirc;
		'Ec' => "\x04irc;",
		// &Euml;
		'Eu' => "\x03ml;",
		// &Igrave;
		'Ig' => "\x05rave;",
		// &Iacute;
		'Ia' => "\x05cute;",
		// &Icirc;
		'Ic' => "\x04irc;",
		// &Iuml;
		'Iu' => "\x03ml;",
		// &ETH;
		'ET' => "\x02H;",
		// &Ntilde;
		'Nt' => "\x05ilde;",
		// &Ograve;
		'Og' => "\x05rave;",
		// &Oacute;
		'Oa' => "\x05cute;",
		// &Ocirc;
		'Oc' => "\x04irc;",
		// &Otilde;
		'Ot' => "\x05ilde;",
		// &Ouml;
		'Ou' => "\x03ml;",
		// &tilde; &times;
		'ti' => "\x04lde;\x04mes;",
		// &Oslash;
		'Os' => "\x05lash;",
		// &Ugrave;
		'Ug' => "\x05rave;",
		// &Uacute;
		'Ua' => "\x05cute;",
		// &Ucirc;
		'Uc' => "\x04irc;",
		// &Uuml;
		'Uu' => "\x03ml;",
		// &Yacute;
		'Ya' => "\x05cute;",
		// &THORN;
		'TH' => "\x04ORN;",
		// &szlig;
		'sz' => "\x04lig;",
		// &agrave;
		'ag' => "\x05rave;",
		// &aacute;
		'aa' => "\x05cute;",
		// &atilde;
		'at' => "\x05ilde;",
		// &auml;
		'au' => "\x03ml;",
		// &aring;
		'ar' => "\x04ing;",
		// &aelig;
		'ae' => "\x04lig;",
		// &ccedil;
		'cc' => "\x05edil;",
		// &egrave;
		'eg' => "\x05rave;",
		// &eacute;
		'ea' => "\x05cute;",
		// &ecirc;
		'ec' => "\x04irc;",
		// &euml; &euro;
		'eu' => "\x03ml;\x03ro;",
		// &igrave;
		'ig' => "\x05rave;",
		// &iacute;
		'ia' => "\x05cute;",
		// &icirc;
		'ic' => "\x04irc;",
		// &iuml;
		'iu' => "\x03ml;",
		// &eta; &eth;
		'et' => "\x02a;\x02h;",
		// &ntilde;
		'nt' => "\x05ilde;",
		// &ograve;
		'og' => "\x05rave;",
		// &oacute;
		'oa' => "\x05cute;",
		// &ocirc;
		'oc' => "\x04irc;",
		// &otilde; &otimes;
		'ot' => "\x05ilde;\x05imes;",
		// &ouml;
		'ou' => "\x03ml;",
		// &divide; &diams;
		'di' => "\x05vide;\x04ams;",
		// &oslash;
		'os' => "\x05lash;",
		// &ugrave;
		'ug' => "\x05rave;",
		// &uacute; &uarr;
		'ua' => "\x05cute;\x03rr;",
		// &ucirc;
		'uc' => "\x04irc;",
		// &uuml;
		'uu' => "\x03ml;",
		// &yacute;
		'ya' => "\x05cute;",
		// &thetasym; &there4; &thinsp; &theta; &thorn;
		'th' => "\x07etasym;\x05ere4;\x05insp;\x04eta;\x04orn;",
		// &yuml;
		'yu' => "\x03ml;",
		// &quot;
		'qu' => "\x03ot;",
		// &amp;
		'am' => "\x02p;",
		// &lt;
		'lt' => "\x01;",
		// &gt;
		'gt' => "\x01;",
		// &apos;
		'ap' => "\x03os;",
		// &OElig;
		'OE' => "\x04lig;",
		// &oelig;
		'oe' => "\x04lig;",
		// &Scaron;
		'Sc' => "\x05aron;",
		// &scaron;
		'sc' => "\x05aron;",
		// &Yuml;
		'Yu' => "\x03ml;",
		// &circ;
		'ci' => "\x03rc;",
		// &ensp;
		'en' => "\x03sp;",
		// &empty; &emsp;
		'em' => "\x04pty;\x03sp;",
		// &zwnj; &zwj;
		'zw' => "\x03nj;\x02j;",
		// &lrm;
		'lr' => "\x02m;",
		// &rlm;
		'rl' => "\x02m;",
		// &ndash;
		'nd' => "\x04ash;",
		// &mdash;
		'md' => "\x04ash;",
		// &lsaquo; &lsquo;
		'ls' => "\x05aquo;\x04quo;",
		// &rsaquo; &rsquo;
		'rs' => "\x05aquo;\x04quo;",
		// &sbquo;
		'sb' => "\x04quo;",
		// &ldquo;
		'ld' => "\x04quo;",
		// &rdquo;
		'rd' => "\x04quo;",
		// &bdquo;
		'bd' => "\x04quo;",
		// &dagger; &darr;
		'da' => "\x05gger;\x03rr;",
		// &Dagger;
		'Da' => "\x05gger;",
		// &permil; &perp;
		'pe' => "\x05rmil;\x03rp;",
		// &fnof;
		'fn' => "\x03of;",
		// &Alpha;
		'Al' => "\x04pha;",
		// &Beta;
		'Be' => "\x03ta;",
		// &Gamma;
		'Ga' => "\x04mma;",
		// &Delta;
		'De' => "\x04lta;",
		// &Epsilon;
		'Ep' => "\x06silon;",
		// &Zeta;
		'Ze' => "\x03ta;",
		// &Eta;
		'Et' => "\x02a;",
		// &Theta;
		'Th' => "\x04eta;",
		// &Iota;
		'Io' => "\x03ta;",
		// &Kappa;
		'Ka' => "\x04ppa;",
		// &Lambda;
		'La' => "\x05mbda;",
		// &Mu;
		'Mu' => "\x01;",
		// &Nu;
		'Nu' => "\x01;",
		// &Xi;
		'Xi' => "\x01;",
		// &Omicron; &Omega;
		'Om' => "\x06icron;\x04ega;",
		// &Pi;
		'Pi' => "\x01;",
		// &Rho;
		'Rh' => "\x02o;",
		// &Sigma;
		'Si' => "\x04gma;",
		// &Tau;
		'Ta' => "\x02u;",
		// &Upsilon;
		'Up' => "\x06silon;",
		// &Phi;
		'Ph' => "\x02i;",
		// &Chi;
		'Ch' => "\x02i;",
		// &Psi;
		'Ps' => "\x02i;",
		// &alefsym; &alpha;
		'al' => "\x06efsym;\x04pha;",
		// &beta;
		'be' => "\x03ta;",
		// &gamma;
		'ga' => "\x04mma;",
		// &epsilon;
		'ep' => "\x06silon;",
		// &zeta;
		'ze' => "\x03ta;",
		// &iota;
		'io' => "\x03ta;",
		// &kappa;
		'ka' => "\x04ppa;",
		// &mu;
		'mu' => "\x01;",
		// &nu;
		'nu' => "\x01;",
		// &xi;
		'xi' => "\x01;",
		// &omicron; &omega;
		'om' => "\x06icron;\x04ega;",
		// &piv; &pi;
		'pi' => "\x02v;\x01;",
		// &rho;
		'rh' => "\x02o;",
		// &sigmaf; &sigma; &sim;
		'si' => "\x05gmaf;\x04gma;\x02m;",
		// &tau;
		'ta' => "\x02u;",
		// &upsilon; &upsih;
		'up' => "\x06silon;\x04sih;",
		// &phi;
		'ph' => "\x02i;",
		// &chi;
		'ch' => "\x02i;",
		// &psi;
		'ps' => "\x02i;",
		// &bull;
		'bu' => "\x03ll;",
		// &hearts; &hellip;
		'he' => "\x05arts;\x05llip;",
		// &prime; &prod; &prop;
		'pr' => "\x04ime;\x03od;\x03op;",
		// &Prime;
		'Pr' => "\x04ime;",
		// &oline;
		'ol' => "\x04ine;",
		// &frac12; &frac14; &frac34; &frasl;
		'fr' => "\x05ac12;\x05ac14;\x05ac34;\x04asl;",
		// &weierp;
		'we' => "\x05ierp;",
		// &image;
		'im' => "\x04age;",
		// &trade;
		'tr' => "\x04ade;",
		// &harr;
		'ha' => "\x03rr;",
		// &crarr;
		'cr' => "\x04arr;",
		// &lArr;
		'lA' => "\x03rr;",
		// &uArr;
		'uA' => "\x03rr;",
		// &rArr;
		'rA' => "\x03rr;",
		// &dArr;
		'dA' => "\x03rr;",
		// &hArr;
		'hA' => "\x03rr;",
		// &forall;
		'fo' => "\x05rall;",
		// &exist;
		'ex' => "\x04ist;",
		// &nabla;
		'na' => "\x04bla;",
		// &isin;
		'is' => "\x03in;",
		// &ni;
		'ni' => "\x01;",
		// &sube; &sup1; &sup2; &sup3; &supe; &sub; &sum; &sup;
		'su' => "\x03be;\x03p1;\x03p2;\x03p3;\x03pe;\x02b;\x02m;\x02p;",
		// &lowast; &loz;
		'lo' => "\x05wast;\x02z;",
		// &infin; &int;
		'in' => "\x04fin;\x02t;",
		// &and; &ang;
		'an' => "\x02d;\x02g;",
		// &cap;
		'ca' => "\x02p;",
		// &asymp;
		'as' => "\x04ymp;",
		// &ne;
		'ne' => "\x01;",
		// &equiv;
		'eq' => "\x04uiv;",
		// &le;
		'le' => "\x01;",
		// &ge;
		'ge' => "\x01;",
		// &nsub;
		'ns' => "\x03ub;",
		// &oplus;
		'op' => "\x04lus;",
		// &sdot;
		'sd' => "\x03ot;",
		// &lceil;
		'lc' => "\x04eil;",
		// &rceil;
		'rc' => "\x04eil;",
		// &lfloor;
		'lf' => "\x05loor;",
		// &rfloor;
		'rf' => "\x05loor;",
		// &spades;
		'sp' => "\x05ades;",
		// &clubs;
		'cl' => "\x04ubs;",
	),
	''
);
