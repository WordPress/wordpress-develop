<?php

global $html5_named_character_entity_set;

/**
 * Set of named character references in the HTML5 specification.
 *
 * This list will never change, according to the spec. Each named
 * character reference is case-sensitive and the presence or absence
 * of the semicolon is significant. Without the semicolon, the rules
 * for an ambiguous ampersand govern whether the following text is
 * to be interpreted as a character reference or not.
 *
 * @link https://html.spec.whatwg.org/entities.json.
 */
$html5_named_character_entity_set = WP_Token_Set::from_precomputed_table(
	array(
		// &AElig; &AElig
		'AE' => "\x04lig;\x03lig",
		// &AMP; &AMP
		'AM' => "\x02P;\x01P",
		// &Aacute; &Aacute
		'Aa' => "\x05cute;\x04cute",
		// &Abreve;
		'Ab' => "\x05reve;",
		// &Acirc; &Acirc &Acy;
		'Ac' => "\x04irc;\x03irc\x02y;",
		// &Afr;
		'Af' => "\x02r;",
		// &Agrave; &Agrave
		'Ag' => "\x05rave;\x04rave",
		// &Alpha;
		'Al' => "\x04pha;",
		// &Amacr;
		'Am' => "\x04acr;",
		// &And;
		'An' => "\x02d;",
		// &Aogon; &Aopf;
		'Ao' => "\x04gon;\x03pf;",
		// &ApplyFunction;
		'Ap' => "\x0cplyFunction;",
		// &Aring; &Aring
		'Ar' => "\x04ing;\x03ing",
		// &Assign; &Ascr;
		'As' => "\x05sign;\x03cr;",
		// &Atilde; &Atilde
		'At' => "\x05ilde;\x04ilde",
		// &Auml; &Auml
		'Au' => "\x03ml;\x02ml",
		// &Backslash; &Barwed; &Barv;
		'Ba' => "\x08ckslash;\x05rwed;\x03rv;",
		// &Bcy;
		'Bc' => "\x02y;",
		// &Bernoullis; &Because; &Beta;
		'Be' => "\x09rnoullis;\x06cause;\x03ta;",
		// &Bfr;
		'Bf' => "\x02r;",
		// &Bopf;
		'Bo' => "\x03pf;",
		// &Breve;
		'Br' => "\x04eve;",
		// &Bscr;
		'Bs' => "\x03cr;",
		// &Bumpeq;
		'Bu' => "\x05mpeq;",
		// &CHcy;
		'CH' => "\x03cy;",
		// &COPY; &COPY
		'CO' => "\x03PY;\x02PY",
		// &CapitalDifferentialD; &Cayleys; &Cacute; &Cap;
		'Ca' => "\x13pitalDifferentialD;\x06yleys;\x05cute;\x02p;",
		// &Cconint; &Ccaron; &Ccedil; &Ccedil &Ccirc;
		'Cc' => "\x06onint;\x05aron;\x05edil;\x04edil\x04irc;",
		// &Cdot;
		'Cd' => "\x03ot;",
		// &CenterDot; &Cedilla;
		'Ce' => "\x08nterDot;\x06dilla;",
		// &Cfr;
		'Cf' => "\x02r;",
		// &Chi;
		'Ch' => "\x02i;",
		// &CircleMinus; &CircleTimes; &CirclePlus; &CircleDot;
		'Ci' => "\x0arcleMinus;\x0arcleTimes;\x09rclePlus;\x08rcleDot;",
		// &ClockwiseContourIntegral; &CloseCurlyDoubleQuote; &CloseCurlyQuote;
		'Cl' => "\x17ockwiseContourIntegral;\x14oseCurlyDoubleQuote;\x0eoseCurlyQuote;",
		// &CounterClockwiseContourIntegral; &ContourIntegral; &Congruent; &Coproduct; &Colone; &Conint; &Colon; &Copf;
		'Co' => "\x1eunterClockwiseContourIntegral;\x0entourIntegral;\x08ngruent;\x08product;\x05lone;\x05nint;\x04lon;\x03pf;",
		// &Cross;
		'Cr' => "\x04oss;",
		// &Cscr;
		'Cs' => "\x03cr;",
		// &CupCap; &Cup;
		'Cu' => "\x05pCap;\x02p;",
		// &DDotrahd; &DD;
		'DD' => "\x07otrahd;\x01;",
		// &DJcy;
		'DJ' => "\x03cy;",
		// &DScy;
		'DS' => "\x03cy;",
		// &DZcy;
		'DZ' => "\x03cy;",
		// &Dagger; &Dashv; &Darr;
		'Da' => "\x05gger;\x04shv;\x03rr;",
		// &Dcaron; &Dcy;
		'Dc' => "\x05aron;\x02y;",
		// &Delta; &Del;
		'De' => "\x04lta;\x02l;",
		// &Dfr;
		'Df' => "\x02r;",
		// &DiacriticalDoubleAcute; &DiacriticalAcute; &DiacriticalGrave; &DiacriticalTilde; &DiacriticalDot; &DifferentialD; &Diamond;
		'Di' => "\x15acriticalDoubleAcute;\x0facriticalAcute;\x0facriticalGrave;\x0facriticalTilde;\x0dacriticalDot;\x0cfferentialD;\x06amond;",
		// &DoubleLongLeftRightArrow; &DoubleContourIntegral; &DoubleLeftRightArrow; &DoubleLongRightArrow; &DoubleLongLeftArrow; &DownLeftRightVector; &DownRightTeeVector; &DownRightVectorBar; &DoubleUpDownArrow; &DoubleVerticalBar; &DownLeftTeeVector; &DownLeftVectorBar; &DoubleRightArrow; &DownArrowUpArrow; &DoubleDownArrow; &DoubleLeftArrow; &DownRightVector; &DoubleRightTee; &DownLeftVector; &DoubleLeftTee; &DoubleUpArrow; &DownArrowBar; &DownTeeArrow; &DoubleDot; &DownArrow; &DownBreve; &Downarrow; &DotEqual; &DownTee; &DotDot; &Dopf; &Dot;
		'Do' => "\x17ubleLongLeftRightArrow;\x14ubleContourIntegral;\x13ubleLeftRightArrow;\x13ubleLongRightArrow;\x12ubleLongLeftArrow;\x12wnLeftRightVector;\x11wnRightTeeVector;\x11wnRightVectorBar;\x10ubleUpDownArrow;\x10ubleVerticalBar;\x10wnLeftTeeVector;\x10wnLeftVectorBar;\x0fubleRightArrow;\x0fwnArrowUpArrow;\x0eubleDownArrow;\x0eubleLeftArrow;\x0ewnRightVector;\x0dubleRightTee;\x0dwnLeftVector;\x0cubleLeftTee;\x0cubleUpArrow;\x0bwnArrowBar;\x0bwnTeeArrow;\x08ubleDot;\x08wnArrow;\x08wnBreve;\x08wnarrow;\x07tEqual;\x06wnTee;\x05tDot;\x03pf;\x02t;",
		// &Dstrok; &Dscr;
		'Ds' => "\x05trok;\x03cr;",
		// &ENG;
		'EN' => "\x02G;",
		// &ETH; &ETH
		'ET' => "\x02H;\x01H",
		// &Eacute; &Eacute
		'Ea' => "\x05cute;\x04cute",
		// &Ecaron; &Ecirc; &Ecirc &Ecy;
		'Ec' => "\x05aron;\x04irc;\x03irc\x02y;",
		// &Edot;
		'Ed' => "\x03ot;",
		// &Efr;
		'Ef' => "\x02r;",
		// &Egrave; &Egrave
		'Eg' => "\x05rave;\x04rave",
		// &Element;
		'El' => "\x06ement;",
		// &EmptyVerySmallSquare; &EmptySmallSquare; &Emacr;
		'Em' => "\x13ptyVerySmallSquare;\x0fptySmallSquare;\x04acr;",
		// &Eogon; &Eopf;
		'Eo' => "\x04gon;\x03pf;",
		// &Epsilon;
		'Ep' => "\x06silon;",
		// &Equilibrium; &EqualTilde; &Equal;
		'Eq' => "\x0auilibrium;\x09ualTilde;\x04ual;",
		// &Escr; &Esim;
		'Es' => "\x03cr;\x03im;",
		// &Eta;
		'Et' => "\x02a;",
		// &Euml; &Euml
		'Eu' => "\x03ml;\x02ml",
		// &ExponentialE; &Exists;
		'Ex' => "\x0bponentialE;\x05ists;",
		// &Fcy;
		'Fc' => "\x02y;",
		// &Ffr;
		'Ff' => "\x02r;",
		// &FilledVerySmallSquare; &FilledSmallSquare;
		'Fi' => "\x14lledVerySmallSquare;\x10lledSmallSquare;",
		// &Fouriertrf; &ForAll; &Fopf;
		'Fo' => "\x09uriertrf;\x05rAll;\x03pf;",
		// &Fscr;
		'Fs' => "\x03cr;",
		// &GJcy;
		'GJ' => "\x03cy;",
		// &GT;
		'GT' => "\x01;",
		// &Gammad; &Gamma;
		'Ga' => "\x05mmad;\x04mma;",
		// &Gbreve;
		'Gb' => "\x05reve;",
		// &Gcedil; &Gcirc; &Gcy;
		'Gc' => "\x05edil;\x04irc;\x02y;",
		// &Gdot;
		'Gd' => "\x03ot;",
		// &Gfr;
		'Gf' => "\x02r;",
		// &Gg;
		'Gg' => "\x01;",
		// &Gopf;
		'Go' => "\x03pf;",
		// &GreaterSlantEqual; &GreaterEqualLess; &GreaterFullEqual; &GreaterGreater; &GreaterEqual; &GreaterTilde; &GreaterLess;
		'Gr' => "\x10eaterSlantEqual;\x0featerEqualLess;\x0featerFullEqual;\x0deaterGreater;\x0beaterEqual;\x0beaterTilde;\x0aeaterLess;",
		// &Gscr;
		'Gs' => "\x03cr;",
		// &Gt;
		'Gt' => "\x01;",
		// &HARDcy;
		'HA' => "\x05RDcy;",
		// &Hacek; &Hat;
		'Ha' => "\x04cek;\x02t;",
		// &Hcirc;
		'Hc' => "\x04irc;",
		// &Hfr;
		'Hf' => "\x02r;",
		// &HilbertSpace;
		'Hi' => "\x0blbertSpace;",
		// &HorizontalLine; &Hopf;
		'Ho' => "\x0drizontalLine;\x03pf;",
		// &Hstrok; &Hscr;
		'Hs' => "\x05trok;\x03cr;",
		// &HumpDownHump; &HumpEqual;
		'Hu' => "\x0bmpDownHump;\x08mpEqual;",
		// &IEcy;
		'IE' => "\x03cy;",
		// &IJlig;
		'IJ' => "\x04lig;",
		// &IOcy;
		'IO' => "\x03cy;",
		// &Iacute; &Iacute
		'Ia' => "\x05cute;\x04cute",
		// &Icirc; &Icirc &Icy;
		'Ic' => "\x04irc;\x03irc\x02y;",
		// &Idot;
		'Id' => "\x03ot;",
		// &Ifr;
		'If' => "\x02r;",
		// &Igrave; &Igrave
		'Ig' => "\x05rave;\x04rave",
		// &ImaginaryI; &Implies; &Imacr; &Im;
		'Im' => "\x09aginaryI;\x06plies;\x04acr;\x01;",
		// &InvisibleComma; &InvisibleTimes; &Intersection; &Integral; &Int;
		'In' => "\x0dvisibleComma;\x0dvisibleTimes;\x0btersection;\x07tegral;\x02t;",
		// &Iogon; &Iopf; &Iota;
		'Io' => "\x04gon;\x03pf;\x03ta;",
		// &Iscr;
		'Is' => "\x03cr;",
		// &Itilde;
		'It' => "\x05ilde;",
		// &Iukcy; &Iuml; &Iuml
		'Iu' => "\x04kcy;\x03ml;\x02ml",
		// &Jcirc; &Jcy;
		'Jc' => "\x04irc;\x02y;",
		// &Jfr;
		'Jf' => "\x02r;",
		// &Jopf;
		'Jo' => "\x03pf;",
		// &Jsercy; &Jscr;
		'Js' => "\x05ercy;\x03cr;",
		// &Jukcy;
		'Ju' => "\x04kcy;",
		// &KHcy;
		'KH' => "\x03cy;",
		// &KJcy;
		'KJ' => "\x03cy;",
		// &Kappa;
		'Ka' => "\x04ppa;",
		// &Kcedil; &Kcy;
		'Kc' => "\x05edil;\x02y;",
		// &Kfr;
		'Kf' => "\x02r;",
		// &Kopf;
		'Ko' => "\x03pf;",
		// &Kscr;
		'Ks' => "\x03cr;",
		// &LJcy;
		'LJ' => "\x03cy;",
		// &LT;
		'LT' => "\x01;",
		// &Laplacetrf; &Lacute; &Lambda; &Lang; &Larr;
		'La' => "\x09placetrf;\x05cute;\x05mbda;\x03ng;\x03rr;",
		// &Lcaron; &Lcedil; &Lcy;
		'Lc' => "\x05aron;\x05edil;\x02y;",
		// &LeftArrowRightArrow; &LeftDoubleBracket; &LeftDownTeeVector; &LeftDownVectorBar; &LeftTriangleEqual; &LeftAngleBracket; &LeftUpDownVector; &LessEqualGreater; &LeftRightVector; &LeftTriangleBar; &LeftUpTeeVector; &LeftUpVectorBar; &LeftDownVector; &LeftRightArrow; &Leftrightarrow; &LessSlantEqual; &LeftTeeVector; &LeftVectorBar; &LessFullEqual; &LeftArrowBar; &LeftTeeArrow; &LeftTriangle; &LeftUpVector; &LeftCeiling; &LessGreater; &LeftVector; &LeftArrow; &LeftFloor; &Leftarrow; &LessTilde; &LessLess; &LeftTee;
		'Le' => "\x12ftArrowRightArrow;\x10ftDoubleBracket;\x10ftDownTeeVector;\x10ftDownVectorBar;\x10ftTriangleEqual;\x0fftAngleBracket;\x0fftUpDownVector;\x0fssEqualGreater;\x0eftRightVector;\x0eftTriangleBar;\x0eftUpTeeVector;\x0eftUpVectorBar;\x0dftDownVector;\x0dftRightArrow;\x0dftrightarrow;\x0dssSlantEqual;\x0cftTeeVector;\x0cftVectorBar;\x0cssFullEqual;\x0bftArrowBar;\x0bftTeeArrow;\x0bftTriangle;\x0bftUpVector;\x0aftCeiling;\x0assGreater;\x09ftVector;\x08ftArrow;\x08ftFloor;\x08ftarrow;\x08ssTilde;\x07ssLess;\x06ftTee;",
		// &Lfr;
		'Lf' => "\x02r;",
		// &Lleftarrow; &Ll;
		'Ll' => "\x09eftarrow;\x01;",
		// &Lmidot;
		'Lm' => "\x05idot;",
		// &LongLeftRightArrow; &Longleftrightarrow; &LowerRightArrow; &LongRightArrow; &Longrightarrow; &LowerLeftArrow; &LongLeftArrow; &Longleftarrow; &Lopf;
		'Lo' => "\x11ngLeftRightArrow;\x11ngleftrightarrow;\x0ewerRightArrow;\x0dngRightArrow;\x0dngrightarrow;\x0dwerLeftArrow;\x0cngLeftArrow;\x0cngleftarrow;\x03pf;",
		// &Lstrok; &Lscr; &Lsh;
		'Ls' => "\x05trok;\x03cr;\x02h;",
		// &Lt;
		'Lt' => "\x01;",
		// &Map;
		'Ma' => "\x02p;",
		// &Mcy;
		'Mc' => "\x02y;",
		// &MediumSpace; &Mellintrf;
		'Me' => "\x0adiumSpace;\x08llintrf;",
		// &Mfr;
		'Mf' => "\x02r;",
		// &MinusPlus;
		'Mi' => "\x08nusPlus;",
		// &Mopf;
		'Mo' => "\x03pf;",
		// &Mscr;
		'Ms' => "\x03cr;",
		// &Mu;
		'Mu' => "\x01;",
		// &NJcy;
		'NJ' => "\x03cy;",
		// &Nacute;
		'Na' => "\x05cute;",
		// &Ncaron; &Ncedil; &Ncy;
		'Nc' => "\x05aron;\x05edil;\x02y;",
		// &NegativeVeryThinSpace; &NestedGreaterGreater; &NegativeMediumSpace; &NegativeThickSpace; &NegativeThinSpace; &NestedLessLess; &NewLine;
		'Ne' => "\x14gativeVeryThinSpace;\x13stedGreaterGreater;\x12gativeMediumSpace;\x11gativeThickSpace;\x10gativeThinSpace;\x0dstedLessLess;\x06wLine;",
		// &Nfr;
		'Nf' => "\x02r;",
		// &NotNestedGreaterGreater; &NotSquareSupersetEqual; &NotPrecedesSlantEqual; &NotRightTriangleEqual; &NotSucceedsSlantEqual; &NotDoubleVerticalBar; &NotGreaterSlantEqual; &NotLeftTriangleEqual; &NotSquareSubsetEqual; &NotGreaterFullEqual; &NotRightTriangleBar; &NotLeftTriangleBar; &NotGreaterGreater; &NotLessSlantEqual; &NotNestedLessLess; &NotReverseElement; &NotSquareSuperset; &NotTildeFullEqual; &NonBreakingSpace; &NotPrecedesEqual; &NotRightTriangle; &NotSucceedsEqual; &NotSucceedsTilde; &NotSupersetEqual; &NotGreaterEqual; &NotGreaterTilde; &NotHumpDownHump; &NotLeftTriangle; &NotSquareSubset; &NotGreaterLess; &NotLessGreater; &NotSubsetEqual; &NotVerticalBar; &NotEqualTilde; &NotTildeEqual; &NotTildeTilde; &NotCongruent; &NotHumpEqual; &NotLessEqual; &NotLessTilde; &NotLessLess; &NotPrecedes; &NotSucceeds; &NotSuperset; &NotElement; &NotGreater; &NotCupCap; &NotExists; &NotSubset; &NotEqual; &NotTilde; &NoBreak; &NotLess; &Nopf; &Not;
		'No' => "\x16tNestedGreaterGreater;\x15tSquareSupersetEqual;\x14tPrecedesSlantEqual;\x14tRightTriangleEqual;\x14tSucceedsSlantEqual;\x13tDoubleVerticalBar;\x13tGreaterSlantEqual;\x13tLeftTriangleEqual;\x13tSquareSubsetEqual;\x12tGreaterFullEqual;\x12tRightTriangleBar;\x11tLeftTriangleBar;\x10tGreaterGreater;\x10tLessSlantEqual;\x10tNestedLessLess;\x10tReverseElement;\x10tSquareSuperset;\x10tTildeFullEqual;\x0fnBreakingSpace;\x0ftPrecedesEqual;\x0ftRightTriangle;\x0ftSucceedsEqual;\x0ftSucceedsTilde;\x0ftSupersetEqual;\x0etGreaterEqual;\x0etGreaterTilde;\x0etHumpDownHump;\x0etLeftTriangle;\x0etSquareSubset;\x0dtGreaterLess;\x0dtLessGreater;\x0dtSubsetEqual;\x0dtVerticalBar;\x0ctEqualTilde;\x0ctTildeEqual;\x0ctTildeTilde;\x0btCongruent;\x0btHumpEqual;\x0btLessEqual;\x0btLessTilde;\x0atLessLess;\x0atPrecedes;\x0atSucceeds;\x0atSuperset;\x09tElement;\x09tGreater;\x08tCupCap;\x08tExists;\x08tSubset;\x07tEqual;\x07tTilde;\x06Break;\x06tLess;\x03pf;\x02t;",
		// &Nscr;
		'Ns' => "\x03cr;",
		// &Ntilde; &Ntilde
		'Nt' => "\x05ilde;\x04ilde",
		// &Nu;
		'Nu' => "\x01;",
		// &OElig;
		'OE' => "\x04lig;",
		// &Oacute; &Oacute
		'Oa' => "\x05cute;\x04cute",
		// &Ocirc; &Ocirc &Ocy;
		'Oc' => "\x04irc;\x03irc\x02y;",
		// &Odblac;
		'Od' => "\x05blac;",
		// &Ofr;
		'Of' => "\x02r;",
		// &Ograve; &Ograve
		'Og' => "\x05rave;\x04rave",
		// &Omicron; &Omacr; &Omega;
		'Om' => "\x06icron;\x04acr;\x04ega;",
		// &Oopf;
		'Oo' => "\x03pf;",
		// &OpenCurlyDoubleQuote; &OpenCurlyQuote;
		'Op' => "\x13enCurlyDoubleQuote;\x0denCurlyQuote;",
		// &Or;
		'Or' => "\x01;",
		// &Oslash; &Oslash &Oscr;
		'Os' => "\x05lash;\x04lash\x03cr;",
		// &Otilde; &Otimes; &Otilde
		'Ot' => "\x05ilde;\x05imes;\x04ilde",
		// &Ouml; &Ouml
		'Ou' => "\x03ml;\x02ml",
		// &OverParenthesis; &OverBracket; &OverBrace; &OverBar;
		'Ov' => "\x0eerParenthesis;\x0aerBracket;\x08erBrace;\x06erBar;",
		// &PartialD;
		'Pa' => "\x07rtialD;",
		// &Pcy;
		'Pc' => "\x02y;",
		// &Pfr;
		'Pf' => "\x02r;",
		// &Phi;
		'Ph' => "\x02i;",
		// &Pi;
		'Pi' => "\x01;",
		// &PlusMinus;
		'Pl' => "\x08usMinus;",
		// &Poincareplane; &Popf;
		'Po' => "\x0cincareplane;\x03pf;",
		// &PrecedesSlantEqual; &PrecedesEqual; &PrecedesTilde; &Proportional; &Proportion; &Precedes; &Product; &Prime; &Pr;
		'Pr' => "\x11ecedesSlantEqual;\x0cecedesEqual;\x0cecedesTilde;\x0boportional;\x09oportion;\x07ecedes;\x06oduct;\x04ime;\x01;",
		// &Pscr; &Psi;
		'Ps' => "\x03cr;\x02i;",
		// &QUOT; &QUOT
		'QU' => "\x03OT;\x02OT",
		// &Qfr;
		'Qf' => "\x02r;",
		// &Qopf;
		'Qo' => "\x03pf;",
		// &Qscr;
		'Qs' => "\x03cr;",
		// &RBarr;
		'RB' => "\x04arr;",
		// &REG; &REG
		'RE' => "\x02G;\x01G",
		// &Racute; &Rarrtl; &Rang; &Rarr;
		'Ra' => "\x05cute;\x05rrtl;\x03ng;\x03rr;",
		// &Rcaron; &Rcedil; &Rcy;
		'Rc' => "\x05aron;\x05edil;\x02y;",
		// &ReverseUpEquilibrium; &ReverseEquilibrium; &ReverseElement; &Re;
		'Re' => "\x13verseUpEquilibrium;\x11verseEquilibrium;\x0dverseElement;\x01;",
		// &Rfr;
		'Rf' => "\x02r;",
		// &Rho;
		'Rh' => "\x02o;",
		// &RightArrowLeftArrow; &RightDoubleBracket; &RightDownTeeVector; &RightDownVectorBar; &RightTriangleEqual; &RightAngleBracket; &RightUpDownVector; &RightTriangleBar; &RightUpTeeVector; &RightUpVectorBar; &RightDownVector; &RightTeeVector; &RightVectorBar; &RightArrowBar; &RightTeeArrow; &RightTriangle; &RightUpVector; &RightCeiling; &RightVector; &RightArrow; &RightFloor; &Rightarrow; &RightTee;
		'Ri' => "\x12ghtArrowLeftArrow;\x11ghtDoubleBracket;\x11ghtDownTeeVector;\x11ghtDownVectorBar;\x11ghtTriangleEqual;\x10ghtAngleBracket;\x10ghtUpDownVector;\x0fghtTriangleBar;\x0fghtUpTeeVector;\x0fghtUpVectorBar;\x0eghtDownVector;\x0dghtTeeVector;\x0dghtVectorBar;\x0cghtArrowBar;\x0cghtTeeArrow;\x0cghtTriangle;\x0cghtUpVector;\x0bghtCeiling;\x0aghtVector;\x09ghtArrow;\x09ghtFloor;\x09ghtarrow;\x07ghtTee;",
		// &RoundImplies; &Ropf;
		'Ro' => "\x0bundImplies;\x03pf;",
		// &Rrightarrow;
		'Rr' => "\x0aightarrow;",
		// &Rscr; &Rsh;
		'Rs' => "\x03cr;\x02h;",
		// &RuleDelayed;
		'Ru' => "\x0aleDelayed;",
		// &SHCHcy; &SHcy;
		'SH' => "\x05CHcy;\x03cy;",
		// &SOFTcy;
		'SO' => "\x05FTcy;",
		// &Sacute;
		'Sa' => "\x05cute;",
		// &Scaron; &Scedil; &Scirc; &Scy; &Sc;
		'Sc' => "\x05aron;\x05edil;\x04irc;\x02y;\x01;",
		// &Sfr;
		'Sf' => "\x02r;",
		// &ShortRightArrow; &ShortDownArrow; &ShortLeftArrow; &ShortUpArrow;
		'Sh' => "\x0eortRightArrow;\x0dortDownArrow;\x0dortLeftArrow;\x0bortUpArrow;",
		// &Sigma;
		'Si' => "\x04gma;",
		// &SmallCircle;
		'Sm' => "\x0aallCircle;",
		// &Sopf;
		'So' => "\x03pf;",
		// &SquareSupersetEqual; &SquareIntersection; &SquareSubsetEqual; &SquareSuperset; &SquareSubset; &SquareUnion; &Square; &Sqrt;
		'Sq' => "\x12uareSupersetEqual;\x11uareIntersection;\x10uareSubsetEqual;\x0duareSuperset;\x0buareSubset;\x0auareUnion;\x05uare;\x03rt;",
		// &Sscr;
		'Ss' => "\x03cr;",
		// &Star;
		'St' => "\x03ar;",
		// &SucceedsSlantEqual; &SucceedsEqual; &SucceedsTilde; &SupersetEqual; &SubsetEqual; &Succeeds; &SuchThat; &Superset; &Subset; &Supset; &Sub; &Sum; &Sup;
		'Su' => "\x11cceedsSlantEqual;\x0ccceedsEqual;\x0ccceedsTilde;\x0cpersetEqual;\x0absetEqual;\x07cceeds;\x07chThat;\x07perset;\x05bset;\x05pset;\x02b;\x02m;\x02p;",
		// &THORN; &THORN
		'TH' => "\x04ORN;\x03ORN",
		// &TRADE;
		'TR' => "\x04ADE;",
		// &TSHcy; &TScy;
		'TS' => "\x04Hcy;\x03cy;",
		// &Tab; &Tau;
		'Ta' => "\x02b;\x02u;",
		// &Tcaron; &Tcedil; &Tcy;
		'Tc' => "\x05aron;\x05edil;\x02y;",
		// &Tfr;
		'Tf' => "\x02r;",
		// &ThickSpace; &Therefore; &ThinSpace; &Theta;
		'Th' => "\x09ickSpace;\x08erefore;\x08inSpace;\x04eta;",
		// &TildeFullEqual; &TildeEqual; &TildeTilde; &Tilde;
		'Ti' => "\x0dldeFullEqual;\x09ldeEqual;\x09ldeTilde;\x04lde;",
		// &Topf;
		'To' => "\x03pf;",
		// &TripleDot;
		'Tr' => "\x08ipleDot;",
		// &Tstrok; &Tscr;
		'Ts' => "\x05trok;\x03cr;",
		// &Uarrocir; &Uacute; &Uacute &Uarr;
		'Ua' => "\x07rrocir;\x05cute;\x04cute\x03rr;",
		// &Ubreve; &Ubrcy;
		'Ub' => "\x05reve;\x04rcy;",
		// &Ucirc; &Ucirc &Ucy;
		'Uc' => "\x04irc;\x03irc\x02y;",
		// &Udblac;
		'Ud' => "\x05blac;",
		// &Ufr;
		'Uf' => "\x02r;",
		// &Ugrave; &Ugrave
		'Ug' => "\x05rave;\x04rave",
		// &Umacr;
		'Um' => "\x04acr;",
		// &UnderParenthesis; &UnderBracket; &UnderBrace; &UnionPlus; &UnderBar; &Union;
		'Un' => "\x0fderParenthesis;\x0bderBracket;\x09derBrace;\x08ionPlus;\x07derBar;\x04ion;",
		// &Uogon; &Uopf;
		'Uo' => "\x04gon;\x03pf;",
		// &UpArrowDownArrow; &UpperRightArrow; &UpperLeftArrow; &UpEquilibrium; &UpDownArrow; &Updownarrow; &UpArrowBar; &UpTeeArrow; &UpArrow; &Uparrow; &Upsilon; &UpTee; &Upsi;
		'Up' => "\x0fArrowDownArrow;\x0eperRightArrow;\x0dperLeftArrow;\x0cEquilibrium;\x0aDownArrow;\x0adownarrow;\x09ArrowBar;\x09TeeArrow;\x06Arrow;\x06arrow;\x06silon;\x04Tee;\x03si;",
		// &Uring;
		'Ur' => "\x04ing;",
		// &Uscr;
		'Us' => "\x03cr;",
		// &Utilde;
		'Ut' => "\x05ilde;",
		// &Uuml; &Uuml
		'Uu' => "\x03ml;\x02ml",
		// &VDash;
		'VD' => "\x04ash;",
		// &Vbar;
		'Vb' => "\x03ar;",
		// &Vcy;
		'Vc' => "\x02y;",
		// &Vdashl; &Vdash;
		'Vd' => "\x05ashl;\x04ash;",
		// &VerticalSeparator; &VerticalTilde; &VeryThinSpace; &VerticalLine; &VerticalBar; &Verbar; &Vert; &Vee;
		'Ve' => "\x10rticalSeparator;\x0crticalTilde;\x0cryThinSpace;\x0brticalLine;\x0articalBar;\x05rbar;\x03rt;\x02e;",
		// &Vfr;
		'Vf' => "\x02r;",
		// &Vopf;
		'Vo' => "\x03pf;",
		// &Vscr;
		'Vs' => "\x03cr;",
		// &Vvdash;
		'Vv' => "\x05dash;",
		// &Wcirc;
		'Wc' => "\x04irc;",
		// &Wedge;
		'We' => "\x04dge;",
		// &Wfr;
		'Wf' => "\x02r;",
		// &Wopf;
		'Wo' => "\x03pf;",
		// &Wscr;
		'Ws' => "\x03cr;",
		// &Xfr;
		'Xf' => "\x02r;",
		// &Xi;
		'Xi' => "\x01;",
		// &Xopf;
		'Xo' => "\x03pf;",
		// &Xscr;
		'Xs' => "\x03cr;",
		// &YAcy;
		'YA' => "\x03cy;",
		// &YIcy;
		'YI' => "\x03cy;",
		// &YUcy;
		'YU' => "\x03cy;",
		// &Yacute; &Yacute
		'Ya' => "\x05cute;\x04cute",
		// &Ycirc; &Ycy;
		'Yc' => "\x04irc;\x02y;",
		// &Yfr;
		'Yf' => "\x02r;",
		// &Yopf;
		'Yo' => "\x03pf;",
		// &Yscr;
		'Ys' => "\x03cr;",
		// &Yuml;
		'Yu' => "\x03ml;",
		// &ZHcy;
		'ZH' => "\x03cy;",
		// &Zacute;
		'Za' => "\x05cute;",
		// &Zcaron; &Zcy;
		'Zc' => "\x05aron;\x02y;",
		// &Zdot;
		'Zd' => "\x03ot;",
		// &ZeroWidthSpace; &Zeta;
		'Ze' => "\x0droWidthSpace;\x03ta;",
		// &Zfr;
		'Zf' => "\x02r;",
		// &Zopf;
		'Zo' => "\x03pf;",
		// &Zscr;
		'Zs' => "\x03cr;",
		// &aacute; &aacute
		'aa' => "\x05cute;\x04cute",
		// &abreve;
		'ab' => "\x05reve;",
		// &acirc; &acute; &acirc &acute &acE; &acd; &acy; &ac;
		'ac' => "\x04irc;\x04ute;\x03irc\x03ute\x02E;\x02d;\x02y;\x01;",
		// &aelig; &aelig
		'ae' => "\x04lig;\x03lig",
		// &afr; &af;
		'af' => "\x02r;\x01;",
		// &agrave; &agrave
		'ag' => "\x05rave;\x04rave",
		// &alefsym; &aleph; &alpha;
		'al' => "\x06efsym;\x04eph;\x04pha;",
		// &amacr; &amalg; &amp; &amp
		'am' => "\x04acr;\x04alg;\x02p;\x01p",
		// &andslope; &angmsdaa; &angmsdab; &angmsdac; &angmsdad; &angmsdae; &angmsdaf; &angmsdag; &angmsdah; &angrtvbd; &angrtvb; &angzarr; &andand; &angmsd; &angsph; &angle; &angrt; &angst; &andd; &andv; &ange; &and; &ang;
		'an' => "\x07dslope;\x07gmsdaa;\x07gmsdab;\x07gmsdac;\x07gmsdad;\x07gmsdae;\x07gmsdaf;\x07gmsdag;\x07gmsdah;\x07grtvbd;\x06grtvb;\x06gzarr;\x05dand;\x05gmsd;\x05gsph;\x04gle;\x04grt;\x04gst;\x03dd;\x03dv;\x03ge;\x02d;\x02g;",
		// &aogon; &aopf;
		'ao' => "\x04gon;\x03pf;",
		// &approxeq; &apacir; &approx; &apid; &apos; &apE; &ape; &ap;
		'ap' => "\x07proxeq;\x05acir;\x05prox;\x03id;\x03os;\x02E;\x02e;\x01;",
		// &aring; &aring
		'ar' => "\x04ing;\x03ing",
		// &asympeq; &asymp; &ascr; &ast;
		'as' => "\x06ympeq;\x04ymp;\x03cr;\x02t;",
		// &atilde; &atilde
		'at' => "\x05ilde;\x04ilde",
		// &auml; &auml
		'au' => "\x03ml;\x02ml",
		// &awconint; &awint;
		'aw' => "\x07conint;\x04int;",
		// &bNot;
		'bN' => "\x03ot;",
		// &backepsilon; &backprime; &backsimeq; &backcong; &barwedge; &backsim; &barvee; &barwed;
		'ba' => "\x0ackepsilon;\x08ckprime;\x08cksimeq;\x07ckcong;\x07rwedge;\x06cksim;\x05rvee;\x05rwed;",
		// &bbrktbrk; &bbrk;
		'bb' => "\x07rktbrk;\x03rk;",
		// &bcong; &bcy;
		'bc' => "\x04ong;\x02y;",
		// &bdquo;
		'bd' => "\x04quo;",
		// &because; &bemptyv; &between; &becaus; &bernou; &bepsi; &beta; &beth;
		'be' => "\x06cause;\x06mptyv;\x06tween;\x05caus;\x05rnou;\x04psi;\x03ta;\x03th;",
		// &bfr;
		'bf' => "\x02r;",
		// &bigtriangledown; &bigtriangleup; &bigotimes; &bigoplus; &bigsqcup; &biguplus; &bigwedge; &bigcirc; &bigodot; &bigstar; &bigcap; &bigcup; &bigvee;
		'bi' => "\x0egtriangledown;\x0cgtriangleup;\x08gotimes;\x07goplus;\x07gsqcup;\x07guplus;\x07gwedge;\x06gcirc;\x06godot;\x06gstar;\x05gcap;\x05gcup;\x05gvee;",
		// &bkarow;
		'bk' => "\x05arow;",
		// &blacktriangleright; &blacktriangledown; &blacktriangleleft; &blacktriangle; &blacklozenge; &blacksquare; &blank; &blk12; &blk14; &blk34; &block;
		'bl' => "\x11acktriangleright;\x10acktriangledown;\x10acktriangleleft;\x0cacktriangle;\x0backlozenge;\x0aacksquare;\x04ank;\x04k12;\x04k14;\x04k34;\x04ock;",
		// &bnequiv; &bnot; &bne;
		'bn' => "\x06equiv;\x03ot;\x02e;",
		// &boxminus; &boxtimes; &boxplus; &bottom; &bowtie; &boxbox; &boxDL; &boxDR; &boxDl; &boxDr; &boxHD; &boxHU; &boxHd; &boxHu; &boxUL; &boxUR; &boxUl; &boxUr; &boxVH; &boxVL; &boxVR; &boxVh; &boxVl; &boxVr; &boxdL; &boxdR; &boxdl; &boxdr; &boxhD; &boxhU; &boxhd; &boxhu; &boxuL; &boxuR; &boxul; &boxur; &boxvH; &boxvL; &boxvR; &boxvh; &boxvl; &boxvr; &bopf; &boxH; &boxV; &boxh; &boxv; &bot;
		'bo' => "\x07xminus;\x07xtimes;\x06xplus;\x05ttom;\x05wtie;\x05xbox;\x04xDL;\x04xDR;\x04xDl;\x04xDr;\x04xHD;\x04xHU;\x04xHd;\x04xHu;\x04xUL;\x04xUR;\x04xUl;\x04xUr;\x04xVH;\x04xVL;\x04xVR;\x04xVh;\x04xVl;\x04xVr;\x04xdL;\x04xdR;\x04xdl;\x04xdr;\x04xhD;\x04xhU;\x04xhd;\x04xhu;\x04xuL;\x04xuR;\x04xul;\x04xur;\x04xvH;\x04xvL;\x04xvR;\x04xvh;\x04xvl;\x04xvr;\x03pf;\x03xH;\x03xV;\x03xh;\x03xv;\x02t;",
		// &bprime;
		'bp' => "\x05rime;",
		// &brvbar; &breve; &brvbar
		'br' => "\x05vbar;\x04eve;\x04vbar",
		// &bsolhsub; &bsemi; &bsime; &bsolb; &bscr; &bsim; &bsol;
		'bs' => "\x07olhsub;\x04emi;\x04ime;\x04olb;\x03cr;\x03im;\x03ol;",
		// &bullet; &bumpeq; &bumpE; &bumpe; &bull; &bump;
		'bu' => "\x05llet;\x05mpeq;\x04mpE;\x04mpe;\x03ll;\x03mp;",
		// &capbrcup; &cacute; &capand; &capcap; &capcup; &capdot; &caret; &caron; &caps; &cap;
		'ca' => "\x07pbrcup;\x05cute;\x05pand;\x05pcap;\x05pcup;\x05pdot;\x04ret;\x04ron;\x03ps;\x02p;",
		// &ccupssm; &ccaron; &ccedil; &ccaps; &ccedil &ccirc; &ccups;
		'cc' => "\x06upssm;\x05aron;\x05edil;\x04aps;\x04edil\x04irc;\x04ups;",
		// &cdot;
		'cd' => "\x03ot;",
		// &centerdot; &cemptyv; &cedil; &cedil &cent; &cent
		'ce' => "\x08nterdot;\x06mptyv;\x04dil;\x03dil\x03nt;\x02nt",
		// &cfr;
		'cf' => "\x02r;",
		// &checkmark; &check; &chcy; &chi;
		'ch' => "\x08eckmark;\x04eck;\x03cy;\x02i;",
		// &circlearrowright; &circlearrowleft; &circledcirc; &circleddash; &circledast; &circledR; &circledS; &cirfnint; &cirscir; &circeq; &cirmid; &cirE; &circ; &cire; &cir;
		'ci' => "\x0frclearrowright;\x0erclearrowleft;\x0arcledcirc;\x0arcleddash;\x09rcledast;\x07rcledR;\x07rcledS;\x07rfnint;\x06rscir;\x05rceq;\x05rmid;\x03rE;\x03rc;\x03re;\x02r;",
		// &clubsuit; &clubs;
		'cl' => "\x07ubsuit;\x04ubs;",
		// &complement; &complexes; &coloneq; &congdot; &colone; &commat; &compfn; &conint; &coprod; &copysr; &colon; &comma; &comp; &cong; &copf; &copy; &copy
		'co' => "\x09mplement;\x08mplexes;\x06loneq;\x06ngdot;\x05lone;\x05mmat;\x05mpfn;\x05nint;\x05prod;\x05pysr;\x04lon;\x04mma;\x03mp;\x03ng;\x03pf;\x03py;\x02py",
		// &crarr; &cross;
		'cr' => "\x04arr;\x04oss;",
		// &csube; &csupe; &cscr; &csub; &csup;
		'cs' => "\x04ube;\x04upe;\x03cr;\x03ub;\x03up;",
		// &ctdot;
		'ct' => "\x04dot;",
		// &curvearrowright; &curvearrowleft; &curlyeqprec; &curlyeqsucc; &curlywedge; &cupbrcap; &curlyvee; &cudarrl; &cudarrr; &cularrp; &curarrm; &cularr; &cupcap; &cupcup; &cupdot; &curarr; &curren; &cuepr; &cuesc; &cupor; &curren &cuvee; &cuwed; &cups; &cup;
		'cu' => "\x0ervearrowright;\x0drvearrowleft;\x0arlyeqprec;\x0arlyeqsucc;\x09rlywedge;\x07pbrcap;\x07rlyvee;\x06darrl;\x06darrr;\x06larrp;\x06rarrm;\x05larr;\x05pcap;\x05pcup;\x05pdot;\x05rarr;\x05rren;\x04epr;\x04esc;\x04por;\x04rren\x04vee;\x04wed;\x03ps;\x02p;",
		// &cwconint; &cwint;
		'cw' => "\x07conint;\x04int;",
		// &cylcty;
		'cy' => "\x05lcty;",
		// &dArr;
		'dA' => "\x03rr;",
		// &dHar;
		'dH' => "\x03ar;",
		// &dagger; &daleth; &dashv; &darr; &dash;
		'da' => "\x05gger;\x05leth;\x04shv;\x03rr;\x03sh;",
		// &dbkarow; &dblac;
		'db' => "\x06karow;\x04lac;",
		// &dcaron; &dcy;
		'dc' => "\x05aron;\x02y;",
		// &ddagger; &ddotseq; &ddarr; &dd;
		'dd' => "\x06agger;\x06otseq;\x04arr;\x01;",
		// &demptyv; &delta; &deg; &deg
		'de' => "\x06mptyv;\x04lta;\x02g;\x01g",
		// &dfisht; &dfr;
		'df' => "\x05isht;\x02r;",
		// &dharl; &dharr;
		'dh' => "\x04arl;\x04arr;",
		// &divideontimes; &diamondsuit; &diamond; &digamma; &divide; &divonx; &diams; &disin; &divide &diam; &die; &div;
		'di' => "\x0cvideontimes;\x0aamondsuit;\x06amond;\x06gamma;\x05vide;\x05vonx;\x04ams;\x04sin;\x04vide\x03am;\x02e;\x02v;",
		// &djcy;
		'dj' => "\x03cy;",
		// &dlcorn; &dlcrop;
		'dl' => "\x05corn;\x05crop;",
		// &downharpoonright; &downharpoonleft; &doublebarwedge; &downdownarrows; &dotsquare; &downarrow; &doteqdot; &dotminus; &dotplus; &dollar; &doteq; &dopf; &dot;
		'do' => "\x0fwnharpoonright;\x0ewnharpoonleft;\x0dublebarwedge;\x0dwndownarrows;\x08tsquare;\x08wnarrow;\x07teqdot;\x07tminus;\x06tplus;\x05llar;\x04teq;\x03pf;\x02t;",
		// &drbkarow; &drcorn; &drcrop;
		'dr' => "\x07bkarow;\x05corn;\x05crop;",
		// &dstrok; &dscr; &dscy; &dsol;
		'ds' => "\x05trok;\x03cr;\x03cy;\x03ol;",
		// &dtdot; &dtrif; &dtri;
		'dt' => "\x04dot;\x04rif;\x03ri;",
		// &duarr; &duhar;
		'du' => "\x04arr;\x04har;",
		// &dwangle;
		'dw' => "\x06angle;",
		// &dzigrarr; &dzcy;
		'dz' => "\x07igrarr;\x03cy;",
		// &eDDot; &eDot;
		'eD' => "\x04Dot;\x03ot;",
		// &eacute; &easter; &eacute
		'ea' => "\x05cute;\x05ster;\x04cute",
		// &ecaron; &ecolon; &ecirc; &ecir; &ecirc &ecy;
		'ec' => "\x05aron;\x05olon;\x04irc;\x03ir;\x03irc\x02y;",
		// &edot;
		'ed' => "\x03ot;",
		// &ee;
		'ee' => "\x01;",
		// &efDot; &efr;
		'ef' => "\x04Dot;\x02r;",
		// &egrave; &egsdot; &egrave &egs; &eg;
		'eg' => "\x05rave;\x05sdot;\x04rave\x02s;\x01;",
		// &elinters; &elsdot; &ell; &els; &el;
		'el' => "\x07inters;\x05sdot;\x02l;\x02s;\x01;",
		// &emptyset; &emptyv; &emsp13; &emsp14; &emacr; &empty; &emsp;
		'em' => "\x07ptyset;\x05ptyv;\x05sp13;\x05sp14;\x04acr;\x04pty;\x03sp;",
		// &ensp; &eng;
		'en' => "\x03sp;\x02g;",
		// &eogon; &eopf;
		'eo' => "\x04gon;\x03pf;",
		// &epsilon; &eparsl; &eplus; &epsiv; &epar; &epsi;
		'ep' => "\x06silon;\x05arsl;\x04lus;\x04siv;\x03ar;\x03si;",
		// &eqslantless; &eqslantgtr; &eqvparsl; &eqcolon; &equivDD; &eqcirc; &equals; &equest; &eqsim; &equiv;
		'eq' => "\x0aslantless;\x09slantgtr;\x07vparsl;\x06colon;\x06uivDD;\x05circ;\x05uals;\x05uest;\x04sim;\x04uiv;",
		// &erDot; &erarr;
		'er' => "\x04Dot;\x04arr;",
		// &esdot; &escr; &esim;
		'es' => "\x04dot;\x03cr;\x03im;",
		// &eta; &eth; &eth
		'et' => "\x02a;\x02h;\x01h",
		// &euml; &euro; &euml
		'eu' => "\x03ml;\x03ro;\x02ml",
		// &exponentiale; &expectation; &exist; &excl;
		'ex' => "\x0bponentiale;\x0apectation;\x04ist;\x03cl;",
		// &fallingdotseq;
		'fa' => "\x0cllingdotseq;",
		// &fcy;
		'fc' => "\x02y;",
		// &female;
		'fe' => "\x05male;",
		// &ffilig; &ffllig; &fflig; &ffr;
		'ff' => "\x05ilig;\x05llig;\x04lig;\x02r;",
		// &filig;
		'fi' => "\x04lig;",
		// &fjlig;
		'fj' => "\x04lig;",
		// &fllig; &fltns; &flat;
		'fl' => "\x04lig;\x04tns;\x03at;",
		// &fnof;
		'fn' => "\x03of;",
		// &forall; &forkv; &fopf; &fork;
		'fo' => "\x05rall;\x04rkv;\x03pf;\x03rk;",
		// &fpartint;
		'fp' => "\x07artint;",
		// &frac12; &frac13; &frac14; &frac15; &frac16; &frac18; &frac23; &frac25; &frac34; &frac35; &frac38; &frac45; &frac56; &frac58; &frac78; &frac12 &frac14 &frac34 &frasl; &frown;
		'fr' => "\x05ac12;\x05ac13;\x05ac14;\x05ac15;\x05ac16;\x05ac18;\x05ac23;\x05ac25;\x05ac34;\x05ac35;\x05ac38;\x05ac45;\x05ac56;\x05ac58;\x05ac78;\x04ac12\x04ac14\x04ac34\x04asl;\x04own;",
		// &fscr;
		'fs' => "\x03cr;",
		// &gEl; &gE;
		'gE' => "\x02l;\x01;",
		// &gacute; &gammad; &gamma; &gap;
		'ga' => "\x05cute;\x05mmad;\x04mma;\x02p;",
		// &gbreve;
		'gb' => "\x05reve;",
		// &gcirc; &gcy;
		'gc' => "\x04irc;\x02y;",
		// &gdot;
		'gd' => "\x03ot;",
		// &geqslant; &gesdotol; &gesdoto; &gesdot; &gesles; &gescc; &geqq; &gesl; &gel; &geq; &ges; &ge;
		'ge' => "\x07qslant;\x07sdotol;\x06sdoto;\x05sdot;\x05sles;\x04scc;\x03qq;\x03sl;\x02l;\x02q;\x02s;\x01;",
		// &gfr;
		'gf' => "\x02r;",
		// &ggg; &gg;
		'gg' => "\x02g;\x01;",
		// &gimel;
		'gi' => "\x04mel;",
		// &gjcy;
		'gj' => "\x03cy;",
		// &glE; &gla; &glj; &gl;
		'gl' => "\x02E;\x02a;\x02j;\x01;",
		// &gnapprox; &gneqq; &gnsim; &gnap; &gneq; &gnE; &gne;
		'gn' => "\x07approx;\x04eqq;\x04sim;\x03ap;\x03eq;\x02E;\x02e;",
		// &gopf;
		'go' => "\x03pf;",
		// &grave;
		'gr' => "\x04ave;",
		// &gsime; &gsiml; &gscr; &gsim;
		'gs' => "\x04ime;\x04iml;\x03cr;\x03im;",
		// &gtreqqless; &gtrapprox; &gtreqless; &gtquest; &gtrless; &gtlPar; &gtrarr; &gtrdot; &gtrsim; &gtcir; &gtdot; &gtcc; &gt;
		'gt' => "\x09reqqless;\x08rapprox;\x08reqless;\x06quest;\x06rless;\x05lPar;\x05rarr;\x05rdot;\x05rsim;\x04cir;\x04dot;\x03cc;\x01;",
		// &gvertneqq; &gvnE;
		'gv' => "\x08ertneqq;\x03nE;",
		// &hArr;
		'hA' => "\x03rr;",
		// &harrcir; &hairsp; &hamilt; &hardcy; &harrw; &half; &harr;
		'ha' => "\x06rrcir;\x05irsp;\x05milt;\x05rdcy;\x04rrw;\x03lf;\x03rr;",
		// &hbar;
		'hb' => "\x03ar;",
		// &hcirc;
		'hc' => "\x04irc;",
		// &heartsuit; &hearts; &hellip; &hercon;
		'he' => "\x08artsuit;\x05arts;\x05llip;\x05rcon;",
		// &hfr;
		'hf' => "\x02r;",
		// &hksearow; &hkswarow;
		'hk' => "\x07searow;\x07swarow;",
		// &hookrightarrow; &hookleftarrow; &homtht; &horbar; &hoarr; &hopf;
		'ho' => "\x0dokrightarrow;\x0cokleftarrow;\x05mtht;\x05rbar;\x04arr;\x03pf;",
		// &hslash; &hstrok; &hscr;
		'hs' => "\x05lash;\x05trok;\x03cr;",
		// &hybull; &hyphen;
		'hy' => "\x05bull;\x05phen;",
		// &iacute; &iacute
		'ia' => "\x05cute;\x04cute",
		// &icirc; &icirc &icy; &ic;
		'ic' => "\x04irc;\x03irc\x02y;\x01;",
		// &iexcl; &iecy; &iexcl
		'ie' => "\x04xcl;\x03cy;\x03xcl",
		// &iff; &ifr;
		'if' => "\x02f;\x02r;",
		// &igrave; &igrave
		'ig' => "\x05rave;\x04rave",
		// &iiiint; &iinfin; &iiint; &iiota; &ii;
		'ii' => "\x05iint;\x05nfin;\x04int;\x04ota;\x01;",
		// &ijlig;
		'ij' => "\x04lig;",
		// &imagline; &imagpart; &imacr; &image; &imath; &imped; &imof;
		'im' => "\x07agline;\x07agpart;\x04acr;\x04age;\x04ath;\x04ped;\x03of;",
		// &infintie; &integers; &intercal; &intlarhk; &intprod; &incare; &inodot; &intcal; &infin; &int; &in;
		'in' => "\x07fintie;\x07tegers;\x07tercal;\x07tlarhk;\x06tprod;\x05care;\x05odot;\x05tcal;\x04fin;\x02t;\x01;",
		// &iogon; &iocy; &iopf; &iota;
		'io' => "\x04gon;\x03cy;\x03pf;\x03ta;",
		// &iprod;
		'ip' => "\x04rod;",
		// &iquest; &iquest
		'iq' => "\x05uest;\x04uest",
		// &isindot; &isinsv; &isinE; &isins; &isinv; &iscr; &isin;
		'is' => "\x06indot;\x05insv;\x04inE;\x04ins;\x04inv;\x03cr;\x03in;",
		// &itilde; &it;
		'it' => "\x05ilde;\x01;",
		// &iukcy; &iuml; &iuml
		'iu' => "\x04kcy;\x03ml;\x02ml",
		// &jcirc; &jcy;
		'jc' => "\x04irc;\x02y;",
		// &jfr;
		'jf' => "\x02r;",
		// &jmath;
		'jm' => "\x04ath;",
		// &jopf;
		'jo' => "\x03pf;",
		// &jsercy; &jscr;
		'js' => "\x05ercy;\x03cr;",
		// &jukcy;
		'ju' => "\x04kcy;",
		// &kappav; &kappa;
		'ka' => "\x05ppav;\x04ppa;",
		// &kcedil; &kcy;
		'kc' => "\x05edil;\x02y;",
		// &kfr;
		'kf' => "\x02r;",
		// &kgreen;
		'kg' => "\x05reen;",
		// &khcy;
		'kh' => "\x03cy;",
		// &kjcy;
		'kj' => "\x03cy;",
		// &kopf;
		'ko' => "\x03pf;",
		// &kscr;
		'ks' => "\x03cr;",
		// &lAtail; &lAarr; &lArr;
		'lA' => "\x05tail;\x04arr;\x03rr;",
		// &lBarr;
		'lB' => "\x04arr;",
		// &lEg; &lE;
		'lE' => "\x02g;\x01;",
		// &lHar;
		'lH' => "\x03ar;",
		// &laemptyv; &larrbfs; &larrsim; &lacute; &lagran; &lambda; &langle; &larrfs; &larrhk; &larrlp; &larrpl; &larrtl; &latail; &langd; &laquo; &larrb; &lates; &lang; &laquo &larr; &late; &lap; &lat;
		'la' => "\x07emptyv;\x06rrbfs;\x06rrsim;\x05cute;\x05gran;\x05mbda;\x05ngle;\x05rrfs;\x05rrhk;\x05rrlp;\x05rrpl;\x05rrtl;\x05tail;\x04ngd;\x04quo;\x04rrb;\x04tes;\x03ng;\x03quo\x03rr;\x03te;\x02p;\x02t;",
		// &lbrksld; &lbrkslu; &lbrace; &lbrack; &lbarr; &lbbrk; &lbrke;
		'lb' => "\x06rksld;\x06rkslu;\x05race;\x05rack;\x04arr;\x04brk;\x04rke;",
		// &lcaron; &lcedil; &lceil; &lcub; &lcy;
		'lc' => "\x05aron;\x05edil;\x04eil;\x03ub;\x02y;",
		// &ldrushar; &ldrdhar; &ldquor; &ldquo; &ldca; &ldsh;
		'ld' => "\x07rushar;\x06rdhar;\x05quor;\x04quo;\x03ca;\x03sh;",
		// &leftrightsquigarrow; &leftrightharpoons; &leftharpoondown; &leftrightarrows; &leftleftarrows; &leftrightarrow; &leftthreetimes; &leftarrowtail; &leftharpoonup; &lessapprox; &lesseqqgtr; &leftarrow; &lesseqgtr; &leqslant; &lesdotor; &lesdoto; &lessdot; &lessgtr; &lesssim; &lesdot; &lesges; &lescc; &leqq; &lesg; &leg; &leq; &les; &le;
		'le' => "\x12ftrightsquigarrow;\x10ftrightharpoons;\x0eftharpoondown;\x0eftrightarrows;\x0dftleftarrows;\x0dftrightarrow;\x0dftthreetimes;\x0cftarrowtail;\x0cftharpoonup;\x09ssapprox;\x09sseqqgtr;\x08ftarrow;\x08sseqgtr;\x07qslant;\x07sdotor;\x06sdoto;\x06ssdot;\x06ssgtr;\x06sssim;\x05sdot;\x05sges;\x04scc;\x03qq;\x03sg;\x02g;\x02q;\x02s;\x01;",
		// &lfisht; &lfloor; &lfr;
		'lf' => "\x05isht;\x05loor;\x02r;",
		// &lgE; &lg;
		'lg' => "\x02E;\x01;",
		// &lharul; &lhard; &lharu; &lhblk;
		'lh' => "\x05arul;\x04ard;\x04aru;\x04blk;",
		// &ljcy;
		'lj' => "\x03cy;",
		// &llcorner; &llhard; &llarr; &lltri; &ll;
		'll' => "\x07corner;\x05hard;\x04arr;\x04tri;\x01;",
		// &lmoustache; &lmidot; &lmoust;
		'lm' => "\x09oustache;\x05idot;\x05oust;",
		// &lnapprox; &lneqq; &lnsim; &lnap; &lneq; &lnE; &lne;
		'ln' => "\x07approx;\x04eqq;\x04sim;\x03ap;\x03eq;\x02E;\x02e;",
		// &longleftrightarrow; &longrightarrow; &looparrowright; &longleftarrow; &looparrowleft; &longmapsto; &lotimes; &lozenge; &loplus; &lowast; &lowbar; &loang; &loarr; &lobrk; &lopar; &lopf; &lozf; &loz;
		'lo' => "\x11ngleftrightarrow;\x0dngrightarrow;\x0doparrowright;\x0cngleftarrow;\x0coparrowleft;\x09ngmapsto;\x06times;\x06zenge;\x05plus;\x05wast;\x05wbar;\x04ang;\x04arr;\x04brk;\x04par;\x03pf;\x03zf;\x02z;",
		// &lparlt; &lpar;
		'lp' => "\x05arlt;\x03ar;",
		// &lrcorner; &lrhard; &lrarr; &lrhar; &lrtri; &lrm;
		'lr' => "\x07corner;\x05hard;\x04arr;\x04har;\x04tri;\x02m;",
		// &lsaquo; &lsquor; &lstrok; &lsime; &lsimg; &lsquo; &lscr; &lsim; &lsqb; &lsh;
		'ls' => "\x05aquo;\x05quor;\x05trok;\x04ime;\x04img;\x04quo;\x03cr;\x03im;\x03qb;\x02h;",
		// &ltquest; &lthree; &ltimes; &ltlarr; &ltrPar; &ltcir; &ltdot; &ltrie; &ltrif; &ltcc; &ltri; &lt;
		'lt' => "\x06quest;\x05hree;\x05imes;\x05larr;\x05rPar;\x04cir;\x04dot;\x04rie;\x04rif;\x03cc;\x03ri;\x01;",
		// &lurdshar; &luruhar;
		'lu' => "\x07rdshar;\x06ruhar;",
		// &lvertneqq; &lvnE;
		'lv' => "\x08ertneqq;\x03nE;",
		// &mDDot;
		'mD' => "\x04Dot;",
		// &mapstodown; &mapstoleft; &mapstoup; &maltese; &mapsto; &marker; &macr; &male; &malt; &macr &map;
		'ma' => "\x09pstodown;\x09pstoleft;\x07pstoup;\x06ltese;\x05psto;\x05rker;\x03cr;\x03le;\x03lt;\x02cr\x02p;",
		// &mcomma; &mcy;
		'mc' => "\x05omma;\x02y;",
		// &mdash;
		'md' => "\x04ash;",
		// &measuredangle;
		'me' => "\x0casuredangle;",
		// &mfr;
		'mf' => "\x02r;",
		// &mho;
		'mh' => "\x02o;",
		// &minusdu; &midast; &midcir; &middot; &minusb; &minusd; &micro; &middot &minus; &micro &mid;
		'mi' => "\x06nusdu;\x05dast;\x05dcir;\x05ddot;\x05nusb;\x05nusd;\x04cro;\x04ddot\x04nus;\x03cro\x02d;",
		// &mlcp; &mldr;
		'ml' => "\x03cp;\x03dr;",
		// &mnplus;
		'mn' => "\x05plus;",
		// &models; &mopf;
		'mo' => "\x05dels;\x03pf;",
		// &mp;
		'mp' => "\x01;",
		// &mstpos; &mscr;
		'ms' => "\x05tpos;\x03cr;",
		// &multimap; &mumap; &mu;
		'mu' => "\x07ltimap;\x04map;\x01;",
		// &nGtv; &nGg; &nGt;
		'nG' => "\x03tv;\x02g;\x02t;",
		// &nLeftrightarrow; &nLeftarrow; &nLtv; &nLl; &nLt;
		'nL' => "\x0eeftrightarrow;\x09eftarrow;\x03tv;\x02l;\x02t;",
		// &nRightarrow;
		'nR' => "\x0aightarrow;",
		// &nVDash; &nVdash;
		'nV' => "\x05Dash;\x05dash;",
		// &naturals; &napprox; &natural; &nacute; &nabla; &napid; &napos; &natur; &nang; &napE; &nap;
		'na' => "\x07turals;\x06pprox;\x06tural;\x05cute;\x04bla;\x04pid;\x04pos;\x04tur;\x03ng;\x03pE;\x02p;",
		// &nbumpe; &nbump; &nbsp; &nbsp
		'nb' => "\x05umpe;\x04ump;\x03sp;\x02sp",
		// &ncongdot; &ncaron; &ncedil; &ncong; &ncap; &ncup; &ncy;
		'nc' => "\x07ongdot;\x05aron;\x05edil;\x04ong;\x03ap;\x03up;\x02y;",
		// &ndash;
		'nd' => "\x04ash;",
		// &nearrow; &nexists; &nearhk; &nequiv; &nesear; &nexist; &neArr; &nearr; &nedot; &nesim; &ne;
		'ne' => "\x06arrow;\x06xists;\x05arhk;\x05quiv;\x05sear;\x05xist;\x04Arr;\x04arr;\x04dot;\x04sim;\x01;",
		// &nfr;
		'nf' => "\x02r;",
		// &ngeqslant; &ngeqq; &ngsim; &ngeq; &nges; &ngtr; &ngE; &nge; &ngt;
		'ng' => "\x08eqslant;\x04eqq;\x04sim;\x03eq;\x03es;\x03tr;\x02E;\x02e;\x02t;",
		// &nhArr; &nharr; &nhpar;
		'nh' => "\x04Arr;\x04arr;\x04par;",
		// &nisd; &nis; &niv; &ni;
		'ni' => "\x03sd;\x02s;\x02v;\x01;",
		// &njcy;
		'nj' => "\x03cy;",
		// &nleftrightarrow; &nleftarrow; &nleqslant; &nltrie; &nlArr; &nlarr; &nleqq; &nless; &nlsim; &nltri; &nldr; &nleq; &nles; &nlE; &nle; &nlt;
		'nl' => "\x0eeftrightarrow;\x09eftarrow;\x08eqslant;\x05trie;\x04Arr;\x04arr;\x04eqq;\x04ess;\x04sim;\x04tri;\x03dr;\x03eq;\x03es;\x02E;\x02e;\x02t;",
		// &nmid;
		'nm' => "\x03id;",
		// &notindot; &notinva; &notinvb; &notinvc; &notniva; &notnivb; &notnivc; &notinE; &notin; &notni; &nopf; &not; &not
		'no' => "\x07tindot;\x06tinva;\x06tinvb;\x06tinvc;\x06tniva;\x06tnivb;\x06tnivc;\x05tinE;\x04tin;\x04tni;\x03pf;\x02t;\x01t",
		// &nparallel; &npolint; &npreceq; &nparsl; &nprcue; &npart; &nprec; &npar; &npre; &npr;
		'np' => "\x08arallel;\x06olint;\x06receq;\x05arsl;\x05rcue;\x04art;\x04rec;\x03ar;\x03re;\x02r;",
		// &nrightarrow; &nrarrc; &nrarrw; &nrtrie; &nrArr; &nrarr; &nrtri;
		'nr' => "\x0aightarrow;\x05arrc;\x05arrw;\x05trie;\x04Arr;\x04arr;\x04tri;",
		// &nshortparallel; &nsubseteqq; &nsupseteqq; &nshortmid; &nsubseteq; &nsupseteq; &nsqsube; &nsqsupe; &nsubset; &nsucceq; &nsupset; &nsccue; &nsimeq; &nsime; &nsmid; &nspar; &nsubE; &nsube; &nsucc; &nsupE; &nsupe; &nsce; &nscr; &nsim; &nsub; &nsup; &nsc;
		'ns' => "\x0dhortparallel;\x09ubseteqq;\x09upseteqq;\x08hortmid;\x08ubseteq;\x08upseteq;\x06qsube;\x06qsupe;\x06ubset;\x06ucceq;\x06upset;\x05ccue;\x05imeq;\x04ime;\x04mid;\x04par;\x04ubE;\x04ube;\x04ucc;\x04upE;\x04upe;\x03ce;\x03cr;\x03im;\x03ub;\x03up;\x02c;",
		// &ntrianglerighteq; &ntrianglelefteq; &ntriangleright; &ntriangleleft; &ntilde; &ntilde &ntgl; &ntlg;
		'nt' => "\x0frianglerighteq;\x0erianglelefteq;\x0driangleright;\x0criangleleft;\x05ilde;\x04ilde\x03gl;\x03lg;",
		// &numero; &numsp; &num; &nu;
		'nu' => "\x05mero;\x04msp;\x02m;\x01;",
		// &nvinfin; &nvltrie; &nvrtrie; &nvDash; &nvHarr; &nvdash; &nvlArr; &nvrArr; &nvsim; &nvap; &nvge; &nvgt; &nvle; &nvlt;
		'nv' => "\x06infin;\x06ltrie;\x06rtrie;\x05Dash;\x05Harr;\x05dash;\x05lArr;\x05rArr;\x04sim;\x03ap;\x03ge;\x03gt;\x03le;\x03lt;",
		// &nwarrow; &nwarhk; &nwnear; &nwArr; &nwarr;
		'nw' => "\x06arrow;\x05arhk;\x05near;\x04Arr;\x04arr;",
		// &oS;
		'oS' => "\x01;",
		// &oacute; &oacute &oast;
		'oa' => "\x05cute;\x04cute\x03st;",
		// &ocirc; &ocir; &ocirc &ocy;
		'oc' => "\x04irc;\x03ir;\x03irc\x02y;",
		// &odblac; &odsold; &odash; &odiv; &odot;
		'od' => "\x05blac;\x05sold;\x04ash;\x03iv;\x03ot;",
		// &oelig;
		'oe' => "\x04lig;",
		// &ofcir; &ofr;
		'of' => "\x04cir;\x02r;",
		// &ograve; &ograve &ogon; &ogt;
		'og' => "\x05rave;\x04rave\x03on;\x02t;",
		// &ohbar; &ohm;
		'oh' => "\x04bar;\x02m;",
		// &oint;
		'oi' => "\x03nt;",
		// &olcross; &olarr; &olcir; &oline; &olt;
		'ol' => "\x06cross;\x04arr;\x04cir;\x04ine;\x02t;",
		// &omicron; &ominus; &omacr; &omega; &omid;
		'om' => "\x06icron;\x05inus;\x04acr;\x04ega;\x03id;",
		// &oopf;
		'oo' => "\x03pf;",
		// &operp; &oplus; &opar;
		'op' => "\x04erp;\x04lus;\x03ar;",
		// &orderof; &orslope; &origof; &orarr; &order; &ordf; &ordm; &oror; &ord; &ordf &ordm &orv; &or;
		'or' => "\x06derof;\x06slope;\x05igof;\x04arr;\x04der;\x03df;\x03dm;\x03or;\x02d;\x02df\x02dm\x02v;\x01;",
		// &oslash; &oslash &oscr; &osol;
		'os' => "\x05lash;\x04lash\x03cr;\x03ol;",
		// &otimesas; &otilde; &otimes; &otilde
		'ot' => "\x07imesas;\x05ilde;\x05imes;\x04ilde",
		// &ouml; &ouml
		'ou' => "\x03ml;\x02ml",
		// &ovbar;
		'ov' => "\x04bar;",
		// &parallel; &parsim; &parsl; &para; &part; &par; &para
		'pa' => "\x07rallel;\x05rsim;\x04rsl;\x03ra;\x03rt;\x02r;\x02ra",
		// &pcy;
		'pc' => "\x02y;",
		// &pertenk; &percnt; &period; &permil; &perp;
		'pe' => "\x06rtenk;\x05rcnt;\x05riod;\x05rmil;\x03rp;",
		// &pfr;
		'pf' => "\x02r;",
		// &phmmat; &phone; &phiv; &phi;
		'ph' => "\x05mmat;\x04one;\x03iv;\x02i;",
		// &pitchfork; &piv; &pi;
		'pi' => "\x08tchfork;\x02v;\x01;",
		// &plusacir; &planckh; &pluscir; &plussim; &plustwo; &planck; &plankv; &plusdo; &plusdu; &plusmn; &plusb; &pluse; &plusmn &plus;
		'pl' => "\x07usacir;\x06anckh;\x06uscir;\x06ussim;\x06ustwo;\x05anck;\x05ankv;\x05usdo;\x05usdu;\x05usmn;\x04usb;\x04use;\x04usmn\x03us;",
		// &pm;
		'pm' => "\x01;",
		// &pointint; &pound; &popf; &pound
		'po' => "\x07intint;\x04und;\x03pf;\x03und",
		// &preccurlyeq; &precnapprox; &precapprox; &precneqq; &precnsim; &profalar; &profline; &profsurf; &precsim; &preceq; &primes; &prnsim; &propto; &prurel; &prcue; &prime; &prnap; &prsim; &prap; &prec; &prnE; &prod; &prop; &prE; &pre; &pr;
		'pr' => "\x0aeccurlyeq;\x0aecnapprox;\x09ecapprox;\x07ecneqq;\x07ecnsim;\x07ofalar;\x07ofline;\x07ofsurf;\x06ecsim;\x05eceq;\x05imes;\x05nsim;\x05opto;\x05urel;\x04cue;\x04ime;\x04nap;\x04sim;\x03ap;\x03ec;\x03nE;\x03od;\x03op;\x02E;\x02e;\x01;",
		// &pscr; &psi;
		'ps' => "\x03cr;\x02i;",
		// &puncsp;
		'pu' => "\x05ncsp;",
		// &qfr;
		'qf' => "\x02r;",
		// &qint;
		'qi' => "\x03nt;",
		// &qopf;
		'qo' => "\x03pf;",
		// &qprime;
		'qp' => "\x05rime;",
		// &qscr;
		'qs' => "\x03cr;",
		// &quaternions; &quatint; &questeq; &quest; &quot; &quot
		'qu' => "\x0aaternions;\x06atint;\x06esteq;\x04est;\x03ot;\x02ot",
		// &rAtail; &rAarr; &rArr;
		'rA' => "\x05tail;\x04arr;\x03rr;",
		// &rBarr;
		'rB' => "\x04arr;",
		// &rHar;
		'rH' => "\x03ar;",
		// &rationals; &raemptyv; &rarrbfs; &rarrsim; &racute; &rangle; &rarrap; &rarrfs; &rarrhk; &rarrlp; &rarrpl; &rarrtl; &ratail; &radic; &rangd; &range; &raquo; &rarrb; &rarrc; &rarrw; &ratio; &race; &rang; &raquo &rarr;
		'ra' => "\x08tionals;\x07emptyv;\x06rrbfs;\x06rrsim;\x05cute;\x05ngle;\x05rrap;\x05rrfs;\x05rrhk;\x05rrlp;\x05rrpl;\x05rrtl;\x05tail;\x04dic;\x04ngd;\x04nge;\x04quo;\x04rrb;\x04rrc;\x04rrw;\x04tio;\x03ce;\x03ng;\x03quo\x03rr;",
		// &rbrksld; &rbrkslu; &rbrace; &rbrack; &rbarr; &rbbrk; &rbrke;
		'rb' => "\x06rksld;\x06rkslu;\x05race;\x05rack;\x04arr;\x04brk;\x04rke;",
		// &rcaron; &rcedil; &rceil; &rcub; &rcy;
		'rc' => "\x05aron;\x05edil;\x04eil;\x03ub;\x02y;",
		// &rdldhar; &rdquor; &rdquo; &rdca; &rdsh;
		'rd' => "\x06ldhar;\x05quor;\x04quo;\x03ca;\x03sh;",
		// &realpart; &realine; &reals; &real; &rect; &reg; &reg
		're' => "\x07alpart;\x06aline;\x04als;\x03al;\x03ct;\x02g;\x01g",
		// &rfisht; &rfloor; &rfr;
		'rf' => "\x05isht;\x05loor;\x02r;",
		// &rharul; &rhard; &rharu; &rhov; &rho;
		'rh' => "\x05arul;\x04ard;\x04aru;\x03ov;\x02o;",
		// &rightleftharpoons; &rightharpoondown; &rightrightarrows; &rightleftarrows; &rightsquigarrow; &rightthreetimes; &rightarrowtail; &rightharpoonup; &risingdotseq; &rightarrow; &ring;
		'ri' => "\x10ghtleftharpoons;\x0fghtharpoondown;\x0fghtrightarrows;\x0eghtleftarrows;\x0eghtsquigarrow;\x0eghtthreetimes;\x0dghtarrowtail;\x0dghtharpoonup;\x0bsingdotseq;\x09ghtarrow;\x03ng;",
		// &rlarr; &rlhar; &rlm;
		'rl' => "\x04arr;\x04har;\x02m;",
		// &rmoustache; &rmoust;
		'rm' => "\x09oustache;\x05oust;",
		// &rnmid;
		'rn' => "\x04mid;",
		// &rotimes; &roplus; &roang; &roarr; &robrk; &ropar; &ropf;
		'ro' => "\x06times;\x05plus;\x04ang;\x04arr;\x04brk;\x04par;\x03pf;",
		// &rppolint; &rpargt; &rpar;
		'rp' => "\x07polint;\x05argt;\x03ar;",
		// &rrarr;
		'rr' => "\x04arr;",
		// &rsaquo; &rsquor; &rsquo; &rscr; &rsqb; &rsh;
		'rs' => "\x05aquo;\x05quor;\x04quo;\x03cr;\x03qb;\x02h;",
		// &rtriltri; &rthree; &rtimes; &rtrie; &rtrif; &rtri;
		'rt' => "\x07riltri;\x05hree;\x05imes;\x04rie;\x04rif;\x03ri;",
		// &ruluhar;
		'ru' => "\x06luhar;",
		// &rx;
		'rx' => "\x01;",
		// &sacute;
		'sa' => "\x05cute;",
		// &sbquo;
		'sb' => "\x04quo;",
		// &scpolint; &scaron; &scedil; &scnsim; &sccue; &scirc; &scnap; &scsim; &scap; &scnE; &scE; &sce; &scy; &sc;
		'sc' => "\x07polint;\x05aron;\x05edil;\x05nsim;\x04cue;\x04irc;\x04nap;\x04sim;\x03ap;\x03nE;\x02E;\x02e;\x02y;\x01;",
		// &sdotb; &sdote; &sdot;
		'sd' => "\x04otb;\x04ote;\x03ot;",
		// &setminus; &searrow; &searhk; &seswar; &seArr; &searr; &setmn; &sect; &semi; &sext; &sect
		'se' => "\x07tminus;\x06arrow;\x05arhk;\x05swar;\x04Arr;\x04arr;\x04tmn;\x03ct;\x03mi;\x03xt;\x02ct",
		// &sfrown; &sfr;
		'sf' => "\x05rown;\x02r;",
		// &shortparallel; &shortmid; &shchcy; &sharp; &shcy; &shy; &shy
		'sh' => "\x0cortparallel;\x07ortmid;\x05chcy;\x04arp;\x03cy;\x02y;\x01y",
		// &simplus; &simrarr; &sigmaf; &sigmav; &simdot; &sigma; &simeq; &simgE; &simlE; &simne; &sime; &simg; &siml; &sim;
		'si' => "\x06mplus;\x06mrarr;\x05gmaf;\x05gmav;\x05mdot;\x04gma;\x04meq;\x04mgE;\x04mlE;\x04mne;\x03me;\x03mg;\x03ml;\x02m;",
		// &slarr;
		'sl' => "\x04arr;",
		// &smallsetminus; &smeparsl; &smashp; &smile; &smtes; &smid; &smte; &smt;
		'sm' => "\x0callsetminus;\x07eparsl;\x05ashp;\x04ile;\x04tes;\x03id;\x03te;\x02t;",
		// &softcy; &solbar; &solb; &sopf; &sol;
		'so' => "\x05ftcy;\x05lbar;\x03lb;\x03pf;\x02l;",
		// &spadesuit; &spades; &spar;
		'sp' => "\x08adesuit;\x05ades;\x03ar;",
		// &sqsubseteq; &sqsupseteq; &sqsubset; &sqsupset; &sqcaps; &sqcups; &sqsube; &sqsupe; &square; &squarf; &sqcap; &sqcup; &sqsub; &sqsup; &squf; &squ;
		'sq' => "\x09subseteq;\x09supseteq;\x07subset;\x07supset;\x05caps;\x05cups;\x05sube;\x05supe;\x05uare;\x05uarf;\x04cap;\x04cup;\x04sub;\x04sup;\x03uf;\x02u;",
		// &srarr;
		'sr' => "\x04arr;",
		// &ssetmn; &ssmile; &sstarf; &sscr;
		'ss' => "\x05etmn;\x05mile;\x05tarf;\x03cr;",
		// &straightepsilon; &straightphi; &starf; &strns; &star;
		'st' => "\x0eraightepsilon;\x0araightphi;\x04arf;\x04rns;\x03ar;",
		// &succcurlyeq; &succnapprox; &subsetneqq; &succapprox; &supsetneqq; &subseteqq; &subsetneq; &supseteqq; &supsetneq; &subseteq; &succneqq; &succnsim; &supseteq; &subedot; &submult; &subplus; &subrarr; &succsim; &supdsub; &supedot; &suphsol; &suphsub; &suplarr; &supmult; &supplus; &subdot; &subset; &subsim; &subsub; &subsup; &succeq; &supdot; &supset; &supsim; &supsub; &supsup; &subnE; &subne; &supnE; &supne; &subE; &sube; &succ; &sung; &sup1; &sup2; &sup3; &supE; &supe; &sub; &sum; &sup1 &sup2 &sup3 &sup;
		'su' => "\x0acccurlyeq;\x0accnapprox;\x09bsetneqq;\x09ccapprox;\x09psetneqq;\x08bseteqq;\x08bsetneq;\x08pseteqq;\x08psetneq;\x07bseteq;\x07ccneqq;\x07ccnsim;\x07pseteq;\x06bedot;\x06bmult;\x06bplus;\x06brarr;\x06ccsim;\x06pdsub;\x06pedot;\x06phsol;\x06phsub;\x06plarr;\x06pmult;\x06pplus;\x05bdot;\x05bset;\x05bsim;\x05bsub;\x05bsup;\x05cceq;\x05pdot;\x05pset;\x05psim;\x05psub;\x05psup;\x04bnE;\x04bne;\x04pnE;\x04pne;\x03bE;\x03be;\x03cc;\x03ng;\x03p1;\x03p2;\x03p3;\x03pE;\x03pe;\x02b;\x02m;\x02p1\x02p2\x02p3\x02p;",
		// &swarrow; &swarhk; &swnwar; &swArr; &swarr;
		'sw' => "\x06arrow;\x05arhk;\x05nwar;\x04Arr;\x04arr;",
		// &szlig; &szlig
		'sz' => "\x04lig;\x03lig",
		// &target; &tau;
		'ta' => "\x05rget;\x02u;",
		// &tbrk;
		'tb' => "\x03rk;",
		// &tcaron; &tcedil; &tcy;
		'tc' => "\x05aron;\x05edil;\x02y;",
		// &tdot;
		'td' => "\x03ot;",
		// &telrec;
		'te' => "\x05lrec;",
		// &tfr;
		'tf' => "\x02r;",
		// &thickapprox; &therefore; &thetasym; &thicksim; &there4; &thetav; &thinsp; &thksim; &theta; &thkap; &thorn; &thorn
		'th' => "\x0aickapprox;\x08erefore;\x07etasym;\x07icksim;\x05ere4;\x05etav;\x05insp;\x05ksim;\x04eta;\x04kap;\x04orn;\x03orn",
		// &timesbar; &timesb; &timesd; &tilde; &times; &times &tint;
		'ti' => "\x07mesbar;\x05mesb;\x05mesd;\x04lde;\x04mes;\x03mes\x03nt;",
		// &topfork; &topbot; &topcir; &toea; &topf; &tosa; &top;
		'to' => "\x06pfork;\x05pbot;\x05pcir;\x03ea;\x03pf;\x03sa;\x02p;",
		// &tprime;
		'tp' => "\x05rime;",
		// &trianglerighteq; &trianglelefteq; &triangleright; &triangledown; &triangleleft; &triangleq; &triangle; &triminus; &trpezium; &triplus; &tritime; &tridot; &trade; &trisb; &trie;
		'tr' => "\x0eianglerighteq;\x0dianglelefteq;\x0ciangleright;\x0biangledown;\x0biangleleft;\x08iangleq;\x07iangle;\x07iminus;\x07pezium;\x06iplus;\x06itime;\x05idot;\x04ade;\x04isb;\x03ie;",
		// &tstrok; &tshcy; &tscr; &tscy;
		'ts' => "\x05trok;\x04hcy;\x03cr;\x03cy;",
		// &twoheadrightarrow; &twoheadleftarrow; &twixt;
		'tw' => "\x10oheadrightarrow;\x0foheadleftarrow;\x04ixt;",
		// &uArr;
		'uA' => "\x03rr;",
		// &uHar;
		'uH' => "\x03ar;",
		// &uacute; &uacute &uarr;
		'ua' => "\x05cute;\x04cute\x03rr;",
		// &ubreve; &ubrcy;
		'ub' => "\x05reve;\x04rcy;",
		// &ucirc; &ucirc &ucy;
		'uc' => "\x04irc;\x03irc\x02y;",
		// &udblac; &udarr; &udhar;
		'ud' => "\x05blac;\x04arr;\x04har;",
		// &ufisht; &ufr;
		'uf' => "\x05isht;\x02r;",
		// &ugrave; &ugrave
		'ug' => "\x05rave;\x04rave",
		// &uharl; &uharr; &uhblk;
		'uh' => "\x04arl;\x04arr;\x04blk;",
		// &ulcorner; &ulcorn; &ulcrop; &ultri;
		'ul' => "\x07corner;\x05corn;\x05crop;\x04tri;",
		// &umacr; &uml; &uml
		'um' => "\x04acr;\x02l;\x01l",
		// &uogon; &uopf;
		'uo' => "\x04gon;\x03pf;",
		// &upharpoonright; &upharpoonleft; &updownarrow; &upuparrows; &uparrow; &upsilon; &uplus; &upsih; &upsi;
		'up' => "\x0dharpoonright;\x0charpoonleft;\x0adownarrow;\x09uparrows;\x06arrow;\x06silon;\x04lus;\x04sih;\x03si;",
		// &urcorner; &urcorn; &urcrop; &uring; &urtri;
		'ur' => "\x07corner;\x05corn;\x05crop;\x04ing;\x04tri;",
		// &uscr;
		'us' => "\x03cr;",
		// &utilde; &utdot; &utrif; &utri;
		'ut' => "\x05ilde;\x04dot;\x04rif;\x03ri;",
		// &uuarr; &uuml; &uuml
		'uu' => "\x04arr;\x03ml;\x02ml",
		// &uwangle;
		'uw' => "\x06angle;",
		// &vArr;
		'vA' => "\x03rr;",
		// &vBarv; &vBar;
		'vB' => "\x04arv;\x03ar;",
		// &vDash;
		'vD' => "\x04ash;",
		// &vartriangleright; &vartriangleleft; &varsubsetneqq; &varsupsetneqq; &varsubsetneq; &varsupsetneq; &varepsilon; &varnothing; &varpropto; &varkappa; &varsigma; &vartheta; &vangrt; &varphi; &varrho; &varpi; &varr;
		'va' => "\x0frtriangleright;\x0ertriangleleft;\x0crsubsetneqq;\x0crsupsetneqq;\x0brsubsetneq;\x0brsupsetneq;\x09repsilon;\x09rnothing;\x08rpropto;\x07rkappa;\x07rsigma;\x07rtheta;\x05ngrt;\x05rphi;\x05rrho;\x04rpi;\x03rr;",
		// &vcy;
		'vc' => "\x02y;",
		// &vdash;
		'vd' => "\x04ash;",
		// &veebar; &vellip; &verbar; &veeeq; &vert; &vee;
		've' => "\x05ebar;\x05llip;\x05rbar;\x04eeq;\x03rt;\x02e;",
		// &vfr;
		'vf' => "\x02r;",
		// &vltri;
		'vl' => "\x04tri;",
		// &vnsub; &vnsup;
		'vn' => "\x04sub;\x04sup;",
		// &vopf;
		'vo' => "\x03pf;",
		// &vprop;
		'vp' => "\x04rop;",
		// &vrtri;
		'vr' => "\x04tri;",
		// &vsubnE; &vsubne; &vsupnE; &vsupne; &vscr;
		'vs' => "\x05ubnE;\x05ubne;\x05upnE;\x05upne;\x03cr;",
		// &vzigzag;
		'vz' => "\x06igzag;",
		// &wcirc;
		'wc' => "\x04irc;",
		// &wedbar; &wedgeq; &weierp; &wedge;
		'we' => "\x05dbar;\x05dgeq;\x05ierp;\x04dge;",
		// &wfr;
		'wf' => "\x02r;",
		// &wopf;
		'wo' => "\x03pf;",
		// &wp;
		'wp' => "\x01;",
		// &wreath; &wr;
		'wr' => "\x05eath;\x01;",
		// &wscr;
		'ws' => "\x03cr;",
		// &xcirc; &xcap; &xcup;
		'xc' => "\x04irc;\x03ap;\x03up;",
		// &xdtri;
		'xd' => "\x04tri;",
		// &xfr;
		'xf' => "\x02r;",
		// &xhArr; &xharr;
		'xh' => "\x04Arr;\x04arr;",
		// &xi;
		'xi' => "\x01;",
		// &xlArr; &xlarr;
		'xl' => "\x04Arr;\x04arr;",
		// &xmap;
		'xm' => "\x03ap;",
		// &xnis;
		'xn' => "\x03is;",
		// &xoplus; &xotime; &xodot; &xopf;
		'xo' => "\x05plus;\x05time;\x04dot;\x03pf;",
		// &xrArr; &xrarr;
		'xr' => "\x04Arr;\x04arr;",
		// &xsqcup; &xscr;
		'xs' => "\x05qcup;\x03cr;",
		// &xuplus; &xutri;
		'xu' => "\x05plus;\x04tri;",
		// &xvee;
		'xv' => "\x03ee;",
		// &xwedge;
		'xw' => "\x05edge;",
		// &yacute; &yacute &yacy;
		'ya' => "\x05cute;\x04cute\x03cy;",
		// &ycirc; &ycy;
		'yc' => "\x04irc;\x02y;",
		// &yen; &yen
		'ye' => "\x02n;\x01n",
		// &yfr;
		'yf' => "\x02r;",
		// &yicy;
		'yi' => "\x03cy;",
		// &yopf;
		'yo' => "\x03pf;",
		// &yscr;
		'ys' => "\x03cr;",
		// &yucy; &yuml; &yuml
		'yu' => "\x03cy;\x03ml;\x02ml",
		// &zacute;
		'za' => "\x05cute;",
		// &zcaron; &zcy;
		'zc' => "\x05aron;\x02y;",
		// &zdot;
		'zd' => "\x03ot;",
		// &zeetrf; &zeta;
		'ze' => "\x05etrf;\x03ta;",
		// &zfr;
		'zf' => "\x02r;",
		// &zhcy;
		'zh' => "\x03cy;",
		// &zigrarr;
		'zi' => "\x06grarr;",
		// &zopf;
		'zo' => "\x03pf;",
		// &zscr;
		'zs' => "\x03cr;",
		// &zwnj; &zwj;
		'zw' => "\x03nj;\x02j;",
	),
	'GTLTgtlt'
);
